# DeAlly — Live Call AI / LLM Integration

This guide describes DeAlly's production live-call flow: browser audio capture, server-side transcription, structured conversation analysis, knowledge-grounded suggestions, the ephemeral stream, findings feedback, recording retention, and call persistence.

The surrounding flow — booking a call, sending the invitation, the customer panel, the post-call summary, the review task, corrections, flags and meeting platforms — is documented in [call-lifecycle.md](call-lifecycle.md).

Provider credentials and provider requests stay on the Laravel server. The browser sends audio and interaction requests to DeAlly; it never receives a provider API key or calls Groq or OpenAI directly.

## Architecture at a glance

```text
Agent microphone ──▶ agent chunks ───────┐
                                       ├─▶ POST /live/transcribe
Shared meeting audio ─▶ customer chunks ─┘
                                                 │
                        audio kept on disk     │  written BEFORE transcription
                                                 ▼
                                  provider speech-to-text
                                                 │
                                                 ▼
                                    TranscriptLine persisted
                                                 │
                        every chunk, both sides  ▼
                                  structured LLM analysis
                             │              │            │
                             ▼              ▼            ▼
                        signals   recommendations    noticed
                             │              │            │
                             └──────────────┴────────────┘
                                            ▼
                        CallFinding rows + CallEphemeral rows
                                            │
                            ┌───────────────┴──────────────────┐
                            ▼                                  ▼
                     Findings shelf                 KnowledgeGap / corrections
```

The browser continues recording while transcription and analysis requests are in flight. The two audio sources have independent queues so a slow customer upload does not pause the agent recorder, and vice versa.

## Browser capture flow

The live call client is implemented in `resources/js/live-call.js`.

1. **Start** requests microphone permission with `getUserMedia({ audio: true })`.
2. In parallel, the browser asks for shared meeting audio with `getDisplayMedia({ audio: true, video: true })`. The display stream is kept whole, video track included, and the video is kept out of the *recording* by handing `MediaRecorder` a `MediaStream` built from the audio tracks alone. Stopping the video track instead is **not** equivalent: Chrome treats it as ending the display capture session and the audio dies with it, so the customer stream delivers one window and then goes quiet for the rest of the call while the page still reads "Live". A share that resolves with no audio track is treated as no meeting audio rather than as a source that silently never speaks.
3. The server's live-session endpoint marks the call `in_progress` and records the resolved AI provider.
4. Each source is recorded independently with `MediaRecorder`:
   - `agent` — local microphone;
   - `customer` — shared meeting audio.
5. Each source records **4-second windows**, and every window is uploaded. If meeting sharing is declined or unsupported, capture continues with the microphone alone.

   Each window is recorded by its own short-lived `MediaRecorder` that is started and then stopped, which closes the blob as a complete WebM file with its own header. A single long-running recorder sliced with `requestData()` — the obvious implementation — emits headerless fragments after the first window, and those decode unreliably: the opening window transcribes and later ones come back empty or truncated. The next window is armed when the previous one closes, so the cadence is `4s + close latency` rather than a fixed grid.
6. Each source is metered through an `AnalyserNode` while it records, but loudness **only diagnoses — it never discards**. Two levels are read from the same meter:

   - `SILENCE_RMS = 0.0015` is a true-silence floor. A window with no measurable sound in it is skipped and never uploaded, which is the only case where audio is dropped. A customer who speaks quietly on a compressed meeting stream, or a rep on a laptop microphone, must never fall under this: a dropped window is discarded permanently, so the question is simply never transcribed and nothing in the provider's behaviour explains it.
   - `ALERT_RMS = 0.02` warns. Three consecutive quiet-but-not-silent windows across every active source is a dead microphone or an ended share, and the page says so and points at the hardware rather than at the provider. Quiet windows are still uploaded — this only changes what the rep is told.

   Invented text for silent audio ("Thank you.") is filtered server-side instead, where the transcript is actually written. The analyser is connected to a muted gain sink: an analyser with no destination is not guaranteed to be pulled by the audio graph, and a suspended context reads as permanent silence, which would discard the whole call while the page still showed "Live". The context is also resumed if the autoplay policy hands it back suspended.

7. The two sources are captured with different audio processing, because the same settings are wrong for both. The microphone uses `echoCancellation`, `noiseSuppression`, and `autoGainControl` — it is treated as a phone line, and the gain control is what keeps a quiet rep audible. The shared meeting stream disables all three: that audio is already clean, and running noise suppression and automatic gain on it pumps the level and makes speech sound thin to the transcriber. Re-acquiring a stream after a failure uses identical constraints, so the sound of the recording does not change halfway through a call.
8. Chunks upload as multipart form data. Each source keeps its own queue and client sequence counter.
9. A failed upload is retried once for HTTP `5xx` or `429`. Each source's backlog is bounded at four chunks; if the provider falls further behind, the oldest waiting chunk is dropped and the rep sees a warning toast. Each request also carries a 30-second abort: a request that never settles would otherwise hold that source's queue shut for the rest of the call, so the page would look live while transcribing nothing.
10. A watchdog notices a source that has stopped delivering audio — the browser's own "Stop sharing" bar, a muted track, or a device that disappeared — and re-arms it in place by re-acquiring the stream. A source that fails twice is left marked "needs attention" with an instruction, instead of being re-armed forever. Recapture for the microphone is silent; re-capturing meeting audio re-opens the browser's own share prompt. An ended track also stops the open window recorder immediately, rather than leaving a dead encoder attached to a stream that is gone.
11. Stopping capture closes each source's open window and waits for both queues to drain, so the tail of the conversation is not lost.
12. Ending the call stops capture first, drains the queues, and only then submits the call-completion form.

