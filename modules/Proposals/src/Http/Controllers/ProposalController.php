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

    public function updateStatus(Request $request, Proposal $proposal)
    {
        $this->authorizeDeally('deally.proposals.manage');
        $this->authorizeSeatRecord($proposal);

        $status = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected,draft'],
        ])['status'];

        $proposal->update(['status' => $status]);

        app(ActivityLogger::class)->log(
            'proposal.'.$status,
            "Proposal '{$proposal->name}' {$status}.",
            ['proposal_id' => $proposal->getKey()],
            'info',
            $proposal
        );

        return back()->with('toast', "Proposal '{$proposal->name}' marked as {$status}.");
    }

    /**
     * The editable proposal detail, rendered as a form fragment for the
     * Proposal Detail modal (opened from the pipeline engagement log and
     * the proposals page alike).
     */
    public function show(Proposal $proposal)
    {
        $this->authorizeDeally('deally.proposals.view');
        $this->authorizeSeatRecord($proposal);

        return view('proposals::partials.detail', [
            'proposal' => $proposal,
            'canManage' => $this->deallyCan('deally.proposals.manage'),
            'statuses' => ['draft', 'viewed', 'sent', 'approved', 'rejected'],
        ]);
    }

    public function update(Request $request, Proposal $proposal)
    {
        $this->authorizeDeally('deally.proposals.manage');
        $this->authorizeSeatRecord($proposal);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'package' => ['nullable', 'string', 'max:255'],
            'quote' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:draft,viewed,sent,approved,rejected'],
        ]);

        $proposal->update($data);

        app(ActivityLogger::class)->log(
            'proposal.updated',
            "Proposal '{$proposal->name}' updated for {$proposal->company}.",
            ['proposal_id' => $proposal->getKey(), 'status' => $data['status']],
            'info',
            $proposal
        );

        return back()->with('toast', "Proposal '{$proposal->name}' updated.");
    }
}
