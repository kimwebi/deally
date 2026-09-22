<?php

namespace Deally\Solutions\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Proposals\Models\KnowledgeEntry;
use Deally\Proposals\Models\KnowledgeGap;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SolutionsController extends Controller
{
    public function workspace()
    {
        $this->authorizeDeally('deally.kb.manage');

        $pendingGaps = KnowledgeGap::where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('solutions::pages.solutions', [
            'entriesCount' => KnowledgeEntry::count(),
            'pendingGaps' => $pendingGaps,
            'gapsPendingCount' => $pendingGaps->count(),
            'gapsThisWeek' => KnowledgeGap::where('created_at', '>=', now()->startOfWeek())->count(),
            'expertPings' => KnowledgeGap::where('type', 'gap')
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->take(6)
                ->get(),
            'corrections' => KnowledgeGap::where('type', 'correction')
                ->orderBy('created_at', 'desc')
                ->take(6)
                ->get(),
        ]);
    }

    public function resolveGap(Request $request, KnowledgeGap $gap)
    {
        $this->authorizeDeally('deally.kb.manage');

        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(['approve', 'reject', 'edit'])],
            'text' => ['nullable', 'string', 'max:2000'],
        ]);

        match ($data['action']) {
            'approve' => $gap->update(['status' => 'live']),
            'reject' => $gap->update(['status' => 'rejected']),
            'edit' => $gap->update([
                'status' => 'pending',
                'text' => $data['text'] ?? $gap->text,
            ]),
        };

        return back()->with('toast', 'Gap resolved.');
    }
}
