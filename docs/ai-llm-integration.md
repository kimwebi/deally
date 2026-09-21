# DeAlly — Live Call AI / LLM Integration

This guide explains how DeAlly turns a live (or pre-recorded) sales call into real-time, AI-generated solutions that the sales agent can read to the customer.

## How it works

While a call is live, the browser captures microphone audio in short chunks and posts each chunk to DeAlly. DeAlly:

1. **Transcribes** the chunk (speech → text).
2. **Stores the transcript line** on the call.
3. **Asks the LLM** for 2 short suggested replies for the agent.
4. **Returns solution "cards"** that render in the live call's **Findings panel**.

The agent reads the exact suggested words to the customer, or types an ad-hoc question and gets a grounded answer built from the live transcript plus the company's **knowledge base**.

```
Agent talks ──▶ mic chunk (2s webm) ──▶ POST /app/calls/{id}/live/transcribe
                                                │
                                                ▼
                              Speech-to-text (Whisper) ──▶ transcript line saved
                                                │
                                                ▼
                              Chat completion (LLM) ──▶ 2 suggested replies
                                                │
                                                ▼
                              Findings panel (cards) ◀── the agent reads them to the customer
```

## Driver selection

`CallController::assistant()` picks the implementation automatically:

```php
protected function assistant(): DummyAssistant|LiveAssistant
{
    return blank(config('services.openai.key'))
        ? new DummyAssistant
        : new LiveAssistant;
}
```

- **`LiveAssistant`** — the real LLM integration (OpenAI). Used when `OPENAI_API_KEY` is set.
- **`DummyAssistant`** — deterministic, rule-based fallback (no key, no network). Used in demos, CI, and tests so the full flow always works.

## Configuration

Add to `.env` (config keys live in `config/services.php`):

```env
OPENAI_API_KEY=sk-...
OPENAI_TRANSCRIPTION_MODEL=whisper-1
OPENAI_CHAT_MODEL=gpt-4o-mini
OPENAI_TIMEOUT=45
```

| Env var | Default | Used by |
| --- | --- | --- |
| `OPENAI_API_KEY` | — | Enables the live driver (missing → `DummyAssistant`) |
| `OPENAI_TRANSCRIPTION_MODEL` | `whisper-1` | `LiveAssistant::transcribe()` |
| `OPENAI_CHAT_MODEL` | `gpt-4o-mini` | `LiveAssistant::suggest()` / `answer()` |
| `OPENAI_TIMEOUT` | `45` | HTTP timeout for all OpenAI calls |

Set `OPENAI_TIMEOUT` high enough to not hang a live call; the browser keeps listening while it waits.

## Endpoints

Both routes require login, the `deally.calls.manage` permission, and seat access to the call (`authorizeDeally` + `authorizeSeatRecord`), plus the usual CSRF token.

### `POST /app/calls/{call}/live/transcribe`

Multipart form with a single file field. Validated up to 10 MB (`audio` => `required|file|max:10240`).

Request:

```
audio: (binary chunk, e.g. segment.webm)
```

Successful response (`200`):

```json
{
  "ok": true,
  "transcript": "Your pricing looks higher than what we can approve this quarter — can you do better?",
  "suggestions": [
    {
      "role": "say",
      "label": "Say this",
      "subtype": "",
      "confidence": "AI · live",
      "body": "I can put together a formal proposal within two days, and we typically start with a 2-week pilot so there is no risk in locking in.",
      "package": "Suggested reply",
      "source": "AI · live transcript"
    }
  ]
}
```

Failure (`422`): `{ "ok": false, "error": "transcription_failed" }` (also logs an Activity entry).

The transcript is saved as a `TranscriptLine` (`speaker=customer`, `is_agent=false`) so the review page and `answer()` context see it.

### `POST /app/calls/{call}/live/query`

JSON body: `text` (required, max 1000 chars). Used for the agent's ad-hoc questions and objection mode (`objection: 1`).

Successful response (`200`):

```json
{
  "ok": true,
  "answer": "Yes — we offer HIPAA-compliant hosting with per-tenant data isolation.",
  "cards": [ ... ]
}
```

- **Normal query** — `answer()` is grounded in the last 10 transcript lines + the knowledge base.
- **Objection mode** (`objection: 1`) — if the LLM can't produce a confident objection reply (`subtype` is `ask`) or the text is blank, DeAlly creates a `KnowledgeGap` (status `pending`) and notifies the Solutions Lead; the returned card tells the agent what to ask.

Failure (`422`): `{ "ok": false, "error": "query_failed" }`.

## Card schema

Cards are what render in the Findings panel. The fields:

| Field | Meaning |
| --- | --- |
| `role` | `say`, `ask`, `reference`, `objection`, `waiting`, `person` |
| `label` | Header tag, e.g. `Say this` / `Ask this` |
| `subtype` | `ask` / `reply` for objections, else `""` |
| `confidence` | e.g. `High · 92%` or `AI · live` |
| `body` | The exact words the agent should say |
| `package` | Source title, e.g. a KB entry title |
| `source` | `Knowledge base · <type>`, `AI · live transcript`, etc. |

The Dummy assistant also emits `role=ask` cards when the KB has no confident match (“Ask a clarifying question…” is fed to the gap queue).

## Inside `LiveAssistant`

`modules/Calls/src/Services/LiveAssistant.php` wraps the OpenAI HTTP API with `Illuminate\Support\Facades\Http`:

- **`transcribe(string $audio, string $filename)`** — calls `POST https://api.openai.com/v1/audio/transcriptions` with the chunk and `whisper-1`. Returns trimmed text, or `null` if empty/failed.
- **`suggest(Call $call, string $customerText)`** — chat completion. The `system` prompt sets the DeAlly persona + company and injects the whole knowledge base, instructing it never to invent facts; the `user` prompt asks for **exactly two suggested replies**, one per line. Output is parsed into two `say` cards.
- **`answer(Call $call, string $query)`** — chat completion grounded in the last 10 transcript lines + KB context; the prompt tells it to answer in plain spoken English with the exact words to say, and to confirm with the onboarding team when the KB doesn't cover the question.

### Knowledge base grounding

`knowledgeContext()` serialises every `KnowledgeEntry` as:

```
[type] title: description
```

and injects it into both prompts. This is where the "solutions" the agent gives the customer come from — keep it populated (Knowledge Base module).

## Adding / swapping an AI provider

Keep the three-method contract and plug the new class into `CallController::assistant()`:

```php
public function transcribe(string $audio, string $filename): ?string;
public function suggest(Call $call, string $customerText): array;   // cards
public function answer(Call $call, string $query): ?string;
```

Example — add an Anthropic driver:

1. Create `modules/Calls/src/Services/AnthropicAssistant.php` implementing the contract.
2. Add `AnthropicAssistant` to the union type and selection logic in `CallController::assistant()`.
3. Return `null` / `[]` when your API key is missing so key-free environments fall back gracefully.
4. Emit cards in the same schema above — the frontend already renders them.

If you're not using OpenAI at all, note the current implementation hard-codes the two OpenAI endpoints and is called directly (not through a service provider binding); swapping means editing `LiveAssistant` or adding your own class.

## Testing note

- With no `OPENAI_API_KEY`, the suite exercises the complete UI flow through `DummyAssistant` — no real transcription or model calls, deterministic output.
- `LiveAssistant` guards every public method with `missingConfig()` (returns `null` / `[]`), so it never throws in tests or key-less demos.
- See `tests/Feature/DeallySmokeTest.php` and `tests/Feature/CallLifecycleTest.php` for the covered paths.