<?php

namespace Deally\Pipeline\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Pipeline\Models\Opportunity;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function index()
    {
        $this->authorizeDeally('deally.pipeline.view');

        $query = $this->scopeToSeat(Opportunity::query());

        $opportunities = $query
            ->when(request('stage'), function ($query, string $stage): void {
                $query->where('stage', $stage);
            })
            ->orderByDesc('value')
            ->get();

        $totalValue = $opportunities->sum('value');

        return view('pipeline::pages.pipeline', [
            'opportunities' => $opportunities,
            'totalValue' => $totalValue,
            'stages' => Opportunity::stages(),
            'activeStage' => request('stage'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeDeally('deally.pipeline.manage');

        $data = $request->validate([
            'company' => ['required', 'string'],
            'contact_name' => ['nullable', 'string'],
            'contact_title' => ['nullable', 'string'],
            'packages' => ['nullable', 'string'],
            'stage' => ['nullable', 'string', 'in:discovery,demo,negotiation,won,lost'],
            'value' => ['nullable', 'numeric', 'min:0'],
        ]);

        Opportunity::create($data + ['owner_user_id' => auth()->id()]);

        return back()->with('toast', 'Deal created.');
    }
}