The page displays separate **You** and **Meeting** source indicators. Browser support therefore needs `MediaRecorder`, `AudioContext`, `getUserMedia`, and—when meeting capture is wanted—`getDisplayMedia`.

> Re-entering the live page is never required to keep a call transcribing. If the page does need a reload to recover, that is a bug in this layer, not expected behaviour.

### Which speech produces findings

Analysis runs on **every** transcribed chunk, whichever side spoke. The rolling window still contains both speakers, and the prompt is told to report what the newest line changes for the deal — including when the newest line is the agent's own.

This was previously gated to customer lines, on the reasoning that advising a rep on their own words is noise. In practice it produced the worst possible failure: a rep testing into their own microphone, with no meeting shared, got a working transcript and an **always-empty** shelf, which reads as a broken provider rather than a missing second stream. The prompt is a better place for that judgement than a hard gate, so an agent line is now analysed rather than dropped.

The consequence is that a rep testing solo gets cards too. That is intentional — it is the only way to exercise the shelf without a second device.

### Diagnosing a capture that stopped

`TranscriptLine.client_sequence` is the per-source counter, and it is the fastest signal available. A continuous capture increments it (`0, 1, 2, …`); a page that reloaded or restarted capture shows `0` again on every line. Every stored line reading `0` means one chunk per page load — the source is dying, not the provider being slow. The watchdog in step 9 exists so that case now repairs itself.

A gap in the sequence is not the same thing. Silence-dropped windows (step 6) are discarded before the counter advances, so a run of unanswered questions shows as missing sequence values rather than repeated zeros.

### Grounding: what the model may answer for itself

Recommendations declare a `grounding` of `kb` or `model`, and the split is deliberate:

- **`model`** — general facts any well-informed person would know: geography, country and city facts, history, industry norms, definitions, how a product category works, everyday how-tos and support questions about the customer's own devices, apps and third-party tools (e.g. "how do I turn on data roaming on a Samsung S24 Ultra?"), and how the product compares to competitors. The model answers rather than deflecting. A visible non-answer ("I don't have that information, let me check") costs the rep credibility with a customer who can already see the question is answerable, so refusing these is its own failure — a standard, well-documented answer is returned directly, not raised as a knowledge gap.
- **`kb`** — anything about **this company's own** commercial terms: prices, discounts, contract wording, seat and usage limits, SLAs, which of our own features exist, timelines, certifications. These are the claims that cost a deal when they are wrong, so the model is barred from guessing and must use a knowledge base entry or commit to a named follow-up. A `knowledge_gap` is raised only for these — a customer's device or a third-party tool is not "our" commercial terms.

`LiveAssistant::knowledgePackage()` renders the difference on the card footer: a `kb` item that names a real entry is rendered as that document's title, while a `model` item reads `answered from model knowledge — verify before quoting`, because there is no document behind it and the rep should not quote it unchecked. An unrecognised `grounding` value is treated as `kb` — provider output is untrusted, and "open a document" is the safe side of that mistake.

### Hallucinated filler

Speech-to-text models invent short stock phrases for audio that contains no speech — most often `Thank you.`, `Bye.`, or a subtitle credit. Two defences keep them out of the product:

- **Requests are constrained.** Transcription runs with `temperature: 0` and an explicit `language: en`.
- **Responses are filtered.** `LiveAssistant::isHallucination()` normalises the returned text and discards `LiveAssistant::HALLUCINATION_PHRASES` and anything without letters, returning `null`. A recording window that resolves to punctuation, digits, or a stock phrase (`Thank you.`, `Subscriptions by…`) is a breath, a click, or room noise rather than an utterance. A single real word — including the backchannel `Mm-hmm.` — is kept, because a rep wants to read it back. The controller already treats a `null` transcript as silence, so a hallucinated chunk is not stored and never reaches the analysis window.

> No transcription `prompt` is sent. Whisper treats the prompt as a prior to continue from and echoes it back as transcript text when the audio is thin — a domain prior reading `B2B sales call for DeAlly…` put the literal line `B2B sales.` into a real customer's transcript. The vocabulary bias was not worth the risk of the product quoting itself back to the rep, so the filter above carries the whole defence.

> The UI tells the rep that the call is recorded, and the browser requires the rep to approve the microphone and screen-share prompts. A deployment must also obtain any consent required by its recording policy, customer contracts, and applicable law.

## Provider selection

All providers implement `Deally\Calls\Contracts\CallAssistant`:

```php
public function name(): string;
public function transcribe(string $audio, string $filename): ?string;
public function suggest(Call $call, string $customerText): array;
public function analyze(Call $call, array $lines, array $reported = []): array;
public function answer(Call $call, string $query): ?string;
public function answerGlobal(string $query): ?string;
```

`analyze()` returns `signals`, `recommendations`, `noticed`, `proposal_intent` and
`proposal_intent_note`. The last three were added when the ephemeral stream and the post-call
summary needed them:

- **`noticed`** is a paraphrase of what the newest line means for the deal, at most ~12 words, in
  present tense, with no quotation marks. It becomes the stream's **Heard** card and the review's
  "what was heard" list. `LiveAssistant::normalizeParaphrase()` enforces the shape server-side,
  because provider output is untrusted and a "paraphrase" that comes back as a verbatim quote is
  exactly the failure the card exists to prevent — a rep reads it as the customer's exact words.
