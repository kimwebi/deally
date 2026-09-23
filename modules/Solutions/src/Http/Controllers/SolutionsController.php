<?php

namespace Deally\Solutions\Http\Controllers;

use Deally\Calls\Models\Call;
use Deally\Core\Http\Controllers\Controller;
use Deally\Proposals\Models\KnowledgeEntry;
use Deally\Proposals\Models\KnowledgeGap;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class SolutionsController extends Controller
{
    public function workspace()
    {
        $this->authorizeDeally('deally.kb.manage');

        $calls = $this->scopeToSeat(Call::query())->get();

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
            'voc' => $this->vocTrends($calls),
        ]);
    }

    /**
     * Organisation-wide voice-of-customer trends from logged call sentiment.
     *
     * @return array{
     *     months: array<int, string>,
     *     rows: array<int, array{label: string, cells: array<int, string>, recentCount: int}>,
     *     total30: int,
     *     positive30: int,
     *     negative30: int,
     * }
     */
    protected function vocTrends(Collection $calls): array
    {
        $now = now();
        $months = collect(range(5, 0))->map(fn (int $i) => [
            'label' => $now->copy()->subMonths($i)->format('M'),
            'start' => $now->copy()->subMonths($i)->startOfMonth(),
            'end' => $now->copy()->subMonths($i)->endOfMonth(),
        ]);

        $rows = $calls
            ->groupBy('company')
            ->map(function (Collection $companyCalls, string $company) use ($months, $now): array {
                $cells = $months->map(function (array $month) use ($companyCalls): string {
                    $inMonth = $companyCalls->filter(fn (Call $call) => $call->date !== null
                        && $call->date->between($month['start'], $month['end']));

                    if ($inMonth->isEmpty()) {
                        return '';
                    }

                    if ($inMonth->where('sentiment', 'negative')->count() > 0) {
                        return 'hot';
                    }

                    if ($inMonth->where('sentiment', 'positive')->count() === $inMonth->count()) {
                        return 'cold';
                    }

                    return 'warm';
                });

                return [
                    'label' => $company,
                    'cells' => $cells->values()->all(),
                    'recentCount' => $companyCalls
                        ->filter(fn (Call $call) => $call->date !== null && $call->date->between($now->copy()->subDays(60), $now))
                        ->count(),
                ];
            })
            ->sortByDesc('recentCount')
            ->take(4)
            ->values()
            ->all();

        $recent = $calls->filter(fn (Call $call) => $call->date !== null && $call->date->between($now->copy()->subDays(30), $now));

        return [
            'months' => $months->pluck('label')->all(),
            'rows' => $rows,
            'total30' => $recent->count(),
            'positive30' => $recent->where('sentiment', 'positive')->count(),
            'negative30' => $recent->where('sentiment', 'negative')->count(),
        ];
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
