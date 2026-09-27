<?php

namespace Deally\Calls\Http\Controllers;

use Deally\Calls\Contracts\CallAssistant;
use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallFinding;
use Deally\Calls\Models\CallQuery;
use Deally\Calls\Models\CallRecording;
use Deally\Calls\Models\TranscriptLine;
use Deally\Calls\Services\AssistantFactory;
use Deally\Calls\Services\AssistantProviderException;
use Deally\Calls\Services\DummyAssistant;
use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Models\User;
use Deally\Core\Services\ActivityLogger;
use Deally\Core\Services\Notifier;
use Deally\Proposals\Models\KnowledgeGap;
use Deally\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CallController extends Controller
{
    /**
     * Whether the analysis attempt in the current request reached the provider.
     * Kept per request so a stale failure cannot make a later healthy, empty
     * result look broken.
     */
    protected bool $analysisFailed = false;

    public function __construct(protected AssistantFactory $assistants) {}

    public function index()
    {
        $this->authorizeDeally('deally.calls.view');

        $calls = $this->scopeToSeat(Call::query())->with('opportunity')->orderByDesc('date')->get();

        $ownerIds = $calls->pluck('owner_user_id')->filter();
        $ownerNames = $ownerIds->isNotEmpty()
            ? User::whereIn('id', $ownerIds)->pluck('name', 'id')
            : collect();

        return view('calls::pages.calls', [
            'calls' => $calls,
            'assignees' => $this->tenantMembershipOptions(),
            'suggestedOwners' => $this->suggestedOwnerOptions(),
            'ownerNames' => $ownerNames,
        ]);
    }

    public function show(Call $call)
    {
        $this->authorizeDeally('deally.calls.view');
        $this->authorizeSeatRecord($call);

        $call->load('transcriptLines');

        return view('calls::pages.call-detail', ['call' => $call]);
    }

    public function live(Call $call)
    {
        $this->authorizeDeally('deally.calls.view');
        $this->authorizeSeatRecord($call);

        $call->load('opportunity');

        $tasks = $this->scopeToSeat(Task::query())
            ->where('linked_company', $call->company)
            ->orderByRaw("CASE WHEN status = 'closed' THEN 1 ELSE 0 END")
            ->orderBy('due_at')
            ->limit(6)
            ->get();

        // The shelf only ever shows the newest cards, so a long call cannot
        // flood the panel. Every finding stays persisted for review.
        $findingsLimit = max(1, (int) config('services.live_ai.shelf_limit', 10));

        return view('calls::pages.call-live', [
            'call' => $call,
            'tasks' => $tasks,
            'findings' => $call->findings()->latest('id')->limit($findingsLimit)->get(),
            'findingsTotal' => $call->findings()->count(),
            'findingsLimit' => $findingsLimit,
            'assistantName' => $this->assistants->name(),
            'demoMode' => $this->assistants->usingDummy(),
        ]);
    }

    public function summary(Call $call)
    {
        $this->authorizeDeally('deally.calls.view');
        $this->authorizeSeatRecord($call);

        $reviewTask = $this->scopeToSeat(Task::query())
            ->where('title', "Review Call — {$call->company}")
            ->latest('id')
            ->first();

        return view('calls::pages.call-summary', ['call' => $call, 'reviewTask' => $reviewTask]);
    }

    public function review(Call $call)
    {
        $this->authorizeDeally('deally.calls.view');
        $this->authorizeSeatRecord($call);

        $call->load('transcriptLines');

        /* The review is meant to be the whole conversation, which means the AI's
           half of it too. Findings are grouped onto the line that triggered
           them so the transcript reads as an exchange rather than as a list of
           questions with a separate, unrelated report next to it. */
        $findingsByLine = $call->findings()
            ->whereNotNull('transcript_line_id')
            ->orderBy('id')
            ->get()
            ->groupBy('transcript_line_id');

        $findings = $call->findings()->orderBy('id')->get();

        $recordings = $call->recordings()->get();

        // Keep pre-existing gaps that were only linked by source string.
        $gaps = KnowledgeGap::query()
            ->where(function ($query) use ($call): void {
                $query->where('call_id', $call->id)
                    ->orWhere('source', 'LIKE', "%{$call->company}%");
            })
            ->orderByDesc('id')
            ->get();

        return view('calls::pages.call-review', [
            'call' => $call,
            'gaps' => $gaps,
            'findings' => $findings,
            'findingsByLine' => $findingsByLine,
            'queries' => $call->queries()->get(),
            'recordings' => $recordings,
        ]);
    }

    public function downloadTranscript(Call $call)
    {
        $this->authorizeDeally('deally.calls.view');
        $this->authorizeSeatRecord($call);

        $call->load('transcriptLines');

        /* The download is a copy of the review, so it carries the assistant's
           half of the exchange too. Shipping the questions alone meant the one
           artefact a rep was likely to forward or keep held no record of what
           they were advised. */
        $content = view('calls::transcript-download', [
            'call' => $call,
            'lines' => $call->transcriptLines,
            'findings' => $call->findings()
                ->whereNotNull('transcript_line_id')
                ->orderBy('id')
                ->get()
                ->groupBy('transcript_line_id'),
            'queries' => $call->queries()->get(),
        ]);

        $filename = 'transcript-'.Str::slug($call->company).'-'.$call->date->format('Y-m-d').'.txt';

        return response()->streamDownload(
            function () use ($content): void {
                echo (string) $content;
            },
            $filename,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }

    public function store(Request $request)
    {
        $this->authorizeDeally('deally.calls.manage');

        $data = $request->validate([
            'name' => ['required', 'string'],
            'company' => ['required', 'string'],
            'contact_name' => ['nullable', 'string'],
            'contact_role' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'assignee_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $assigneeId = $data['assignee_user_id'] ?? null;

        if ($assigneeId !== null && ! in_array((string) $assigneeId, $this->tenantMemberUserIds(), true)) {
            $assigneeId = null;
        }

        $attributes = $data;
        unset($attributes['assignee_user_id']);

        $call = Call::create([...$attributes,
            'duration' => '0m',
            'sentiment' => 'neutral',
            'status' => Call::STATUS_SCHEDULED,
            'date' => $data['date'] ?? now()->toDateString(),
            'owner_user_id' => $assigneeId ?: auth()->id(),
        ]);

        return redirect()->route('deally.calls.live', $call);
    }

    /**
     * Open a capture session. Idempotent: re-entering the live call keeps the
     * original start time so the recorded duration stays honest.
     */
    public function liveStart(Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        if ($call->isCompleted()) {
            return response()->json(['ok' => false, 'error' => 'call_completed'], 409);
        }

        $call->forceFill([
            'status' => Call::STATUS_IN_PROGRESS,
            'started_at' => $call->started_at ?? now(),
            'ai_provider' => $this->assistant()->name(),
        ])->save();

        return response()->json([
            'ok' => true,
            'status' => $call->status,
            'provider' => $call->ai_provider,
            'started_at' => $call->started_at?->toIso8601String(),
        ]);
    }

    public function end(Request $request, Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        $data = $request->validate([
            'duration' => ['nullable', 'string'],
            'sentiment' => ['nullable', 'string', 'in:positive,neutral,negative'],
            'notes' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
        ]);

        $data['sentiment'] = $data['sentiment'] ?: 'neutral';
        $data['status'] = Call::STATUS_COMPLETED;
        $data['ended_at'] = $call->ended_at ?? now();

        $call->update($data);

        $this->seedDemoTranscript($call);

        if (blank($data['summary'] ?? null)) {
            $call->update(['summary' => ($call->contact_name ?: 'The customer')
                .' discussed needs for '.$call->company.'. Overall sentiment was '
                .($call->sentiment ?: 'neutral').'; the follow-ups and guidance were captured for review.']);
        }

        Task::query()->firstOrCreate(
            ['title' => "Review Call — {$call->company}", 'linked_company' => $call->company, 'owner_user_id' => $call->owner_user_id ?: auth()->id()],
            ['due_at' => now()->addHours(24), 'status' => 'todo'],
        );

        $logger = app(ActivityLogger::class);
        $logger->log('call.ended', "Ended call '{$call->name}' with {$call->company} ({$call->sentiment}).");

        $notifier = app(Notifier::class);
        $notifier->notify(
            auth()->user(),
            'Call complete',
            "A review task for {$call->company} was created and is due within 24 hours.",
            'call',
            route('deally.tasks.index')
        );

        return redirect()->route('deally.calls.summary', $call);
    }

    /**
     * Seed the scripted conversation so the review and summary pages have
     * something to show when the demo driver was used and no capture session
     * was ever opened. A call that was actually recorded is never overwritten.
     */
    protected function seedDemoTranscript(Call $call): void
    {
        $assistant = $this->assistant();

        if (! $assistant instanceof DummyAssistant) {
            return;
        }

        if ($call->started_at !== null || $call->transcriptLines()->exists()) {
            return;
        }

        $sequence = 0;

        foreach ($assistant->conversation() as $exchange) {
            foreach ([false, true] as $isAgent) {
                TranscriptLine::create([
                    'call_id' => $call->id,
                    'speaker' => $isAgent ? TranscriptLine::SPEAKER_AGENT : TranscriptLine::SPEAKER_CUSTOMER,
                    'is_agent' => $isAgent,
                    'text' => $exchange[(int) $isAgent],
                    'sequence' => ++$sequence,
                    'provider' => $assistant->name(),
                ]);
            }
        }
    }

    public function liveTranscribe(Request $request, Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        if ($call->isCompleted()) {
            return response()->json(['ok' => false, 'error' => 'call_completed'], 409);
        }

        $data = $request->validate([
            'audio' => ['required', 'file', 'max:20480'],
            'speaker' => ['required', 'string', 'in:'.TranscriptLine::SPEAKER_AGENT.','.TranscriptLine::SPEAKER_CUSTOMER],
            'chunk_id' => ['required', 'string', 'max:64'],
            'client_sequence' => ['required', 'integer', 'min:0'],
            'started_at_ms' => ['nullable', 'integer', 'min:0'],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'is_final' => ['sometimes', 'boolean'],
        ]);

        // A retried upload must not produce a second transcript line.
        $existing = $call->transcriptLines()->where('client_chunk_id', $data['chunk_id'])->first();

        /* The window is kept before the provider is asked anything, and whatever
           that produces. A provider outage is exactly when a rep most wants the
           audio back, and the client already promises them that "the call is
           still being recorded". */
        $this->storeRecording($call, $data['audio'], $data);

        if ($existing !== null) {
            // Do not re-run analysis: the original response already carried it.
            return $this->transcribeResponse($call, $existing, [], duplicate: true);
        }

        $this->analysisFailed = false;

        $assistant = $this->assistant();

        try {
            $transcript = $assistant->transcribe(
                $data['audio']->get(),
                $data['audio']->getClientOriginalName() ?: 'chunk.webm'
            );
        } catch (AssistantProviderException $exception) {
            $this->logProviderFailure($call, 'transcription', $exception);

            return response()->json([
                'ok' => false,
                'error' => 'transcription_unavailable',
                'retryable' => $exception->retryable,
            ], 503);
        }

        if ($transcript === null) {
            // Silence is a valid outcome, not a failure: the client just moves on.
            return response()->json(['ok' => true, 'silent' => true, 'transcript' => null, 'findings' => []]);
        }

        $isAgent = $data['speaker'] === TranscriptLine::SPEAKER_AGENT;

        $line = DB::connection($call->getConnectionName())->transaction(
            fn (): TranscriptLine => $this->appendTranscriptLine($call, $data, $transcript, $isAgent, $assistant->name())
        );

        /* Analysis runs on every chunk, whichever side spoke. Gating it on
           customer lines meant a rep testing into their own microphone — with no
           meeting shared — got a transcript and an always-empty shelf, which reads
           as a broken provider rather than as a missing second stream. The
           window still contains both speakers, and the prompt still tells the
           model to report what the newest line changes for the deal. */
        $findings = $this->analyzeLatest($call);

        /* A provider failure during analysis must not fail the upload — the audio
           is already stored and the next window can still produce cards. But it
           is reported, because an empty `findings` array is otherwise
           indistinguishable from "the model had nothing to say", and a run of
           those looks exactly like a broken provider. */
        return $this->transcribeResponse($call, $line, $findings, analysisFailed: $this->analysisFailed);
    }

    /**
     * Keep the captured window on disk so the call can be replayed later.
     *
     * The audio is written to the private disk, never to a public path, and the
     * row is keyed on the chunk id so a retried upload replaces its own window
     * instead of duplicating it. A storage failure must not cost the rep their
     * transcript, so it is logged and swallowed.
     *
     * @param  array<string, mixed>  $data
     */
    protected function storeRecording(Call $call, UploadedFile $audio, array $data): void
    {
        $chunkId = (string) $data['chunk_id'];
        $source = (string) $data['speaker'];
        $sequence = (int) $data['client_sequence'];
        $extension = strtolower($audio->getClientOriginalExtension() ?: 'webm');

        /* These windows come from a stream with no video track — the client
           strips it before handing it to MediaRecorder — but some browsers
           still label the container `video/webm`. An <audio> element refuses
           that outright, so replay would break on a codec the file actually
           contains. The family is corrected here rather than leaving it to
           whatever the browser happened to report. */
        $mime = (string) $audio->getClientMimeType();
        $mime = str_starts_with($mime, 'video/') ? 'audio/'.substr($mime, 6) : $mime;

        $path = 'calls/'.$call->id.'/'.$source.'-'.$sequence.'-'.substr(hash('sha256', $chunkId), 0, 8).'.'.$extension;

        try {
            $stored = Storage::disk('local')->putFileAs(
                dirname($path),
                $audio,
                basename($path)
            );
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        if ($stored === false) {
            return;
        }

        CallRecording::query()->updateOrCreate(
            ['call_id' => $call->id, 'client_chunk_id' => $chunkId],
            [
                'source' => $source,
                'client_sequence' => $sequence,
                'path' => $stored,
                'mime' => $mime ?: null,
                'started_at_ms' => $data['started_at_ms'] ?? null,
                'duration_ms' => $data['duration_ms'] ?? null,
                'bytes' => (int) $audio->getSize(),
            ]
        );
    }

    /**
     * Stream one captured window back to the review player.
     */
    public function recording(Call $call, CallRecording $recording)
    {
        $this->authorizeDeally('deally.calls.view');
        $this->authorizeSeatRecord($call);

        // Scoped model binding resolves by id alone, so a window from another
        // call must not be reachable through this call's URL.
        abort_unless($recording->call_id === $call->id, 404);

        abort_unless(Storage::disk('local')->exists($recording->path), 404);

        return Storage::disk('local')->response(
            $recording->path,
            // The stored path carries directories, which cannot be used as a
            // Content-Disposition filename.
            basename($recording->path),
            ['Content-Type' => $recording->mime ?: 'audio/webm', 'Accept-Ranges' => 'bytes'],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function appendTranscriptLine(
        Call $call,
        array $data,
        string $transcript,
        bool $isAgent,
        string $provider
    ): TranscriptLine {
        // Serialise the sequence allocation so two in-flight chunks cannot
        // claim the same position.
        $locked = Call::query()->whereKey($call->getKey())->lockForUpdate()->firstOrFail();

        $sequence = ((int) $locked->transcriptLines()->max('sequence')) + 1;

        return $locked->transcriptLines()->create([
            'speaker' => $isAgent ? TranscriptLine::SPEAKER_AGENT : TranscriptLine::SPEAKER_CUSTOMER,
            'is_agent' => $isAgent,
            'text' => $transcript,
            'sequence' => $sequence,
            'client_chunk_id' => $data['chunk_id'],
            'client_sequence' => $data['client_sequence'],
            'started_at_ms' => $data['started_at_ms'] ?? null,
            'duration_ms' => $data['duration_ms'] ?? null,
            'is_final' => (bool) ($data['is_final'] ?? false),
            'provider' => $provider,
        ]);
    }

    /**
     * @param  array<int, CallFinding>  $findings
     */
    protected function transcribeResponse(
        Call $call,
        TranscriptLine $line,
        array $findings,
        bool $duplicate = false,
        bool $analysisFailed = false
    ): JsonResponse {
        $cards = array_map(
            fn (CallFinding $finding): array => $finding->toCard(),
            $findings
        );

        return response()->json([
            'ok' => true,
            'duplicate' => $duplicate,
            /* Separates "the AI had nothing to say" from "the AI could not be
               reached". There is deliberately no `retryable` here: the chunk
               upload succeeded, so the browser must not re-send the audio. */
            'analysis_failed' => $analysisFailed,
            'transcript' => $line->text,
            'line' => [
                'id' => $line->id,
                'speaker' => $line->speaker,
                'is_agent' => $line->is_agent,
                'sequence' => $line->sequence,
            ],
            'findings' => $cards,
            // Retained for the existing findings shelf renderer.
            'suggestions' => $cards,
        ]);
    }

    /**
     * Run structured analysis over the rolling transcript window, persisting
     * every finding. Analysis is throttled and never fails the chunk upload.
     *
     * @return array<int, CallFinding>
     */
    protected function analyzeLatest(Call $call): array
    {
        if (! $this->analysisDue($call)) {
            return [];
        }

        $window = max(1, (int) config('services.live_ai.analysis_window', 8));

        /** @var Collection<int, TranscriptLine> $lines */
        $lines = $call->transcriptLines()
            ->latest('sequence')
            ->limit($window)
            ->get()
            ->reverse()
            ->values();

        $payload = $lines
            ->map(fn (TranscriptLine $line): array => [
                'id' => $line->id,
                'speaker' => $line->speaker,
                'is_agent' => $line->is_agent,
                'text' => $line->text,
            ])
            ->all();

        try {
            $analysis = $this->assistant()->analyze($call, $payload, $this->alreadyReported($call));
        } catch (AssistantProviderException $exception) {
            $this->logProviderFailure($call, 'analysis', $exception);
            $this->analysisFailed = true;

            return [];
        }

        $this->analysisFailed = false;

        // Record the attempt even when it produced nothing, so a provider
        // outage cannot turn every chunk into a fresh request.
        $call->forceFill(['last_analyzed_at' => now()])->save();

        return $this->persistFindings($call, $analysis);
    }

    /**
     * Bodies already on the shelf, oldest first. Repeating them makes the shelf
     * look frozen while the conversation moves on, so the model is shown what it
     * has already reported.
     *
     * @return array<int, string>
     */
    protected function alreadyReported(Call $call): array
    {
        $limit = max(1, (int) config('services.live_ai.reported_findings', 6));

        return $call->findings()
            ->latest('id')
            ->limit($limit)
            ->pluck('body')
            ->filter()
            ->map(fn (string $body): string => mb_substr(trim($body), 0, 160))
            ->reverse()
            ->values()
            ->all();
    }

    protected function analysisDue(Call $call): bool
    {
        $interval = max(0, (int) config('services.live_ai.analysis_min_interval', 10));

        if ($interval === 0) {
            return true;
        }

        return $call->last_analyzed_at === null
            || $call->last_analyzed_at->copy()->addSeconds($interval)->isPast();
    }

    /**
     * @param  array{signals?: array<int, array<string, mixed>>, recommendations?: array<int, array<string, mixed>>}  $analysis
     * @return array<int, CallFinding>
     */
    protected function persistFindings(Call $call, array $analysis): array
    {
        $provider = $this->assistant()->name();
        $latest = $call->transcriptLines()->latest('sequence')->first();
        $findings = [];

        $rows = collect($analysis['signals'] ?? [])
            ->map(fn (array $signal): array => [
                'type' => CallFinding::TYPE_SIGNAL,
                'kind' => (string) ($signal['kind'] ?? ''),
                'label' => (string) ($signal['kind'] ?? ''),
                'body' => (string) ($signal['text'] ?? ''),
                'package' => trim((string) ($signal['quote'] ?? '')),
                'source' => 'AI · detected live',
                'confidence' => $signal['confidence'] ?? null,
                'transcript_line_id' => $signal['source_line'] ?? null,
            ])
            ->concat(collect($analysis['recommendations'] ?? [])
                ->map(fn (array $recommendation): array => [
                    'type' => CallFinding::TYPE_RECOMMENDATION,
                    'kind' => (string) ($recommendation['role'] ?? ''),
                    'label' => (string) ($recommendation['label'] ?? ''),
                    'body' => (string) ($recommendation['body'] ?? ''),
                    'package' => (string) ($recommendation['package'] ?? ''),
                    'source' => (string) ($recommendation['source'] ?? ''),
                    'confidence' => $recommendation['confidence'] ?? null,
                    'transcript_line_id' => $recommendation['source_line'] ?? null,
                ]))
            ->filter(fn (array $row): bool => $row['kind'] !== '' && trim($row['body']) !== '');

        foreach ($rows as $row) {
            // Anchor to a line we actually stored, so findings never point at
            // a transcript id the provider invented.
            $transcriptLineId = $row['transcript_line_id'] ?: $latest?->id;
            $dedupeKey = hash('sha256', $row['type'].'|'.$row['kind'].'|'.mb_strtolower(trim($row['body'])));

            $finding = CallFinding::query()->firstOrCreate(
                ['call_id' => $call->id, 'dedupe_key' => $dedupeKey],
                [
                    'transcript_line_id' => $transcriptLineId,
                    'type' => $row['type'],
                    'kind' => $row['kind'],
                    'label' => $row['label'],
                    'body' => trim($row['body']),
                    'package' => $row['package'] ?: null,
                    'source' => $row['source'] ?: null,
                    'confidence' => $row['confidence'],
                    'provider' => $provider,
                    'status' => CallFinding::STATUS_NEW,
                ]
            );

            if ($finding->wasRecentlyCreated && $row['type'] === CallFinding::TYPE_SIGNAL && $row['kind'] === 'knowledge_gap') {
                $this->recordGap($call, [
                    'type' => 'gap',
                    'text' => $finding->body,
                    'transcript_line_id' => $transcriptLineId,
                    'call_finding_id' => $finding->id,
                ]);
            }

            $findings[] = $finding;
        }

        return $findings;
    }

    public function liveFindingFeedback(Request $request, Call $call, CallFinding $finding)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        abort_unless($finding->call_id === $call->id, 404);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:helpful,unhelpful'],
        ]);

        $finding->update(['status' => $data['status']]);

        if ($data['status'] === CallFinding::STATUS_UNHELPFUL) {
            $this->recordGap($call, [
                'type' => $finding->isSignal() ? $finding->kind : 'correction',
                'text' => $finding->body,
                'transcript_line_id' => $finding->transcript_line_id,
                'call_finding_id' => $finding->id,
            ]);
        }

        return response()->json(['ok' => true, 'status' => $finding->status]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function recordGap(Call $call, array $attributes): KnowledgeGap
    {
        $payload = [
            'type' => $attributes['type'],
            'text' => $attributes['text'],
            'source' => $this->gapSource($call),
            'status' => 'pending',
            'call_id' => $call->id,
            'transcript_line_id' => $attributes['transcript_line_id'] ?? null,
        ];

        // Only dedupe on a finding id: a null lookup would match every
        // unlinked gap in the tenant and silently reuse one of them.
        if (blank($attributes['call_finding_id'] ?? null)) {
            return KnowledgeGap::query()->create($payload);
        }

        return KnowledgeGap::query()->firstOrCreate(
            ['call_finding_id' => $attributes['call_finding_id']],
            $payload
        );
    }

    public function liveQuery(Request $request, Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:1000'],
        ]);

        $assistant = $this->assistant();
        $text = trim((string) $data['text']);

        if ($request->boolean('objection')) {
            $cards = $this->normalizeCards($assistant->suggest($call, $text));

            if ($text === '') {
                $this->recordGap($call, ['type' => 'objection', 'text' => 'Objection raised — no further detail']);

                return response()->json(['ok' => true, 'answer' => null, 'cards' => []]);
            }

            $objection = collect($cards)->first(fn (array $card): bool => $card['role'] === 'objection');

            if ($objection === null || $objection['subtype'] === 'ask') {
                $this->recordGap($call, ['type' => 'objection', 'text' => "Objection raised: {$text}"]);
            }

            $this->recordQuery($call, $text, $objection['body'] ?? null, $cards);

            return response()->json(['ok' => true, 'answer' => $objection['body'] ?? null, 'cards' => $cards]);
        }

        $answer = $assistant->answer($call, $text);

        if ($answer === null) {
            app(ActivityLogger::class)->log(
                'call.query_failed',
                "Live query failed during the {$call->company} call.",
                ['call_id' => $call->id, 'text' => $text],
                'error',
                $call
            );

            return response()->json(['ok' => false, 'error' => 'query_failed'], 422);
        }

        $cards = $this->normalizeCards($assistant->suggest($call, $text));

        $this->recordQuery($call, $text, $answer, $cards);

        return response()->json([
            'ok' => true,
            'answer' => $answer,
            'cards' => $cards,
        ]);
    }

    /**
     * Persist what the rep asked the assistant mid-call.
     *
     * The live panel shows this as a chat, but a chat the rep cannot look back
     * at is the reason the review page used to hold only the customer's
     * questions. Losing the answers means the one place the rep goes afterwards
     * cannot tell them what they were told.
     *
     * @param  array<int, array<string, mixed>>  $cards
     */
    protected function recordQuery(Call $call, string $prompt, ?string $answer, array $cards): void
    {
        CallQuery::query()->create([
            'call_id' => $call->id,
            'prompt' => $prompt,
            'answer' => $answer,
            'cards' => $cards === [] ? null : $cards,
            'provider' => $this->assistant()->name(),
        ]);
    }

    public function ask(Request $request)
    {
        $this->authorizeDeally('deally.calls.view');

        $data = $request->validate([
            'text' => ['required', 'string', 'max:1000'],
        ]);

        $text = trim((string) $data['text']);
        $answer = $this->assistant()->answerGlobal($text);

        if ($answer === null) {
            app(ActivityLogger::class)->log(
                'query.failed',
                'Global ask returned no answer.',
                ['text' => $text],
                'error'
            );

            return response()->json(['ok' => false, 'error' => 'query_failed'], 422);
        }

        return response()->json(['ok' => true, 'answer' => $answer]);
    }

    protected function assistant(): CallAssistant
    {
        return $this->assistants->make();
    }

    protected function logProviderFailure(Call $call, string $context, AssistantProviderException $exception): void
    {
        app(ActivityLogger::class)->log(
            'call.provider_failed',
            "Live {$context} failed for {$call->company}.",
            [
                'call_id' => $call->id,
                'context' => $context,
                'provider' => $this->assistants->name(),
                'status' => $exception->status,
                'retryable' => $exception->retryable,
                /* A null status means the request never got an HTTP response, so
                   the reason lives in the message. Without it a TLS trust-store
                   failure and an unreachable host are indistinguishable. */
                'reason' => $exception->getMessage(),
            ],
            'error',
            $call
        );
    }

    protected function gapSource(Call $call): string
    {
        return $call->name.' · '.$call->company.' · '.$call->date->format('M d');
    }

    public function transcript(Request $request, Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        $data = $request->validate([
            'lines' => ['present', 'array'],
            'lines.*.speaker' => ['required', 'string'],
            'lines.*.is_agent' => ['sometimes', 'boolean'],
            'lines.*.text' => ['required', 'string'],
            'lines.*.linked_type' => ['nullable', 'string'],
            'lines.*.linked_text' => ['nullable', 'string'],
        ]);

        $call->transcriptLines()->delete();

        foreach ($data['lines'] as $index => $line) {
            TranscriptLine::create($line + [
                'call_id' => $call->id,
                'sequence' => $index,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function flag(Request $request, Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        $data = $request->validate([
            'text' => ['required', 'string'],
            'type' => ['nullable', 'string'],
        ]);

        $this->recordGap($call, [
            'type' => $data['type'] ?? 'correction',
            'text' => $data['text'],
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<int, string|array<string, string>>  $cards
     * @return array<int, array<string, string>>
     */
    protected function normalizeCards(array $cards): array
    {
        return collect($cards)
            ->map(function (string|array $card): array {
                if (is_string($card)) {
                    return [
                        'role' => 'say',
                        'label' => 'Say this',
                        'subtype' => '',
                        'confidence' => 'AI · live',
                        'body' => $card,
                        'package' => 'Suggested reply',
                        'source' => 'AI · live transcript',
                    ];
                }

                return $card;
            })
            ->values()
            ->all();
    }
}