- **`proposal_intent`** is set only when the conversation actually agreed a proposal.
  `proposal_intent_note` records the terms that were agreed. Together these are the only thing that
  reveals the **Create Proposal** button, because an unconditional button on every call is a
  suggestion the rep has to evaluate and discard rather than an action.

`AssistantFactory` resolves the implementation from `LIVE_AI_DRIVER`:

| Value | Implementation | Behavior |
| --- | --- | --- |
| `auto` | `AssistantFactory` resolution | Prefers Groq, then OpenAI, then the demo driver outside production |
| `groq` | `GroqAssistant` | Uses Groq's OpenAI-compatible transcription and chat endpoints |
| `openai` | `LiveAssistant` | Uses the configured OpenAI endpoints |
| `dummy` | `DummyAssistant` | Runs the deterministic demo experience with no provider requests |

In production, `auto` does **not** silently fall back to fabricated AI output. If neither provider key is configured, it selects Groq and the live request fails with a controlled `503` until credentials are supplied.

The call stores the selected provider in `calls.ai_provider`; transcript lines and findings also retain the provider that produced them.

## Configuration

Add the desired provider and live-call settings to `.env`:

```env
# Driver: auto, groq, openai, or dummy
LIVE_AI_DRIVER=auto

# Groq (preferred by auto)
GROQ_API_KEY=
GROQ_BASE_URL=https://api.groq.com/openai/v1
GROQ_TRANSCRIPTION_MODEL=whisper-large-v3-turbo
GROQ_CHAT_MODEL=openai/gpt-oss-20b
GROQ_TIMEOUT=45
GROQ_CA_BUNDLE=

# OpenAI-compatible alternative
OPENAI_API_KEY=
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_TRANSCRIPTION_MODEL=whisper-1
OPENAI_CHAT_MODEL=gpt-4o-mini
OPENAI_TIMEOUT=45
OPENAI_CA_BUNDLE=

# Optional analysis override and rolling-window controls
LIVE_AI_ANALYSIS_MODEL=
LIVE_AI_ANALYSIS_WINDOW=8
LIVE_AI_ANALYSIS_MIN_INTERVAL=10
LIVE_AI_SHELF_LIMIT=10
LIVE_AI_REPORTED_FINDINGS=6
```

| Setting | Default | Purpose |
| --- | --- | --- |
| `LIVE_AI_DRIVER` | `auto` | Selects `auto`, `groq`, `openai`, or `dummy` |
| `GROQ_API_KEY` | — | Authenticates the Groq driver on the server |
| `GROQ_BASE_URL` | `https://api.groq.com/openai/v1` | Groq API base URL |
| `GROQ_TRANSCRIPTION_MODEL` | `whisper-large-v3-turbo` | Groq speech-to-text model |
| `GROQ_CHAT_MODEL` | `openai/gpt-oss-20b` | Groq suggestions, answers, and default analysis model |
| `GROQ_TIMEOUT` | `45` | Groq request timeout in seconds |
| `GROQ_CA_BUNDLE` | — | Absolute path to a CA bundle for verifying Groq's certificate; required on hosts where cURL has no trust store |
| `OPENAI_API_KEY` | — | Authenticates the OpenAI driver on the server |
| `OPENAI_BASE_URL` | `https://api.openai.com/v1` | OpenAI-compatible API base URL |
| `OPENAI_TRANSCRIPTION_MODEL` | `whisper-1` | OpenAI speech-to-text model |
| `OPENAI_CHAT_MODEL` | `gpt-4o-mini` | OpenAI suggestions, answers, and default analysis model |
| `OPENAI_TIMEOUT` | `45` | OpenAI request timeout in seconds |
| `OPENAI_CA_BUNDLE` | — | Absolute path to a CA bundle for verifying the OpenAI endpoint's certificate |
| `LIVE_AI_ANALYSIS_MODEL` | provider chat model | Optional model override for structured analysis |
| `LIVE_AI_ANALYSIS_WINDOW` | `8` | Number of recent transcript lines sent to analysis |
| `LIVE_AI_ANALYSIS_MIN_INTERVAL` | `10` | Minimum seconds between analysis attempts for a call; `0` disables throttling |
| `LIVE_AI_SHELF_LIMIT` | `10` | Newest findings rendered in the live shelf; older cards stay saved for review |
| `LIVE_AI_REPORTED_FINDINGS` | `6` | Already-reported finding bodies fed back to the model so each pass reports what is new |

OpenAI and Groq deliberately send different completion-limit keys. OpenAI uses `max_tokens`; Groq reasoning models use `max_completion_tokens`. The value is provider configuration rather than something exposed to the client.

The analysis ceiling is sized for **reasoning plus answer**, not for the answer alone. A reasoning model draws its thinking from the same budget, and Groq reports running out mid-document as `HTTP 400` with `json_validate_failed` and `failed_generation: "max completion tokens reached before generating a valid document"` — not as a server error. That response is treated as retryable for the same reason a `5xx` is: it is a budget problem and the identical request can succeed on a retry.

After configuration changes, clear Laravel's cached configuration in a deployed environment:

```bash
php artisan config:clear
```

### Choosing the analysis model for more factual findings

Findings come from a chat model: `GROQ_CHAT_MODEL` by default, or `LIVE_AI_ANALYSIS_MODEL` when that override is set. The default `openai/gpt-oss-20b` is a compact model — fine for everyday how-tos and commonsense questions, but on niche facts (a product launch date, an obscure geography or history fact) it hedges into `knowledge_gap` ("let me confirm and I'll get back to you") rather than risk a wrong answer. If findings keep deflecting questions that do have an answer, route the findings pass at a model with stronger factual recall:

