<?php

namespace Deally\Proposals\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Services\ActivityLogger;
use Deally\Proposals\Models\Proposal;
use Deally\Retention\Services\RetentionService;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    public function index(RetentionService $retention)
    {
        $this->authorizeDeally('deally.proposals.view');

        $proposals = $this->scopeToSeat(Proposal::query())->orderByDesc('updated_at')->get();
        $inflight = $proposals->where('status', '!=', 'approved')->where('status', '!=', 'rejected')->sum('value');
        $archivedIds = $retention->archivedProposals()->pluck('id')->all();

        return view('proposals::pages.proposals', [
            'proposals' => $proposals,
            'inflight' => $inflight,
            'archivedIds' => $archivedIds,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeDeally('deally.proposals.manage');

        $data = $request->validate([
            'name' => ['required', 'string'],
            'company' => ['required', 'string'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string'],
        ]);

        Proposal::create($data + ['status' => 'draft', 'owner_user_id' => auth()->id()]);

        app(ActivityLogger::class)->log(
            'proposal.created',
            "Saved proposal '{$data['name']}' for {$data['company']}."
        );

        return back()->with('toast', 'Proposal saved.');
    }
}
