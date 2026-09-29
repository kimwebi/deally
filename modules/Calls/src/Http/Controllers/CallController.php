<?php

namespace Deally\Calls\Http\Controllers;

use Deally\Calls\Contracts\CallAssistant;
use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallCorrection;
use Deally\Calls\Models\CallEphemeral;
use Deally\Calls\Models\CallFinding;
use Deally\Calls\Models\CallFlag;
use Deally\Calls\Models\CallQuery;
use Deally\Calls\Models\CallRecording;
use Deally\Calls\Models\TranscriptLine;
use Deally\Calls\Services\AssistantFactory;
use Deally\Calls\Services\AssistantProviderException;
use Deally\Calls\Services\CallInvitationService;
use Deally\Calls\Services\CustomerBrief;
use Deally\Calls\Services\DummyAssistant;
use Deally\Calls\Services\MeetingPlatformManager;
use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Models\User;
use Deally\Core\Services\ActivityLogger;
use Deally\Core\Services\Notifier;
use Deally\Pipeline\Models\Contact;
use Deally\Pipeline\Models\Customer;
use Deally\Proposals\Models\KnowledgeGap;
use Deally\Proposals\Services\KnowledgeGapNotifier;
use Deally\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SaasFoundation\Models\Activity;
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

        /* The accounts a call can be filed under, each with the open deals the
           call may be attached to. Ownership mirrors the Calls list — a rep
           only ever sees the customers their seat is responsible for. */
        $customers = $this->scopeToSeat(Customer::query())
            ->with(['opportunities' => fn ($query) => $query->where('stage', '!=', 'lost')])
            ->orderBy('company')
            ->get();

        return view('calls::pages.calls', [
            'calls' => $calls,
            'platforms' => app(MeetingPlatformManager::class)->available(),
            'assignees' => $this->tenantMembershipOptions(),
            'suggestedOwners' => $this->suggestedOwnerOptions(),
            'ownerNames' => $ownerNames,
            'customers' => $customers,
            'canManageCustomers' => $this->deallyCan('deally.customers.manage'),
        ]);
    }

    public function show(Call $call)
    {
        $this->authorizeDeally('deally.calls.view');
        $this->authorizeSeatRecord($call);

        $call->load('transcriptLines');

        return view('calls::pages.call-detail', ['call' => $call]);
    }

    /**
     * The Call Detail modal body, as a fragment.
     *
     * Returned separately from the page so the calls list can open the modal
     * without rendering one full detail view per row — a list of two hundred
     * calls would otherwise carry two hundred transcripts in the HTML.
     */
    public function detail(Call $call)
    {
        $this->authorizeDeally('deally.calls.view');
        $this->authorizeSeatRecord($call);

        $call->load(['transcriptLines', 'openFlags']);

        /* The call's own activity history, including provider failures. A rep
           who triggered an error must be able to see it from the call itself,
           not only from a tenant-wide feed they may not be allowed to open. */
        $activity = Activity::query()
            ->where('subject_type', $call->getMorphClass())
            ->where('subject_id', $call->getKey())
            ->latest()
            ->take(8)
            ->get();

        return response()->view('calls::partials.call-detail-modal', [
            'call' => $call,
            'activity' => $activity,
        ])->header('Content-Type', 'text/html; charset=utf-8');
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
            'brief' => CustomerBrief::for($call),
            'platform' => app(MeetingPlatformManager::class)->describe($call),
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
            ->where('call_id', $call->id)
            ->latest('id')
            ->first();

        return view('calls::pages.call-summary', [
            'call' => $call,
            'reviewTask' => $reviewTask,
            'openFlags' => $call->openFlags()->get(),
        ]);
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
            /* The stream's "heard" cards, read back from the rows it was written
               from. The live view fades them after a few seconds, which makes
               them useless as a record, so the review carries them. */
            'heard' => $call->ephemerals()
                ->where('kind', CallEphemeral::KIND_HEARD)
                ->orderBy('id')
                ->get(),
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
            'customer_id' => ['nullable', 'integer'],
            'new_customer_company' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_role' => ['nullable', 'string', 'max:255'],
            'contact_id' => ['nullable', 'integer'],
            'session_type' => ['nullable', 'string', 'in:discovery,service_review,follow_up'],
            'meeting_platform' => ['nullable', 'string', 'max:40'],
            'invite_email' => ['nullable', 'email'],
            'date' => ['nullable', 'date'],
            'assignee_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'opportunity_id' => ['nullable', 'integer'],
        ]);

        $assigneeId = $data['assignee_user_id'] ?? null;

        if ($assigneeId !== null && ! in_array((string) $assigneeId, $this->tenantMemberUserIds(), true)) {
            $assigneeId = null;
        }

        /* The customer is either an existing account picked from the modal or
           a new one created inline — never both, and never a bare company
           string the caller made up. The company written on the call comes
           from the account, which is what keeps the two in step. A new account
           inherits the contact fields typed below, so nothing is entered
           twice. */
        $customer = $this->resolveCallCustomer($data['customer_id'] ?? null, [
            'company' => $data['new_customer_company'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_title' => $data['contact_role'] ?? null,
        ]);

        $contactName = trim((string) ($data['contact_name'] ?? ''));
        $contactRole = trim((string) ($data['contact_role'] ?? ''));

        $attributes = $data;
        unset(
            $attributes['assignee_user_id'],
            $attributes['meeting_platform'],
            $attributes['invite_email'],
            $attributes['customer_id'],
            $attributes['new_customer_company'],
            $attributes['opportunity_id'],
        );

        $attributes['company'] = $customer?->company ?: trim((string) ($data['company'] ?? ''));
        $attributes['contact_name'] = $contactName !== '' ? $contactName : $this->customerContactName($customer);
        $attributes['contact_role'] = $contactRole !== '' ? $contactRole : ($customer !== null ? $this->customerContactTitle($customer) : null);
        $attributes['opportunity_id'] = $this->resolveCallOpportunity($customer, $data['opportunity_id'] ?? null);

        if ($attributes['company'] === '') {
            return back()->withErrors(['customer_id' => 'Pick a customer or add one — a call is always filed under an account.'])->withInput();
        }

        /* `contacts` lives on the tenant connection, which the `exists` rule does
           not read, so it is checked here and an unknown id is dropped rather
           than written onto the call. */
        if (($attributes['contact_id'] ?? null) !== null) {
            $attributes['contact_id'] = Contact::query()->whereKey($attributes['contact_id'])->value('id');
        }

        $platforms = app(MeetingPlatformManager::class);

        /* A platform that is not enabled for this company is not accepted, so a
           crafted request cannot attach a call to a platform the rep was not
           offered. */
        $platform = $platforms->find($data['meeting_platform'] ?? null);

        if ($platform === null || ! $platform->enabled) {
            $platform = null;
        }

        $call = Call::create([...$attributes,
            'session_type' => $data['session_type'] ?? Call::SESSION_DISCOVERY,
            'duration' => '0m',
            'sentiment' => 'neutral',
            'status' => Call::STATUS_SCHEDULED,
            'date' => $data['date'] ?? now()->toDateString(),
            'owner_user_id' => $assigneeId ?: auth()->id(),
        ]);

        /* The meeting is attempted before the invitation is composed, so the
           customer is only ever told about joining details that actually
           exist. When the platform cannot create one, the invitation says the
           details will follow rather than including a link that goes nowhere. */
        $meeting = ['name' => $platform?->name];

        if ($platform !== null) {
            $result = $platforms->createMeetingFor($call, $platform, [
                'join_url' => null,
                'start_url' => null,
            ]);

            $meeting['join_url'] = $result['meeting']['join_url'];
            $meeting['start_url'] = $result['meeting']['start_url'];
        }

        $invitation = null;

        if (filled($data['invite_email'] ?? null)) {
            $invitation = app(CallInvitationService::class)->send(
                $call,
                (string) $data['invite_email'],
                $call->contact_name,
                $meeting
            );
        }

        app(ActivityLogger::class)->log('call.scheduled', "Scheduled '{$call->name}' with {$call->company}.");

        if ($invitation !== null && $invitation->wasDelivered()) {
            return redirect()
                ->route('deally.calls.live', $call)
                ->with('status', "Invitation sent to {$invitation->recipient_email}.");
        }

        if ($invitation !== null) {
            return redirect()
                ->route('deally.calls.live', $call)
                ->with('error', "The invitation could not be delivered: {$invitation->delivery_error}");
        }

        return redirect()->route('deally.calls.live', $call);
    }

    /**
     * The account a new call is filed under: an existing seat customer picked
     * from the modal, or a brand-new account created inline. An inline account
     * wins over a picked one, so a request cannot carry both and prefer a
     * customer the caller does not own.
     *
     * @param  array{company: ?string, contact_name: ?string, contact_title: ?string}  $newCustomer
     */
    protected function resolveCallCustomer(?int $customerId, array $newCustomer): ?Customer
    {
        if (filled(trim((string) ($newCustomer['company'] ?? '')))) {
            $this->authorizeDeally('deally.customers.manage');

            return Customer::query()->create([
                'company' => trim((string) $newCustomer['company']),
                'contact_name' => $newCustomer['contact_name'] ?? null,
                'contact_title' => $newCustomer['contact_title'] ?? null,
                'owner_user_id' => auth()->id(),
                'team_id' => $this->userTeamId(),
            ]);
        }

        if ($customerId === null) {
            return null;
        }

        return $this->scopeToSeat(Customer::query())->whereKey($customerId)->first();
    }

    /**
     * The customer's primary contact, falling back to the account-level name
     * and then the first recorded person when the account keeps contacts in
     * the extended table instead.
     */
    protected function customerContactName(?Customer $customer): ?string
    {
        if ($customer === null) {
            return null;
        }

        if (filled($customer->contact_name)) {
            return $customer->contact_name;
        }

        return $this->customerPrimaryContact($customer)?->name;
    }

    protected function customerContactTitle(?Customer $customer): ?string
    {
        if ($customer === null) {
            return null;
        }

        if (filled($customer->contact_title)) {
            return $customer->contact_title;
        }

        return $this->customerPrimaryContact($customer)?->title;
    }

    protected function customerPrimaryContact(?Customer $customer): ?Contact
    {
        if ($customer === null) {
            return null;
        }

        return $customer->contacts()->where('is_primary', true)->first()
            ?? $customer->contacts()->first();
    }

    /**
     * The deal a call is filed under must belong to the resolved account and
     * be open, mirroring the picker — a crafted request can never pin a call
     * to a stranger's or a lost deal.
     */
    protected function resolveCallOpportunity(?Customer $customer, mixed $opportunityId): ?int
    {
        if ($customer === null || blank($opportunityId)) {
            return null;
        }

        return $customer->opportunities()
            ->where('stage', '!=', 'lost')
            ->whereKey((int) $opportunityId)
            ->value('id');
    }

    /**
     * Send the invitation for a call that was already created.
     *
     * Re-sending is allowed and does not overwrite the earlier record: each
     * attempt is its own row, so the full sequence of what was actually sent
     * stays answerable.
     */
    public function invite(Request $request, Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $platforms = app(MeetingPlatformManager::class);
        $platform = $platforms->find($call->meeting_platform);

        $invitation = app(CallInvitationService::class)->send(
            $call,
            (string) $data['email'],
            $data['name'] ?? $call->contact_name,
            [
                'name' => $platform?->name,
                'join_url' => $call->meeting_join_url,
            ]
        );

        if (! $invitation->wasDelivered()) {
            return response()->json([
                'ok' => false,
                'error' => 'invitation_failed',
                'message' => $invitation->delivery_error ?: 'The mail transport rejected the message.',
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'status' => $invitation->status,
            'sent_at' => $invitation->sent_at?->toIso8601String(),
        ]);
    }

    /**
     * Preview the invitation without sending it.
     *
     * The rep can see the exact wording before it reaches a customer.
     */
    public function invitePreview(Call $call)
    {
        $this->authorizeDeally('deally.calls.view');
        $this->authorizeSeatRecord($call);

        $platforms = app(MeetingPlatformManager::class);
        $platform = $platforms->find($call->meeting_platform);

        $copy = app(CallInvitationService::class)->compose($call, [
            'name' => $platform?->name,
            'join_url' => $call->meeting_join_url,
        ]);

        return response()->json(['ok' => true, 'copy' => $copy]);
    }

    /**
     * Ask the chosen platform to admit the transcription bot.
     *
     * When no platform is connected this reports `unavailable` with the reason.
     * It never invents a bot presence, and it never returns a join URL: a
     * fabricated link would be believed by the rep, sent to the customer, and
     * would resolve to nothing.
     */
    public function join(Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        $result = app(MeetingPlatformManager::class)->requestBotJoinFor($call);

        return response()->json([
            'ok' => $result['status'] === Call::BOT_JOIN_REQUESTED || $result['status'] === Call::BOT_JOIN_JOINED,
            'bot_join_status' => $result['status'],
            'note' => $result['note'],
        ], $result['status'] === Call::BOT_JOIN_UNAVAILABLE ? 409 : 200);
    }

    /**
     * Record a call that did not happen, and the reschedule that follows.
     *
     * A failed or no-show call still needs a next step, otherwise it silently
     * leaves the pipeline with a dead deal and no reminder. A reschedule task is
     * raised here for exactly that reason.
     */
    public function fail(Request $request, Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        if ($call->isCompleted()) {
            return response()->json(['ok' => false, 'error' => 'call_completed'], 409);
        }

        $data = $request->validate([
            'status' => ['required', 'in:failed,no_show'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reschedule' => ['nullable', 'date'],
        ]);

        $call->forceFill([
            'status' => $data['status'],
            'ended_at' => $call->ended_at ?? now(),
            'ended_reason' => $data['status'] === Call::STATUS_NO_SHOW ? 'no_show' : 'failed',
            'failure_note' => $data['note'] ?? null,
        ])->save();

        $task = null;

        if (filled($data['reschedule'] ?? null)) {
            $task = Task::query()->firstOrCreate(
                [
                    'call_id' => $call->id,
                    'title' => "Reschedule call — {$call->company}",
                    'linked_company' => $call->company,
                    'owner_user_id' => $call->owner_user_id ?: auth()->id(),
                ],
                [
                    'due_at' => $data['reschedule'],
                    'status' => 'todo',
                ]
            );

            $call->forceFill(['date' => $data['reschedule'], 'status' => Call::STATUS_SCHEDULED])->save();
        }

        app(ActivityLogger::class)->log(
            'call.'.$data['status'],
            $data['status'] === Call::STATUS_NO_SHOW
                ? "Recorded a no-show for {$call->company}."
                : "Recorded a failed call for {$call->company}.",
            ['call_id' => $call->id, 'note' => $data['note'] ?? null],
            'warning',
            $call
        );

        return response()->json([
            'ok' => true,
            'status' => $call->status,
            'rescheduled' => $task !== null,
        ]);
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

        $task = Task::query()->firstOrCreate(
            ['call_id' => $call->id, 'title' => "Review Call — {$call->company}", 'linked_company' => $call->company, 'owner_user_id' => $call->owner_user_id ?: auth()->id()],
            ['due_at' => now()->addHours(24), 'status' => 'todo'],
        );

        $logger = app(ActivityLogger::class);
        $logger->log('call.ended', "Ended call '{$call->name}' with {$call->company} ({$call->sentiment}).");

        $notifier = app(Notifier::class);

        /* Notification categories are decided at the company level; the call
           report only fires when the account has it enabled. */
        $prefs = auth()->user()->currentMembership?->tenant?->settings['notifications'] ?? null;

        if ($prefs === null || ($prefs['call_reports'] ?? true)) {
            $notifier->notify(
                auth()->user(),
                'Call complete',
                $task->wasRecentlyCreated
                    ? "A review task for {$call->company} was created and is due within 24 hours."
                    : "The review task for {$call->company} was already open.",
                'call',
                route('deally.calls.review', $call)
            );
        }

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
            /* Silence is a valid outcome, not a failure: the client just moves on.
               The keys still match the full response so the renderer does not need
               a branch to learn there was nothing. */
            return response()->json([
                'ok' => true,
                'silent' => true,
                'transcript' => null,
                'line' => null,
                'findings' => [],
                'suggestions' => [],
                'ephemerals' => [],
                'analysis_failed' => false,
            ]);
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
        $analysis = $this->analyzeLatest($call);

        /* A provider failure during analysis must not fail the upload — the audio
           is already stored and the next window can still produce cards. But it
           is reported, because an empty `findings` array is otherwise
           indistinguishable from "the model had nothing to say", and a run of
           those looks exactly like a broken provider. */
        return $this->transcribeResponse(
            $call,
            $line,
            $analysis['findings'],
            ephemerals: $analysis['ephemerals'],
            analysisFailed: $this->analysisFailed
        );
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
     * @param  array<int, CallEphemeral>  $ephemerals
     */
    protected function transcribeResponse(
        Call $call,
        TranscriptLine $line,
        array $findings,
        array $ephemerals = [],
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
            /* The stream itself is DOM-only, but these are what it was showing,
               already written to the call's record so the review can show what the
               AI was attending to at each moment. */
            'ephemerals' => array_map(
                fn (CallEphemeral $ephemeral): array => [
                    'id' => $ephemeral->id,
                    'kind' => $ephemeral->kind,
                    'label' => $ephemeral->label,
                    'body' => $ephemeral->body,
                    'source' => $ephemeral->source,
                    'line_id' => $ephemeral->transcript_line_id,
                ],
                $ephemerals
            ),
        ]);
    }

    /**
     * Run structured analysis over the rolling transcript window, persisting
     * every finding and every ephemeral the panel will show.
     *
     * @return array{findings: array<int, CallFinding>, ephemerals: array<int, CallEphemeral>}
     */
    protected function analyzeLatest(Call $call): array
    {
        if (! $this->analysisDue($call)) {
            return ['findings' => [], 'ephemerals' => []];
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

            return ['findings' => [], 'ephemerals' => []];
        }

        $this->analysisFailed = false;

        // Record the attempt even when it produced nothing, so a provider
        // outage cannot turn every chunk into a fresh request.
        $call->forceFill(['last_analyzed_at' => now()])->save();

        return [
            'findings' => $this->persistFindings($call, $analysis),
            'ephemerals' => $this->persistEphemerals($call, $analysis, $lines),
        ];
    }

    /**
     * Write what the ephemeral stream is about to show to the call's record.
     *
     * The stream is a live-only surface — cards hold for a few seconds and then
     * fade — but losing the content would mean the review could only show the
     * transcript, with no way to see what the AI was attending to at each moment.
     * The fade governs visibility only; the record is permanent.
     *
     * @param  array<string, mixed>  $analysis
     * @param  Collection<int, TranscriptLine>  $lines
     * @return array<int, CallEphemeral>
     */
    protected function persistEphemerals(Call $call, array $analysis, $lines): array
    {
        $latest = $lines->last();
        $persisted = [];

        /* The "Heard" card is a model-authored paraphrase, never a truncation of
           the customer's own words. An earlier build cut the transcript at 90
           characters, which put the customer's exact sentence on screen wearing a
           label that claimed it was a summary. */
        $noticed = trim((string) ($analysis['noticed'] ?? ''));

        if ($noticed !== '' && $latest !== null) {
            $persisted[] = $this->recordEphemeral(
                $call,
                CallEphemeral::KIND_HEARD,
                'Heard',
                $noticed,
                $latest->id,
                $this->assistant()->name()
            );
        }

        // A proposal agreed mid-call is the gate for the Create Proposal action
        // after the call, so it is recorded while it is actually true.
        if (($analysis['proposal_intent'] ?? false) && ! $call->proposal_intent) {
            $call->forceFill([
                'proposal_intent' => true,
                'proposal_intent_note' => trim((string) ($analysis['proposal_intent_note'] ?? '')) ?: null,
            ])->save();
        }

        return array_values(array_filter($persisted));
    }

    protected function recordEphemeral(
        Call $call,
        string $kind,
        string $label,
        string $body,
        ?int $transcriptLineId = null,
        ?string $source = null
    ): ?CallEphemeral {
        $body = trim($body);

        if ($body === '') {
            return null;
        }

        $transcriptLineId ??= $call->transcriptLines()->latest('sequence')->value('id');

        return CallEphemeral::query()->firstOrCreate(
            [
                'call_id' => $call->id,
                'kind' => $kind,
                'body' => $body,
            ],
            [
                'transcript_line_id' => $transcriptLineId,
                'label' => $label,
                'source' => $source,
            ]
        );
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
            'call_finding_id' => $attributes['call_finding_id'] ?? null,
        ];

        // A question already waiting in the queue is not re-added: the same
        // objection or missing answer repeating would otherwise pile identical
        // gaps into the Solutions queue and, once approved, identical knowledge
        // base entries. The lookup is tenant-wide while the question is still
        // pending — once it leaves the queue, a genuine recurrence creates a
        // new gap again. Matching on text subsumes the old call-finding dedupe,
        // because the same finding always reports the same question.
        $gap = KnowledgeGap::query()
            ->where('status', 'pending')
            ->where('type', $payload['type'])
            ->whereRaw('LOWER(text) = ?', [mb_strtolower(trim($payload['text']))])
            ->orderBy('id')
            ->first();

        $gap ??= KnowledgeGap::query()->create($payload);

        // A freshly created gap is new in the queue: tell the people who
        // resolve gaps about it. Repeated feedback on the same finding reuses
        // the existing gap, so it must not re-notify.
        if ($gap->wasRecentlyCreated) {
            app(KnowledgeGapNotifier::class)->notifyUnresolved($gap);
        }

        return $gap;
    }

    public function liveQuery(Request $request, Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        $data = $request->validate([
            /* Logging a bare objection is a supported action: the rep heard
               resistance without the words to describe it. Making the text
               required made the empty branch below unreachable, so the button
               422'd and the objection was silently lost. */
            'text' => ['nullable', 'string', 'max:1000'],
        ]);

        $assistant = $this->assistant();
        $text = trim((string) ($data['text'] ?? ''));

        if ($request->boolean('objection')) {
            if ($text === '') {
                $this->recordGap($call, ['type' => 'objection', 'text' => 'Objection raised — no further detail']);
                $this->recordEphemeral(
                    $call,
                    CallEphemeral::KIND_OBJECTION,
                    'Objection',
                    'Objection flagged with no detail captured'
                );

                return response()->json(['ok' => true, 'answer' => null, 'cards' => []]);
            }

            $cards = $this->normalizeCards($assistant->suggest($call, $text));

            $this->recordEphemeral(
                $call,
                CallEphemeral::KIND_OBJECTION,
                'Objection',
                $text,
                null,
                $assistant->name()
            );

            $objection = collect($cards)->first(fn (array $card): bool => $card['role'] === 'objection');

            if ($objection === null || $objection['subtype'] === 'ask') {
                $this->recordGap($call, ['type' => 'objection', 'text' => "Objection raised: {$text}"]);
            }

            $this->recordQuery($call, $text, $objection['body'] ?? null, $cards);

            return response()->json(['ok' => true, 'answer' => $objection['body'] ?? null, 'cards' => $cards]);
        }

        if ($text === '') {
            return response()->json(['ok' => false, 'error' => 'text_required'], 422);
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

        $this->recordEphemeral(
            $call,
            CallEphemeral::KIND_ASKED,
            'You asked DeAlly',
            $text,
            null,
            $assistant->name()
        );

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
     * Correct something the AI got wrong.
     *
     * The correction never overwrites the AI's original read: the `ai_*` columns
     * keep what the model believed, and a `call_corrections` row records the
     * difference. That difference is the only part worth learning from, so
     * silently replacing the value would throw away the training signal this
     * whole review flow exists to produce.
     */
    public function correct(Request $request, Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        $data = $request->validate([
            'field' => ['required', 'in:sentiment,readiness,competitor_tag,objection_tag,gap_classification'],
            'value' => ['required', 'string', 'max:120'],
            'target_type' => ['nullable', 'string', 'max:40'],
            'target_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $field = (string) $data['field'];
        $value = trim((string) $data['value']);

        $aiValue = match ($field) {
            CallCorrection::FIELD_SENTIMENT => $call->ai_sentiment ?: $call->sentiment,
            CallCorrection::FIELD_READINESS => $call->ai_readiness ?: $call->readiness,
            default => null,
        };

        /* The note belongs to the correction row, not to the call: it explains the
           change, and writing it onto `calls` would both fail and lose it. */
        $attributes = [];

        if ($field === CallCorrection::FIELD_SENTIMENT) {
            $attributes['ai_sentiment'] = $aiValue ?: $call->sentiment;
            $attributes['sentiment'] = $value;
        }

        if ($field === CallCorrection::FIELD_READINESS) {
            $attributes['ai_readiness'] = $aiValue ?: $call->readiness;
            $attributes['readiness'] = $value;
        }

        $call->forceFill($attributes)->save();

        $correction = CallCorrection::query()->create([
            'call_id' => $call->id,
            'field' => $field,
            'target_type' => $data['target_type'] ?? null,
            'target_id' => $data['target_id'] ?? null,
            'ai_value' => $aiValue,
            'corrected_value' => $value,
            'note' => $data['note'] ?? null,
            'corrected_by_user_id' => auth()->id(),
        ]);

        return response()->json([
            'ok' => true,
            'correction' => [
                'id' => $correction->id,
                'field' => $correction->field,
                'ai_value' => $correction->ai_value,
                'corrected_value' => $correction->corrected_value,
            ],
            'sentiment' => $call->effectiveSentiment(),
            'readiness' => $call->effectiveReadiness(),
        ]);
    }

    /**
     * Raise a deal-status flag from a call.
     *
     * A flag is a statement about the deal, not about the call, and it is what
     * keeps the review task open: an unresolved risk cannot disappear into a
     * completed checklist.
     */
    public function storeFlag(Request $request, Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        $data = $request->validate([
            'headline' => ['required', 'string', 'max:160'],
            'rationale' => ['nullable', 'string', 'max:2000'],
            'opportunity_stage_id' => ['nullable', 'integer'],
        ]);

        $flag = CallFlag::query()->create([
            'call_id' => $call->id,
            'kind' => CallFlag::KIND_DEAL_RISK,
            'status' => CallFlag::STATUS_OPEN,
            'headline' => $data['headline'],
            'rationale' => $data['rationale'] ?? null,
            'opportunity_stage_id' => $data['opportunity_stage_id'] ?? null,
        ]);

        return response()->json(['ok' => true, 'flag' => [
            'id' => $flag->id,
            'headline' => $flag->headline,
            'status' => $flag->status,
        ]]);
    }

    /**
     * Resolve a deal-status flag.
     *
     * `confirmed` is not a lesser outcome than `dismissed`: it means the risk is
     * real and the deal has been moved accordingly, which is a different action
     * from having been wrong about the risk.
     */
    public function resolveFlag(Request $request, Call $call, CallFlag $flag)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        abort_unless($flag->call_id === $call->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:confirmed,adjusted,dismissed'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $flag->update([
            'status' => $data['status'],
            'resolution_note' => $data['resolution_note'] ?? null,
            'resolved_at' => now(),
        ]);

        return response()->json(['ok' => true, 'flag' => [
            'id' => $flag->id,
            'status' => $flag->status,
        ]]);
    }

    /**
     * Add an objection the AI missed.
     *
     * An agent hearing resistance the model did not catch is the highest-value
     * correction in the system, so it is recorded as both a gap and an
     * ephemeral — it belongs in the objection log and in the stream alike.
     */
    public function objection(Request $request, Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:1000'],
            'transcript_line_id' => ['nullable', 'integer'],
        ]);

        $text = trim((string) $data['text']);

        $lineId = $data['transcript_line_id'] ?? null;

        /* A line from another call is refused outright rather than quietly
           unattached, so the rep is never left believing the objection was
           pinned to the moment they said it was. */
        if ($lineId !== null && $call->transcriptLines()->whereKey($lineId)->doesntExist()) {
            return response()->json([
                'ok' => false,
                'error' => 'line_not_in_call',
            ], 422);
        }

        $gap = $this->recordGap($call, [
            'type' => 'objection',
            'text' => $text,
            'transcript_line_id' => $lineId,
        ]);

        $this->recordEphemeral($call, CallEphemeral::KIND_OBJECTION, 'Objection', $text, $lineId, 'Rep · review');

        return response()->json(['ok' => true, 'gap_id' => $gap->id]);
    }

    /**
     * Create a proposal from an agreed next step.
     *
     * Gated on `proposal_intent`, which only the analysis can set. A rep can
     * still open a proposal from the proposals module for any deal, but the
     * button on the call appears only when the call actually agreed one, because
     * an unconditional "Create Proposal" on every call is a suggestion the
     * rep has to evaluate and discard rather than an action.
     */
    public function proposal(Request $request, Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        if (! $call->proposal_intent) {
            return response()->json([
                'ok' => false,
                'error' => 'no_proposal_intent',
            ], 409);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
        ]);

        $title = filled($data['title'] ?? null)
            ? (string) $data['title']
            : "Draft proposal — {$call->company}";

        $task = Task::query()->firstOrCreate(
            [
                'call_id' => $call->id,
                'title' => $title,
                'linked_company' => $call->company,
                'owner_user_id' => $call->owner_user_id ?: auth()->id(),
            ],
            [
                'status' => 'todo',
                'due_at' => now()->addDay(),
                'notes' => trim(($call->proposal_intent_note ?: '')."\nAgreed on the {$call->date->format('j M')} call."),
            ]
        );

        app(ActivityLogger::class)->log(
            'call.proposal_requested',
            "Agreed proposal for {$call->company} turned into a task.",
            ['call_id' => $call->id, 'task_id' => $task->id],
            'info',
            $call
        );

        return response()->json([
            'ok' => true,
            'title' => $title,
            'task_id' => $task->id,
            'url' => route('deally.tasks.index'),
        ]);
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