```env
# Targets only the structured analysis pass that creates findings
LIVE_AI_ANALYSIS_MODEL=llama-3.3-70b-versatile
```

or lift the shared chat model to also improve the spoken suggestions and Ask DeAlly answers:

```env
GROQ_CHAT_MODEL=openai/gpt-oss-120b
```

- `LIVE_AI_ANALYSIS_MODEL` is the surgical knob: transcription and the cheap default stay put, only the cards get the bigger brain.
- `GROQ_CHAT_MODEL` (and `OPENAI_CHAT_MODEL` on the OpenAI driver) is the default analysis model when no override is set, and also powers suggested replies and Ask answers — raising it improves facts everywhere at a slightly higher cost and latency per call.
- On Groq, `llama-3.3-70b-versatile` and `openai/gpt-oss-120b` are broadly available with better factual recall than the 20B default. Bigger models hedge less but answer slightly slower and cost more per call — worth it when factual answers are the point of the findings.
- The grounding guard is unchanged regardless of model: this company's own prices and features must still come from the knowledge base, and every model answer stays labelled `answered from model knowledge — verify before quoting`.
- Change the value in `.env` and run `php artisan config:clear`; new calls pick it up immediately.

## Endpoints

All live-call mutation routes use the `deally` middleware group and therefore require authentication, a valid CSRF token, the `deally.calls.manage` permission, and seat access to the call. Provider credentials are read only from Laravel configuration.

### Start a capture session

`POST /app/calls/{call}/live/start`

The endpoint is idempotent while a call is active. It sets the status to `in_progress`, preserves the original `started_at` value on repeated starts, and records `ai_provider`.

```json
{
  "ok": true,
  "status": "in_progress",
  "provider": "groq",
  "started_at": "2026-09-25T14:30:00+00:00"
}
```

A completed call cannot be restarted and returns:

```http
409 Conflict
```

```json
{ "ok": false, "error": "call_completed" }
```

### Transcribe and analyze a chunk

`POST /app/calls/{call}/live/transcribe`

Multipart fields:

| Field | Rules | Meaning |
| --- | --- | --- |
| `audio` | Required file, maximum 20 MB | Current source's compressed recording chunk |
| `speaker` | Required: `agent` or `customer` | Source that produced the audio |
| `chunk_id` | Required string, maximum 64 characters | Stable idempotency key for this chunk |
| `client_sequence` | Required integer, minimum `0` | Sequence maintained independently by the source |
| `started_at_ms` | Optional integer | Browser timestamp for the chunk window |
| `duration_ms` | Optional integer, maximum `60000` | Chunk duration |
| `is_final` | Optional boolean | Marks the final recorder flush for the source |

A successful, non-silent response contains the stored transcript line, any findings produced by that request, and the ephemeral cards the stream should show:

```json
{
  "ok": true,
  "duplicate": false,
  "transcript": "Your pricing looks higher than what we can approve this quarter.",
  "line": {
    "id": 42,
    "speaker": "customer",
    "is_agent": false,
    "sequence": 7
  },
  "findings": [],
  "suggestions": [],
  "ephemerals": [
    {
      "id": 118,
      "kind": "heard",
      "label": "Heard",
      "body": "weighing us against Cisco on support coverage",
      "source": "openai",
      "line_id": 42
    }
  ],
  "analysis_failed": false
}
```

`suggestions` is retained as an alias of `findings` for compatibility with the existing findings renderer.

Every ephemeral in the response is already a `call_ephemerals` row. The stream is DOM-only, but the cards it was showing are written to the call's permanent record first, so the review task and the review page can show what the AI was attending to at each moment. A card fading after a few seconds is a *visibility* rule, not a deletion — and there is no expiry column to tempt anyone into treating it as one.

`analysis_failed` separates "the AI had nothing to say" from "the AI could not be reached". An empty `findings` array on its own is indistinguishable from a provider that has stopped working, which is what made the shelf look permanently broken while the transcript kept arriving. The browser shows the distinction on the source label.

#### Silence

Whitespace-only provider transcription is a normal outcome, not an error. No line is stored and the chunk succeeds. The response carries the same keys as a real one, so the renderer does not need a branch to learn there was nothing:

```http
200 OK
```

```json
{
  "ok": true,
  "silent": true,
  "transcript": null,
  "line": null,
  "findings": [],
  "suggestions": [],
  "ephemerals": [],
  "analysis_failed": false
}
```

#### Analysis failures

The audio upload and the analysis are separate provider calls, and they fail separately. A failed analysis never fails the upload: the audio is stored, the chunk returns `200`, and the next window can still produce cards.

```json
{
  "ok": true,
  "transcript": "What is in the enterprise tier?",
  "line": { "id": 44, "speaker": "customer", "is_agent": false, "sequence": 9 },
  "findings": [],
  "analysis_failed": true
}
```

There is deliberately no `retryable` flag on this response. The chunk succeeded, so the browser must not re-upload the audio; `analysis_failed` is what it acts on instead. The failure is logged to the activity table as `call.provider_failed` with `operation: analysis`.

#### Retried chunks

`(call_id, client_chunk_id)` is unique. Replaying a chunk returns the already-stored line with `duplicate: true` and does not transcribe or analyze it again:

