# DeAlly — Call Lifecycle: Initiation, Live, Review

This guide covers everything around a call: how one is scheduled and invited, what happens while it is
live, what the rep sees afterwards, and how the review becomes real work. The live audio and AI
pipeline itself is documented separately in [ai-llm-integration.md](ai-llm-integration.md).

The module is `Deally\Calls`. Everything below runs under the `deally` middleware group and requires
authentication and a valid CSRF token, `deally.calls.manage` for anything that writes and
`deally.calls.view` for anything that reads, plus seat access to the individual record.

## What this document is really about

Most of the design decisions here are about **not lying**. A rep who is told a meeting was created, or
that a bot joined, will act on it — send the link, wait for the customer, stop taking notes. Every
guarantee below is enforced by a test whose main assertion is that the system refuses to invent
something:

- No join URL is ever assembled locally. `calls.meeting_join_url` is only ever written from a
  connector's response.
- No bot is ever reported as joined unless a provider said so.
- The customer panel beside a live call shows real records, and says so when it has none.
- An empty findings shelf is distinguishable from a broken provider.
- Nothing in the ephemeral stream is thrown away after the live call, and something reads it back —
  "Clear the findings" hides cards, it does not delete rows.

---

## 1. Data model

Tenant migration `2026_09_28_000001_add_initiation_and_review_support_to_calls_tables.php` adds five
tables, seventeen `calls` columns and `tasks.call_id`. Apply it to an existing tenant database with:

```bash
php artisan tenant:migrate
```

### `meeting_platforms`

Which meeting platforms this company has turned on. The picker offers only rows with
`enabled = true`, ordered by `sort_order`. `connected` and `connection_note` are the honesty
surface: a platform that cannot create a meeting must never be presented as though it can.

| Column | Meaning |
| --- | --- |
| `key` | Stable identifier (`zoom`, `microsoft_teams`, `google_meet`) |
| `name`, `icon`, `sort_order` | Picker presentation |
| `enabled` | Whether a rep may choose it at all |
| `connected` | Whether a real connector is registered for it |
| `connection_note` | Plain-language reason it is not connected, shown in the UI |

