<?php

namespace Deally\Calls\Http\Controllers;

use Deally\Calls\Models\Call;
use Deally\Calls\Models\TranscriptLine;
use Deally\Calls\Services\LiveAssistant;
use Deally\Core\Http\Controllers\Controller;
use Deally\Proposals\Models\KnowledgeGap;
use Deally\Tasks\Models\Task;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function index()
    {
        $calls = Call::orderByDesc('date')->get();

        return view('calls::pages.calls', ['calls' => $calls]);
    }

    public function show(Call $call)
    {
        $call->load('transcriptLines');

        return view('calls::pages.call-detail', ['call' => $call]);
    }

    public function live(Call $call)
    {
        $call->load('opportunity');

        $contact = $call->contact_name ?: 'the customer';

        $script = [
            [
                'type' => 'customer',
                'text' => "We're currently using a legacy tool at {$call->company}, but support has been really slow. Open to alternatives.",
                'delay' => 2500,
                'scenario' => '1 / 5 · Auto-pop',
            ],
            [
                'type' => 'ai_ask',
                'text' => 'Which pricing tier applies to this deal? Choose the tier the customer mentioned.',
                'delay' => 5500,
                'scenario' => '2 / 5 · AI asks you',
            ],
            [
                'type' => 'customer',
                'text' => "{$contact} asked whether we support HIPAA compliance — no documented answer in the knowledge base.",
                'delay' => 6000,
                'scenario' => '3 / 5 · Request expert',
            ],
            [
                'type' => 'customer',
                'text' => 'Three needs expressed in one statement: SSO for security, better pricing, and native Slack integration.',
                'delay' => 7000,
                'scenario' => '4 / 5 · Multi-intervention',
            ],
            [
                'type' => 'ai_detect',
                'text' => 'Ambiguity detected — the customer says their current setup "mostly works" but the pain point is unclear.',
                'delay' => 5500,
                'scenario' => '5 / 5 · Suggested question',
            ],
        ];

        return view('calls::pages.call-live', ['call' => $call, 'script' => $script]);
    }

    public function summary(Call $call)
    {
        return view('calls::pages.call-summary', ['call' => $call]);
    }

    public function store(Request $request)
    {
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
        ]);

        return redirect()->route('deally.calls.live', $call);
    }

    public function end(Request $request, Call $call)
    {
        $data = $request->validate([
            'duration' => ['nullable', 'string'],
            'sentiment' => ['nullable', 'string', 'in:positive,neutral,negative'],
            'notes' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
        ]);

        $call->update($data);

        if ($request->boolean('createTask')) {
            Task::create([
                'title' => "Review Call — {$call->company}",
                'linked_company' => $call->company,
                'due_at' => now()->toDateString(),
                'status' => 'todo',
            ]);
        }

        return redirect()->route('deally.calls.summary', $call);
    }

    public function liveTranscribe(Request $request, Call $call, LiveAssistant $assistant)
    {
        $data = $request->validate([
            'audio' => ['required', 'file', 'max:10240'],
        ]);

        $transcript = $assistant->transcribe(
            $data['audio']->get(),
            $data['audio']->getClientOriginalName() ?: 'chunk.webm'
        );

        if ($transcript === null) {
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
            'suggestions' => $assistant->suggest($call, $transcript),
        ]);
    }

    public function liveQuery(Request $request, Call $call, LiveAssistant $assistant)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:1000'],
        ]);

        $answer = $assistant->answer($call, $data['text']);

        if ($answer === null) {
            return response()->json(['ok' => false, 'error' => 'query_failed'], 422);
        }

        return response()->json(['ok' => true, 'answer' => $answer]);
    }

    public function transcript(Request $request, Call $call)
    {
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
}