```json
{
  "ok": true,
  "duplicate": true,
  "transcript": "We need SSO for security.",
  "line": {
    "id": 43,
    "speaker": "customer",
    "is_agent": false,
    "sequence": 8
  },
  "findings": [],
  "suggestions": [],
  "ephemerals": [],
  "analysis_failed": false
}
```

A duplicate is never re-analyzed: the original response already carried the cards, and re-running the model for a chunk the rep's browser retried would put the same advice on the shelf twice.

#### Errors

- `409` — the call is already completed.
- `422` — request validation failed.
- `503` — the provider is unavailable, missing credentials, or returned an upstream failure.

Provider error payloads and credentials are not returned to the browser:

```json
{
  "ok": false,
  "error": "transcription_unavailable",
  "retryable": true
}
```

The provider's HTTP status determines whether the browser may retry. A transcription provider failure is written to the call's Activity history without exposing upstream response details.

#### A `503` with no HTTP status means the request never arrived

`status: null` on a `call.provider_failed` Activity entry is the signal that no HTTP response was received at all. The `reason` property carries the transport error, because a TLS trust failure and an unreachable host are otherwise indistinguishable:

```json
{
  "status": null,
  "retryable": true,
  "reason": "The groq provider could not be reached: cURL error 60: SSL certificate problem: unable to get local issuer certificate"
}
```

On Windows this is almost always cURL error 60 rather than a network problem: PHP's cURL has no trust store of its own unless `curl.cainfo` is set **for the SAPI serving the app**. A bundle exported for the shell does not reach an already-running php-fpm, so the same code succeeds in `artisan tinker` and fails on every web request. Set `GROQ_CA_BUNDLE` (or `OPENAI_CA_BUNDLE`) to the absolute path of a CA bundle instead — `LiveAssistant::caBundle()` passes it to the HTTP client, so verification no longer depends on how PHP was launched. A configured path that is not readable is logged as a warning and ignored.

### Save finding feedback

`POST /app/calls/{call}/live/findings/{finding}/feedback`

JSON body:

```json
{ "status": "unhelpful" }
```

Allowing a `knowledge_gap` finding to fire again does not stack cards: once a gap is created it stays the only pending copy of that question until it is resolved. Allowed statuses are `helpful` and `unhelpful`, and the finding must belong to the call in the URL. Marking a finding `unhelpful` creates or reuses a linked `KnowledgeGap` for the Solutions workflow, so repeated feedback does not create duplicate gaps. The same text-aware dedupe applies across the whole queue: a pending gap is keyed by its `type` and a canonical form of the text (lowercased, punctuation and whitespace ignored), so re-worded copies the model rephrased between passes or calls — "Do you support HIPAA?" vs "do you support hipaa !" — share one queue item instead of piling up, and resolving one instance also retires sibling pending gaps for the same question.

Successful response:

```json
{ "ok": true, "status": "unhelpful" }
```

### Ask DeAlly during the call

`POST /app/calls/{call}/live/query`

JSON body:

```json
{ "text": "What does the enterprise tier include?" }
```

`text` is required and limited to 4,000 characters. The Ask box is an auto-growing textarea —
**Enter** sends and **Shift+Enter** starts a new line — so a long question is never collapsed to a
single cramped line, and the cap matches a pasted or dictated paragraph rather than a one-liner. A
successful response contains a knowledge-grounded answer and up to two suggestion cards:

```json
{
  "ok": true,
  "answer": "The enterprise tier includes SAML SSO and per-tenant data isolation.",
  "cards": []
}
```

`answer()` uses the most recent 10 transcript lines plus the tenant's knowledge-base entries. `suggest()` also uses that knowledge context.

The query form can send `objection: 1`. In that mode, DeAlly asks the assistant for objection-handling cards. If the assistant cannot return a documented objection reply, DeAlly records a pending gap and returns a card asking the rep for the missing detail. An answer failure returns `422` with `query_failed`; the live audio recorder is unaffected.

Every successful query is written to `call_queries` before the response is returned. The live panel shows these as a chat, but a chat the rep cannot look back at is why the review page used to hold nothing but the customer's questions.

### Replay a captured window

`GET /app/calls/{call}/recordings/{recording}`

Streams one stored window back with its own content type. The recording must belong to the call in the URL; anything else is a `404`. Used by the review page's player, not called directly by the browser's media element — the player fetches each window as a blob so it can sequence them.

## Replay and the full conversation

Two things the review page could not do before, both of which made it useless as the one place a rep goes afterwards.

### The audio is kept

Every uploaded window is written to the **private** disk under `calls/{call_id}/{source}-{sequence}-{hash}.{ext}` and recorded in `call_recordings`, *before* the provider is asked to transcribe it and whatever the provider then says.

The ordering is the point. A provider outage is exactly when a rep most wants the audio back, and the browser already promises them that "the call is still being recorded" when a window fails — a promise the previous code did not keep, because the uploaded bytes were discarded with the request.

`(call_id, client_chunk_id)` is unique, so a retried upload replaces its own window rather than adding a second copy of the same four seconds. A storage failure is logged and swallowed: losing the recording must never cost the rep their transcript.

These windows come from a stream with no video track, but some browsers still label the container `video/webm`. The stored content type has its family corrected to `audio/*`, because an `<audio>` element refuses `video/webm` outright and replay would fail on a codec the file actually contains.

**Storage cost.** Opus at the bitrate MediaRecorder defaults to is roughly 0.5 MB per minute of call across both streams, so about 30 MB per hour. Nothing prunes it: a call's audio lives as long as the call row. If retention matters, that is a scheduled cleanup against `call_recordings` and the `calls/{id}` directory, and it is deliberately not implemented here — deleting a recording is not something the app should do unasked.

