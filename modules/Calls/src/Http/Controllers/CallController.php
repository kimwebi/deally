<?php

namespace Deally\Calls\Http\Controllers;

use Deally\Calls\Models\Call;
use Deally\Calls\Models\TranscriptLine;
use Deally\Calls\Services\DummyAssistant;
use Deally\Calls\Services\LiveAssistant;
use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Services\ActivityLogger;
use Deally\Core\Services\Notifier;
use Deally\Proposals\Models\KnowledgeGap;
use Deally\Tasks\Models\Task;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function index()
    {
        $this->authorizeDeally('deally.calls.view');

        $calls = $this->scopeToSeat(Call::query())->with('opportunity')->orderByDesc('date')->get();

        return view('calls::pages.calls', ['calls' => $calls]);
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

        $tasks = Task::query()
            ->where('linked_company', $call->company)
            ->orderByRaw("CASE WHEN status = 'closed' THEN 1 ELSE 0 END")
            ->orderBy('due_at')
            ->limit(6)
            ->get();

        return view('calls::pages.call-live', ['call' => $call, 'tasks' => $tasks]);
    }

    public function summary(Call $call)
    {
        $this->authorizeDeally('deally.calls.view');
        $this->authorizeSeatRecord($call);

        $reviewTask = Task::query()
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

        $gaps = KnowledgeGap::query()
            ->where('source', 'LIKE', "%{$call->company}%")
            ->orderByDesc('id')
            ->get();

        return view('calls::pages.call-review', ['call' => $call, 'gaps' => $gaps]);
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
        ]);

        $call = Call::create([...$data,
            'duration' => '0m',
            'sentiment' => 'neutral',
            'date' => $data['date'] ?? now()->toDateString(),
            'owner_user_id' => auth()->id(),
        ]);

        return redirect()->route('deally.calls.live', $call);
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

        $call->update($data);

        if (blank($data['summary'] ?? null)) {
            $call->update(['summary' => ($call->contact_name ?: 'The customer')
                .' discussed needs for '.$call->company.'. Overall sentiment was '
                .($call->sentiment ?: 'neutral').'; the follow-ups and guidance were captured for review.']);
        }

        Task::query()->firstOrCreate(
            ['title' => "Review Call — {$call->company}", 'linked_company' => $call->company],
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

    public function liveTranscribe(Request $request, Call $call)
    {
        $this->authorizeDeally('deally.calls.manage');
        $this->authorizeSeatRecord($call);

        $data = $request->validate([
            'audio' => ['required', 'file', 'max:10240'],
        ]);

        $assistant = $this->assistant();

        $transcript = $assistant->transcribe(
            $data['audio']->get(),
            $data['audio']->getClientOriginalName() ?: 'chunk.webm'
        );

        if ($transcript === null) {
            app(ActivityLogger::class)->log(
                'call.transcribe_failed',
                "Live transcription failed for {$call->company}.",
                ['call_id' => $call->id],
                'error',
                $call
            );

            return response()->json(['ok' => false, 'error' => 'transcription_failed'], 422);
        }

        $next = $call->transcriptLines()->max('sequence') + 1;

        TranscriptLine::create([
            'call_id' => $call->id,
            'speaker' => 'customer',
            'is_agent' => false,
            'text' => $transcript,
            'sequence' => $next,
        ]);

        return response()->json([
            'ok' => true,
            'transcript' => $transcript,
            'suggestions' => $this->normalizeCards($assistant->suggest($call, $transcript)),
        ]);
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
                KnowledgeGap::query()->create([
                    'type' => 'objection',
                    'text' => 'Objection raised — no further detail',
                    'source' => $call->name.' · '.$call->company.' · '.$call->date->format('M d'),
                    'status' => 'pending',
                ]);

                return response()->json(['ok' => true, 'answer' => null, 'cards' => []]);
            }

            $objection = collect($cards)->first(fn (array $card): bool => $card['role'] === 'objection');

            if ($objection === null || $objection['subtype'] === 'ask') {
                KnowledgeGap::query()->create([
                    'type' => 'objection',
                    'text' => "Objection raised: {$text}",
                    'source' => $call->name.' · '.$call->company.' · '.$call->date->format('M d'),
                    'status' => 'pending',
                ]);
            }

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

        return response()->json([
            'ok' => true,
            'answer' => $answer,
            'cards' => $this->normalizeCards($assistant->suggest($call, $text)),
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

    protected function assistant(): DummyAssistant|LiveAssistant
    {
        return blank(config('services.openai.key'))
            ? new DummyAssistant
            : new LiveAssistant;
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

        KnowledgeGap::create([
            'type' => $data['type'] ?? 'correction',
            'text' => $data['text'],
            'source' => $call->name.' · '.$call->company.' · '.$call->date->format('M d'),
            'status' => 'pending',
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