Seeded by `MeetingPlatformSeeder` through `DeallyTenantDatabaseSeeder`. All three ship
`enabled = true, connected = false` with an honest note, because that is the true state — see
[Adding a meeting platform](#adding-a-meeting-platform).

### `call_invitations`

Every attempt to invite a customer, kept rather than overwritten. Resending a call invitation
produces a second row, so the history of what was actually sent to a customer is answerable.

| Column | Meaning |
| --- | --- |
| `channel` | `email` today; present so a second channel is an added column, not a new table |
| `recipient_name`, `recipient_email` | Who it went to |
| `subject`, `body` | The message **verbatim**, exactly as delivered |
| `status` | `pending`, `sent`, `failed` |
| `delivery_error` | The transport error, when delivery failed |
| `sent_at` | When the attempt was made |

### `call_ephemerals`

The live stream. Cards fade after a few seconds so the shelf does not become noise, but every one is
a row first — the fade governs *visibility*, never *persistence*. The review task and the review
page read the `heard` rows back, so a rep can see what the assistant was attending to at each moment
instead of reconstructing it from memory of a scrolling panel.

| Column | Meaning |
| --- | --- |
| `kind` | `heard`, `detected`, `gap`, `asked`, `objection` |
| `label`, `body` | The card as shown |
| `transcript_line_id` | The line it was anchored to, nullable; a deleted line nulls it rather than cascading the card away |
| `source` | `model` or `agent` — who produced the card |
| `occurred_at_ms` | Where it sat in the call, for ordering against the audio |

There is deliberately no `expires_at` column. The fade is a rule about how long a card is *visible*,
and storing an expiry would invite someone to treat "expired" as "disposable" — which is exactly the
mistake that made the stream a record of nothing.

`heard` cards are the assistant's own **paraphrase** of what the customer just said, produced by
`LiveAssistant::normalizeParaphrase()`: at most ~12 words, present tense, never wrapped in quotation
marks. A card labelled as a summary that is actually a verbatim quote is worse than no card, because
the rep reads it as the customer's exact words.

### `call_corrections`

The audit trail for everything a rep corrected — sentiment, readiness, competitor tag, objection tag,
gap classification. Both values are kept:

```text
ai_value         what the model believed
corrected_value  what the agent said was true
note             why
```

`field` is the judgement being corrected, `target_type` / `target_id` point at the record the
correction is about when it is not the call itself (a competitor tag on a deal, a gap
classification), and `corrected_by_user_id` records who made it.

The difference between the two values is the only part of a correction worth learning from.
Overwriting the AI's read would throw away the training signal the whole review flow exists to
produce. `corrected_by_user_id` is a plain indexed column with **no** foreign key, because `users`
lives on the central connection and a tenant table cannot constrain itself to a table in another
database.

### `call_flags`

A deal-status flag raised on a call. Flags exist to *block* something: the review task cannot be
closed while a flag is open, from the modal or from the full review page.

| Column | Meaning |
| --- | --- |
| `kind` | `deal_risk` |
| `status` | `open`, `confirmed`, `adjusted`, `dismissed` |
| `headline`, `rationale` | What the flag says and why |
| `resolution_note` | How it was settled |
| `opportunity_stage_id` | The pipeline stage it touches, when the flag is about a stage |
| `resolved_at` | When it stopped blocking |

`status` is the whole point of the table: a flag that does not block anything is a note.

### `tasks.call_id`

A task now points at the call it is actually about. The previous linkage matched on company name,
which handed every task for a company the most recent call's review — so reviewing an old call meant
reading a new one. Tasks raised before the column existed still fall back to matching by
`linked_company`, and that fallback is documented as such in `TaskController::index()`.

### `calls` columns

| Column | Purpose |
| --- | --- |
| `session_type` | `discovery`, `service_review`, `follow_up` — **reported on, never branched on** |
| `contact_id` | Reference to a real contact record rather than a copied name |
| `meeting_platform`, `meeting_external_id`, `meeting_join_url`, `meeting_start_url` | Meeting identity, only ever populated from a provider response |
| `bot_join_status`, `bot_join_note` | `not_requested`, `unavailable`, `requested`, `joined`, `failed` plus the reason |
| `invitation_status`, `invited_at` | `not_sent`, `sent`, `failed` |
| `ended_reason`, `failure_note` | Why an attempt ended without a conversation |
| `ai_sentiment`, `ai_readiness` | The model's original read, kept beside the effective columns |
| `proposal_intent`, `proposal_intent_note` | Whether a proposal was actually agreed, and in what terms |

New statuses are `failed` (the platform could not join, or the call could not be held at all) and
`no_show` (the meeting happened on the platform's side and nobody attended). `Call::wentUnattended()`
is the single predicate for both.

### A note on validating tenant tables

`contacts`, `calls` and `transcript_lines` live on the tenant connection (`deally`), which is **not**
the application's default connection. An `exists:contacts,id` validation rule resolves against the
default connection and will either throw or check the wrong database. Validate tenant references by
querying the model, as `CallController::store()` does, and scope the check to the parent record as
`CallController::objection()` does.

---

## 2. Scheduling a call

`POST /app/calls` — `deally.calls.store`

```json
{
  "name": "Discovery — Acme",
  "company": "Acme Corp",
  "contact_name": "Jane Okafor",
  "contact_role": "VP Operations",
  "contact_id": 12,
  "session_type": "discovery",
  "meeting_platform": "zoom",
  "invite_email": "jane@acme.example",
  "date": "2026-09-30",
  "assignee_user_id": 3
}
```

What the endpoint does:

1. **Drops an assignee who is not a member of this tenant.** A user id outside the instance is
   silently discarded rather than stored, so a call cannot be handed to somebody with no seat.
2. **Drops a `contact_id` that is not in the tenant's `contacts`.** Checked against the tenant
   connection directly, because the `exists` rule reads the central connection — see
   [A note on validating tenant tables](#a-note-on-validating-tenant-tables).
3. **Drops a platform that is not enabled.** A crafted request cannot attach a call to a platform the
   rep was never offered.
4. **Creates the call**, then asks the connector for a meeting.
5. **Composes the invitation** — after the meeting attempt, never before.

Steps 4 and 5 are the point. The customer must only ever be told about joining details that exist.
When the platform cannot create a meeting, the invitation says the details will follow rather than
including a link that goes nowhere.

The Picker lives in `resources/js/call-setup.js`; the modal and its markup are in
`modules/Calls/resources/views/partials/`.

### Sending the invitation

`POST /app/calls/{call}/invitations` — `deally.calls.invite`

`GET /app/calls/{call}/invitations/preview` — `deally.calls.invite.preview`

`CallInvitationService::compose()` builds subject and body without sending, so an agent can read the
exact copy before it reaches a customer. The preview endpoint returns the same two strings the send
path uses.

`compose()` never includes a join link unless `meeting_join_url` is populated. It says nothing about
a link when the platform has a name but no meeting. The body is stored verbatim on the invitation
row, so what was actually sent stays answerable.

Delivery failures are recorded rather than thrown. A failed send sets the invitation to `failed` with
the transport error, sets the call to `invitation_status = failed`, and returns `502`. A rep reading
the call sees that the customer was never reached — an invitation row that only the invitation screen
can show is not enough.

Default mailer is `log`, so the invitation is really rendered on every send and can be read in
`storage/logs/laravel.log`.

### Unplanned calls

`POST /app/calls/{call}/fail` — `deally.calls.fail`

```json
{ "status": "no_show", "note": "Nobody joined.", "reschedule": "2026-10-02" }
```

A failed or no-show call still needs a next step, or it leaves the pipeline with a dead deal and no
reminder. A reschedule date raises a `Reschedule call — {company}` task.

The no-show is recorded even when the call is rescheduled: `ended_reason` and `failure_note` are
preserved while `status` returns to `scheduled`, because the calendar entry moving is not the same
thing as the missed call never happening.

---

## 3. Meeting platforms and the bot join

### What is shipped, and what is not

**No meeting platform is connected.** The flow, the data model, the picker, the states, the UI and
the tests are all real. The provider calls are not implemented, and this is deliberate.

Writing a Zoom Server-to-Server OAuth app, a Teams bot registration, or a Google service-account
joiner requires credentials this project does not have and cannot simulate. The alternative — a
connector that returns a well-formed-looking `external_id` and `join_url` — is worse than having
nothing, because a rep will believe the customer has the link. So `meeting_join_url` has **no code
path that assembles one**, and the only shipped implementation of
`Deally\Calls\Contracts\MeetingPlatformConnector` is `UnconnectedMeetingPlatform`, which reports what
it cannot do in the call's own words.

### Requesting a bot join

`POST /app/calls/{call}/join` — `deally.calls.join`

`MeetingPlatformManager::requestBotJoinFor()` writes `bot_join_status` and `bot_join_note` on the call
in every case, including the "no platform was chosen" one, so the outcome is on the record rather than
only in a toast. `joined` is reachable only from a provider response.

| `bot_join_status` | Meaning |
| --- | --- |
| `not_requested` | Nobody has asked yet |
| `unavailable` | There is no platform, or the platform has no connector — `bot_join_note` says which |
| `requested` | The platform accepted the request and the bot is joining |
| `joined` | **Only** set when the provider confirmed the bot is in the meeting |
| `failed` | The platform tried and refused; the reason is in `bot_join_note` |

The endpoint returns `409` for `unavailable`, because "could not do it" is not a successful call.

The live-call header renders `MeetingPlatformManager::describe()` — for example
`Zoom · bot not admitted` — so the state is visible while the call is running, not discoverable
afterwards.

### Adding a meeting platform

1. **Add the credentials** to `config/services.php` under `services.meetings.<key>` and to
   `.env.example`. Both already carry the full set for all three platforms — Zoom, Microsoft Teams
   and Google Meet — and both say in a comment that nothing reads them yet.
2. **Implement the contract** in a new service:

   ```php
   namespace Deally\Calls\Services;

   use Deally\Calls\Contracts\MeetingPlatformConnector;
   use Deally\Calls\Models\Call;

   class ZoomMeetingPlatform implements MeetingPlatformConnector
   {
       public function key(): string;
       public function isConnected(): bool;
       public function connectionNote(): string;
       public function createMeeting(Call $call, array $context = []): array;
       public function requestBotJoin(Call $call, array $meeting): array;
   }
   ```

3. **Register it** in `MeetingPlatformManager::register()` — from `CallsServiceProvider::boot()` or a
   container `resolving` callback. Until it is registered, `connectorFor()` falls back to
   `UnconnectedMeetingPlatform` for that key.
4. **Flip `connected`** on the `meeting_platforms` row. Do it from the connector's own
   `isConnected()` rather than by hand, so the flag cannot drift from the credentials.
5. **Run `php artisan config:clear`** after setting the environment variables.

Rules a connector must keep:

- Return `null` identifiers with a `reason` when it cannot complete an operation. Never a
  plausible-looking placeholder.
- `requestBotJoin()` returns `Call::BOT_JOIN_JOINED` only on provider confirmation.
- Throwing is acceptable; returning a fake is not. A thrown exception is caught and surfaced as
  `unavailable` with the reason.
- Credentials are read from config and never reach the browser.

---

## 4. The live call

`GET /app/calls/{call}/live` — `deally.calls.live`

The audio capture and analysis pipeline is in [ai-llm-integration.md](ai-llm-integration.md). This
section covers the surfaces around it.

### The customer panel is read-only and fixed

`CustomerBrief` is read once when the page loads and does not change during the call. It is context,
not a second live surface competing with the AI for attention.

The previous version of this panel was hardcoded. It told every rep the customer's sentiment was
trending positive, that the budget had come up four times, and that a spec sheet was overdue — for
every deal, invented, with no connection to any record. A rep reading that during a live call had no
way to tell fiction from fact, which makes it worse than an empty panel. Everything now is real:
contact and role, deal stage and value, proposed packages, a sentiment trend computed from actual
stored calls, unresolved commitments from `knowledge_gaps`, and the call history. Every section says
so plainly when it has nothing, and a single previous call is never called a trend.

### Agent controls live in the AI panel only

The Findings Panel is strictly a viewing surface. Every control the agent has — ask, flag, correct,
clear — is in the AI panel. A control that appears in two places is a control that will be found
somewhere it does not belong.

### The hero card cannot be dismissed without a choice

The prominent card is dismissed by answering it, not by closing it. It is distinguished from the
others by structure — a rule above and the choice in the body — not by colour, because a colour is
the one thing a rep with a colour vision deficiency cannot use.

### Colour

Three colours in normal use: green, amber, violet. Blue is reserved for platform primary, so
"important" never means "blue" and the palette stays consistent with the rest of the product.

### Card roles are distinguished by edge treatment, not colour alone

A solid edge is a **say**, a dashed edge an **ask**, no accent a **reference**, a dotted edge
**waiting**, and a red left border an **objection**. The shelf also scrolls, and `min-height` is 220px
so a card is never clipped mid-sentence.

### Ephemerals

Every card in the live stream is written to `call_ephemerals` before the response is returned, and
returned in the response's `ephemerals` array. "Clear the findings" is display-only: it hides the
DOM, and the rows stay for the review task and the review page, which both read them back.

The client never invents a card body. `clipOwnWords()` in `resources/js/live-call.js` only ever
shortens the rep's **own typed input** for the chat echo; anything attributed to the customer comes
from the model.

---

## 5. After the call

### The post-call summary is deliberately sparse

`GET /app/calls/{call}/summary` — `deally.calls.summary`

The summary is a handover, not the review. It carries: the call's real readiness, open deal-status
flags, whether a proposal was agreed and in what terms, and a **View task** action.

The **Create Proposal** button appears only when `proposal_intent` is true. An unconditional button
on every call is a suggestion the rep has to evaluate and discard rather than an action, and
`proposal_intent` is set by the analysis, not by a rep — so it reflects what was actually agreed.
Calling the endpoint without it returns `409 no_proposal_intent`.

`POST /app/calls/{call}/proposal` — `deally.calls.proposal` — raises a `Draft proposal — {company}`
task carrying `proposal_intent_note` and the call date, so whoever writes it knows the terms that
were agreed rather than starting from the recording.

### The review task is the real review

Ending a call raises a `Review Call — {company}` task with `call_id` set. The tasks list opens it in a
modal, so a rep can work through their queue without losing their place.

`GET /app/tasks/{task}` — `deally.tasks.show` — renders `review-task-modal.blade.php`, which carries:

- **What DeAlly heard** — the `heard` ephemerals, read back from the rows the live stream was
  written to, in the order it heard them. Read-only: there is nothing to correct in a paraphrase,
  and an editable copy of one would invite a rep to rewrite what the customer said.
- **Objections** — the ones the model detected and the ones the rep added during review, kept apart,
  because the second kind is the expensive one: it is the model missing something.
- **Still outstanding** — the call's unresolved `knowledge_gap` entries: the questions it raised that
  the knowledge base could not answer, and the commitments it made that nobody has answered yet.
  Read from the gap log rather than the transcript, because a promise is only useful if it is
  tracked; a sentence in a transcript is not a follow-up anyone will be reminded about.
- **How DeAlly read it** — sentiment and readiness, each correctable, and each keeping the AI's
  original read in `ai_sentiment` / `ai_readiness` with the difference in `call_corrections`.
- **Corrections made** — the log of everything already corrected, so a second reader can see what
  changed and why.
- **Deal status** — every open flag, with the reason each one is blocking, and a way to settle each.

A task with no call attached says so and offers to close it as an ordinary reminder, rather than
rendering an empty review that looks like there was nothing to review.

`CallReviewBrief` assembles the payload for every task in one batched set of queries rather than one
per row. Every block states plainly when it is empty, so an empty objection log is distinguishable
from a review that did not load.

### The task cannot be closed while a flag is open

`Task::unresolvedFlags()` and `Task::blockedReason()` are the single source of truth, used by both the
modal and the list. `TaskController::toggle()` refuses the close, and the list renders the row as
**Needs a decision** with the toggle disabled and the reason in its tooltip. Refusing from both
surfaces matters: closing from the modal is the path a rep takes first.

`POST /app/calls/{call}/flags` and `POST /app/calls/{call}/flags/{flag}/resolve` —
`deally.calls.flags.store`, `deally.calls.flags.resolve` — raise and settle a flag. Resolving asks
for a note, because "resolved" with no reason is indistinguishable from "ignored".

### Correcting the AI

`POST /app/calls/{call}/corrections` — `deally.calls.correct`

`field` is one of `sentiment`, `readiness`, `competitor_tag`, `objection_tag`,
`gap_classification`. The endpoint writes the effective value onto the call, keeps the model's read
in the `ai_*` column, and records the difference as a `call_corrections` row.

### Objections the model missed

`POST /app/calls/{call}/objections` — `deally.calls.objections.store`

An agent hearing resistance the model did not catch is the highest-value correction in the system, so
it is recorded as both a `knowledge_gap` and an `objection` ephemeral — it belongs in the objection
log and in the stream alike. The optional `transcript_line_id` is scoped to the call in the URL; a
line belonging to another call returns `422 line_not_in_call` rather than being quietly unattached, so
the rep is never left believing the objection was pinned to the moment they said it was.

### The full review page

`GET /app/calls/{call}/review` — `deally.calls.review`

Replay, the whole conversation with findings grouped onto the line that triggered them, correctable
chips, the heard record, the flag UI, the objection log, the correction log, the questions asked and
the conditional proposal button. Audio, per-line playback and the recording-retention rules are in
[ai-llm-integration.md](ai-llm-integration.md#replay-and-the-full-conversation).

The transcript download carries the AI's half of the exchange too. Shipping only the questions meant
the one artefact a rep was likely to forward or keep held no record of what they were advised.

### The call detail modal

`GET /app/calls/{call}/detail` — `deally.calls.detail`

A fragment, not a page. The calls list and the tasks list both open modals through one reusable shell:
`data-modal-url` fetches the fragment into `modal-call-detail` via `openRemoteModal()` in
`resources/js/modals.js`. Rendering a transcript per list row would mean a page that cannot be
loaded.

---

## 6. The Calls list

`GET /app/calls` — `deally.calls.index`

Each row opens the call detail modal rather than navigating. The row still carries the full call, the
status, sentiment, duration and next action. Filters and search are unchanged.

---

## 7. Security and operational notes

- Provider credentials and platform credentials stay in server configuration. None of them are
  rendered into Blade, JSON or browser storage.
- Every route requires authentication, a valid CSRF token, the `deally.calls.manage` permission for
  anything that writes and `deally.calls.view` for anything that reads, plus seat access to the
  individual record — a rep with the permission still cannot open another owner's call.
- `deally.calls.recording` streams a window from the **private** disk and is bound to the call in
  the URL, so a recording id from another call does not resolve.
- The invitation body is stored verbatim. That is a record of what a customer was told, and it means
  the stored copy contains whatever the rep typed — do not put credentials in a call subject.
- Recording retention is deliberately not implemented. A call's audio lives as long as the call row;
  deleting a recording is not something the app should do unasked. See the storage cost note in
  [ai-llm-integration.md](ai-llm-integration.md#the-audio-is-kept).

---

## 8. Tests

`tests/Feature/CallInitiationAndReviewTest.php` covers this whole lifecycle (53 tests), and a large
part of it asserts that the system does *not* invent things: no join URL without a provider, no
`joined` bot without a confirmation, no invented customer-panel signals, no paraphrase wrapped in
quotation marks, no foreign transcript line on an objection, no "sent" invitation when delivery
failed, and a review task that refuses to close while a flag is open.

It also guards the reverse failure — a record nothing reads. The `heard` cards have to appear in both
the review task and the review page, other ephemeral kinds must not leak into that list, and an empty
list must say so rather than rendering as blank space.

```bash
php artisan test tests/Feature/CallInitiationAndReviewTest.php
php artisan test --compact
```

---

© 2026 Wyzone Labs. All rights reserved.