### The review holds the whole exchange

`GET /app/calls/{call}/review` now renders:

- **Replay bar** — play/pause, a seekable progress bar, and a source filter for both streams or one. Windows are sequenced through a single audio element and prefetched three ahead as blobs; without the prefetch every four seconds of audio costs a round trip and the call audibly stutters. If a browser cannot decode the container, the player says so and the transcript is unaffected.
- **Per-line playback** — each transcript line carries the window it was transcribed from, so a rep can jump to the moment a line was said. Clicking a line whose source the active filter is hiding widens the filter rather than refusing to play.
- **What DeAlly said** — findings grouped onto the line that triggered them, so the transcript reads as an exchange rather than a list of questions with an unrelated report beside it. Findings that could not be attached to a line are counted in the panel rather than dropped.
- **You asked DeAlly** — the persisted `call_queries`, prompt and answer.
- **Agent Performance** — the AI-suggestion and marked-useful counts replace a "follow-ups captured" figure that only ever restated the line count.

The downloaded transcript carries the same content: the assistant's cards inline under the line that prompted them, and a `QUESTIONS ASKED DEALLY` section at the end.

A call with no stored audio says so plainly instead of offering a dead player. Seeded and imported calls have a transcript but no recording, and pre-existing calls cannot be made replayable — the bytes were discarded at the source.

### End the call

`POST /app/calls/{call}/end`

The normal end-call form records duration, sentiment, notes, and summary, sets `ended_at`, marks the call `completed`, and redirects to the summary. New chunks and restarts are rejected after completion.

The browser drains both source queues before submitting this form.

## Structured live-call analysis

`LiveAssistant::analyze()` is the real-time analysis path. It runs after **any** chunk that produced a stored line, whichever side spoke. See [Which speech produces findings](#which-speech-produces-findings) for why that gate was removed.

### Rolling window and throttling

- The most recent `LIVE_AI_ANALYSIS_WINDOW` transcript lines are sent in chronological order.
- Each source continues transcribing at the normal chunk rate; analysis is independently throttled.
- `calls.last_analyzed_at` records the latest attempt. This prevents a provider outage from causing every subsequent chunk to make another immediate request.
- The default 4-second source chunks and 10-second analysis interval are tuned for a responsive findings cadence while keeping two-stream steady-state usage within the expected Groq request budget. Two streams at 4 seconds is roughly 30 transcription requests a minute, so a plan's rate limit is the binding constraint, not this default. These are not a global concurrency control: multiple simultaneous calls can exceed a provider account's limits. Raise `CHUNK_MS` in `resources/js/live-call.js` or `LIVE_AI_ANALYSIS_MIN_INTERVAL` if your account budget is tight.
- `CHUNK_MS` is also a transcription-quality trade-off, not only a cost one. A window has to be long enough to contain a whole sentence with its context, and too long a window delays the first card. Below roughly 3 seconds, Whisper starts returning fragments; above roughly 8 seconds, a question that starts late in the window is cut off. 4 seconds sits inside that band.
- **Long transcriptions and questions.** Because each window is transcribed on its own and analysis reads the last `LIVE_AI_ANALYSIS_WINDOW` (default 8) stored lines, a customer question that runs across several windows — or pauses in the middle — still reaches the model whole; the chunk boundary never cuts a sentence off mid-thought. The same principle applies to typed asks: the Ask input accepts up to 4,000 characters in an auto-growing textarea, so the full question is sent rather than a truncated opener.

### Signal and recommendation schema

Analysis requests use OpenAI-compatible structured output:

```json
{
  "type": "json_schema",
  "json_schema": {
    "name": "live_call_analysis",
    "strict": true,
    "schema": {}
  }
}
```

The server validates the decoded output again. It drops malformed records, unknown signal kinds, unknown recommendation roles, blank bodies, and source line IDs outside the submitted transcript window. Confidence is clamped to `0..1`; a missing or invalid source line is anchored to the latest stored line when the finding is persisted.

Supported signal kinds:

- `buying_signal`
- `intent`
- `objection`
- `competitor`
- `risk`
- `knowledge_gap`

Supported recommendation roles:

- `say`
- `ask`
- `reference`
- `objection`

Alongside the arrays, the schema carries three scalar fields:

| Field | Type | Purpose |
| --- | --- | --- |
| `noticed` | string, nullable | Paraphrase of what the newest line changes for the deal; the **Heard** card |
| `proposal_intent` | boolean | Whether a proposal was actually agreed |
| `proposal_intent_note` | string | The terms that were agreed, in the model's words |

The prompt asks for high-signal observations rather than a fixed number of cards, and an empty `signals` or `recommendations` array is valid. An empty or absent `noticed` simply produces no Heard card — it is never filled in locally, because a locally-written summary of a customer's words is a quotation the rep did not hear.

### Knowledge grounding

Every Knowledge Base entry is serialized into the analysis, suggestion, and answer prompts as:

```text
[type] title: description
```

Recommendations must be supported by the transcript and knowledge base. A detected `knowledge_gap` creates a linked pending `KnowledgeGap`, allowing the Solutions Lead to add or correct the answer.

The prompt is explicit that only facts present in the knowledge base may be stated, and that an uncovered question should produce a follow-up commitment rather than a likely-sounding answer from the model's own knowledge.

### What the rep is told to do

A card has to answer "what do I do with this?" the moment it lands, so:

- A signal's `text` must name what is missing or what changed **and** the next step. The prompt forbids restating the customer's question back as the finding.
- Analysis is anchored on the **newest** line, whichever side spoke; earlier lines are context only.
- The most recent `LIVE_AI_REPORTED_FINDINGS` finding bodies are sent back with the prompt, so a pass reports what is new instead of re-deriving a card that is already on the shelf. `persistFindings()` still dedupes by body, so without this the shelf would show one finding while the conversation moved on.
- A recommendation's `package` is the footer telling the rep what to open. It is verified against real `KnowledgeEntry` titles: anything else — a blank, `default`, `general`, or the signal kind echoed back — becomes `LiveAssistant::NO_KNOWLEDGE_PACKAGE` (`no KB entry — commit to a follow-up`). The rep therefore always sees either a document to open or an explicit commitment to make.

Analysis failure never rolls back a successfully transcribed chunk. The line remains stored, the failure is logged, and the current response contains no new findings.

## Findings and feedback loop

Every accepted signal or recommendation is persisted as a `CallFinding`. A per-call SHA-256 dedupe key prevents the same finding from being inserted repeatedly across rolling analyses.

A finding stores:

- type (`signal` or `recommendation`);
- kind or recommendation role;
- display label and spoken body;
- source quote/package/source text;
- confidence and provider;
- optional source transcript line;
- feedback status (`new`, `helpful`, or `unhelpful`).

`CallFinding::toCard()` maps the stored row into the existing findings-shelf shape (`id`, `type`, `kind`, `role`, `label`, `body`, `package`, `source`, `confidence`, `status`). The live page embeds persisted findings in `initial-findings`, so reloading the page restores the shelf immediately.

The feedback flow closes the learning loop:

1. A rep marks a finding **unhelpful**.
2. The status is stored on the finding.
3. One linked `KnowledgeGap` is created or reused.
4. The Solutions workflow can review the correction and update the Knowledge Base.

## The ephemeral stream

Alongside the shelf, the live page runs a **stream** of short-lived cards: the newest thing that
happened, said once, then faded. It is the right shape for a call, where the shelf is for things that
still matter and the stream is for things that just did.

| `kind` | What it is |
| --- | --- |
| `heard` | The model's paraphrase of what the customer just said |
| `detected` | A signal the analysis picked up |
| `gap` | A question the knowledge base cannot answer |
| `asked` | What the rep asked DeAlly and what it answered |
| `objection` | An objection, detected or added by the rep during review |

Three rules keep the stream honest:

**Every card is a row first.** The card is written to `call_ephemerals` before the response is
returned, and the response carries it back in `ephemerals`. The fade governs live *visibility* and
nothing else — there is no expiry column, so nothing can be swept up as stale. `CallReviewBrief`
and the review page read those rows back, which is the only reason a rep asking "what did it catch?"
after the call gets an answer from the record rather than from their memory of a scrolling panel.

**"Clear the findings" is display-only.** It empties the DOM. It does not delete rows — "clear" in a
sales tool that also means "no longer know what was said" is a trap, and a rep who pressed it
believing the cards were gone would be right.

**The client never writes a customer's words.** `clipOwnWords()` in `resources/js/live-call.js` only
ever shortens the rep's **own typed input** for the chat echo. Anything attributed to the customer
comes from the model, and the `heard` shape is enforced server-side by
`LiveAssistant::normalizeParaphrase()`.

Cards are distinguished by **edge treatment**, not colour: a solid edge is a *say*, a dashed edge an
*ask*, no accent a *reference*, a dotted edge *waiting*, and a red left border an *objection*. Colour
alone excludes anyone with a colour vision deficiency, and "important" needs to survive a screenshot
in a doc. The hero card is separated by structure — a rule above it and the choice in its body — and
is dismissed by answering rather than by closing.

## Corrections, flags and proposal intent

Three endpoints exist because a review that cannot change anything is a report.

### `POST /app/calls/{call}/corrections`

Corrects `sentiment`, `readiness`, `competitor_tag`, `objection_tag` or `gap_classification`. The
effective value is written onto the call, the model's read is kept in `ai_sentiment` / `ai_readiness`,
and the difference is stored as a `call_corrections` row. Overwriting the AI's read would throw away
the only part of a correction worth learning from.

### `POST /app/calls/{call}/flags` and `POST /app/calls/{call}/flags/{flag}/resolve`

A deal-status flag is what makes the review task *unclosable*. `Task::unresolvedFlags()` and
`Task::blockedReason()` are the single predicate, used by both the tasks list and the review modal,
and `TaskController::toggle()` refuses the close. Resolving requires a note, because "resolved" with
no reason is indistinguishable from "ignored".

### `POST /app/calls/{call}/proposal`

Refuses with `409 no_proposal_intent` unless the analysis set `proposal_intent`. On success it
raises a `Draft proposal — {company}` task carrying `proposal_intent_note` and the call date, so
whoever writes it knows the terms that were agreed rather than starting from the recording.

### `POST /app/calls/{call}/objections`

Records an objection the model missed as both a `knowledge_gap` and an `objection` ephemeral, so it
belongs in the objection log and the stream alike. An optional `transcript_line_id` is scoped to the
call in the URL; a line from another call returns `422 line_not_in_call` rather than being quietly
unattached.

## Persistence model

The tenant migration `2026_09_25_000001_add_live_capture_support_to_calls_tables.php` adds:

### `calls`

- `started_at`
- `ended_at`
- `ai_provider`
- `last_analyzed_at`

### `transcript_lines`

- `client_chunk_id`
- `client_sequence`
- `started_at_ms`
- `duration_ms`
- `is_final`
- `provider`

A unique `(call_id, client_chunk_id)` index makes retries idempotent. The server allocates the canonical transcript `sequence` while holding a row lock, so concurrent uploads cannot receive the same position.

### `call_findings`

Stores persisted signals and recommendations, including confidence, provider, source line, feedback status, and a per-call dedupe key.

### `knowledge_gaps`

Adds optional `call_id`, `transcript_line_id`, and `call_finding_id` relations. The Calls models declare these relations, keeping the module dependency directional and avoiding a Proposals-to-Calls model cycle.

### `call_recordings`

One row per captured window, written whether or not it transcribed: `source`, `client_chunk_id`, `client_sequence`, disk `path`, `mime`, `started_at_ms`, `duration_ms`, and `bytes`. Unique on `(call_id, client_chunk_id)` so a retried upload replaces its own window.

Rows are metadata only. The audio lives on the private disk, and the row is what ties a window back to a call, a source and a transcript line.

### `call_queries`

What the rep asked the assistant during the call and what it answered: `prompt`, `answer`, `cards` (JSON), and `provider`.

The initiation and review schema is a second migration, `2026_09_28_000001_add_initiation_and_review_support_to_calls_tables.php`, which adds `meeting_platforms`, `call_invitations`, `call_ephemerals`, `call_corrections` and `call_flags`, seventeen `calls` columns, and `tasks.call_id`. It is covered in [call-lifecycle.md](call-lifecycle.md#data-model), including the one rule that catches people out: a tenant table cannot declare a foreign key to a central table such as `users`.

For existing tenant databases, run:

```bash
php artisan tenant:migrate
```

## Demo mode

`LIVE_AI_DRIVER=dummy` explicitly enables the deterministic demo experience. In non-production environments, `auto` selects it when no provider key is configured.

In demo mode:

- the page can run the scripted competitor/pricing/knowledge-gap narrative;
- `DummyAssistant::transcribe()` returns `null` rather than pretending to transcribe browser audio;
- ending a call that never started capture and has no transcript seeds a full customer/agent demo conversation for review and summary pages.

A call with `started_at` or any real captured transcript is never overwritten by demo seed data. The client only runs the scripted narrative when the server explicitly renders `data-demo-mode="true"`.

## Adding or swapping a provider

1. Implement `Deally\Calls\Contracts\CallAssistant` in a new service.
2. Add the provider configuration under `config/services.php` and its variables to `.env.example`.
3. Add the driver name to `AssistantFactory::DRIVERS` and its factory branch.
4. Preserve these semantics:
   - return `null` for a silent transcription;
   - throw `AssistantProviderException` for missing credentials, connection failures, and provider HTTP errors;
   - make structured analysis JSON-schema compatible and defensively validate the response;
   - use provider-specific completion parameter names where required.
5. Do not send credentials or provider endpoints to the browser.

`GroqAssistant` demonstrates the lighter adapter approach: it extends the provider-configurable `LiveAssistant` and changes only the server-side config and driver name. A provider with a materially different API should implement the contract directly.

## Security and operational notes

- Provider keys remain in server environment variables and are never rendered into Blade, JSON, browser storage, or client JavaScript. There is no in-app provider login: a key is configured on the server or not at all.
- The client talks only to tenant-scoped DeAlly routes protected by authentication, CSRF, permission, and seat checks.
- Generic `AssistantProviderException` messages prevent upstream payloads and credentials from leaking through HTTP responses. The underlying transport error is preserved on the exception and logged as the `reason` on the `call.provider_failed` Activity entry, so a failure is diagnosable from the call's own history rather than only from the server log.
- Captured audio is written to the **private** disk, never to a public path, and is served only through a route that checks the call in the URL actually owns the window.
- Transcript and finding writes occur only after a usable provider response; silence does not create fake rows. Recording writes are the deliberate exception, so a provider outage cannot destroy the call.
- Provider rate limits are partly controlled by chunk size and analysis throttling, but concurrent-call queuing or account-level capacity management is not implemented yet.
- Operational monitoring should cover `call.provider_failed` Activity entries, retryable `503` responses, and queue-drop warnings in the browser.

## Tests

The primary coverage is in:

- `tests/Feature/LiveAssistantTest.php` — driver selection, dual-stream upload, speaker labeling, idempotency, sequencing, silence, controlled failures, lifecycle timestamps, analysis throttling, strict provider output validation, findings persistence, knowledge gaps, long-question handling, feedback, Groq configuration, credential non-disclosure, query behavior, and authorization. It also covers recording retention across provider failures, replay streaming and its cross-call guard, the review page's contents, and the assistant's half of the downloaded transcript.
- `tests/Feature/CallLifecycleTest.php` — call completion and demo transcript guards, including preserving genuinely captured audio.
- `tests/Feature/CallInitiationAndReviewTest.php` — the `analyze()` contract's new scalars, the uniform silence response, the ephemeral stream's persistence, and the whole initiation and review lifecycle. See [call-lifecycle.md](call-lifecycle.md).

Tests fake provider HTTP responses, so the suite does not require or make real AI provider calls. They also do not fake mail: the invitation is really rendered through the `log` mailer, so a change that breaks the mailable template fails the suite.

The whole suite is 313 tests and 1,310 assertions. Run the narrowest relevant tests while developing, then the full suite:

```bash
php artisan test --compact
```

---

© 2026 Wyzone Labs. All rights reserved.
