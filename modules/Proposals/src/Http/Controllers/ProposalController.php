<?php

namespace Deally\Proposals\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Proposals\Models\Proposal;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    public function index()
    {
        $proposals = Proposal::orderByDesc('updated_at')->get();
        $inflight = $proposals->where('status', '!=', 'approved')->where('status', '!=', 'rejected')->sum('value');

        return view('proposals::pages.proposals', [
            'proposals' => $proposals,
            'inflight' => $inflight,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'company' => ['required', 'string'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string'],
        ]);

        Proposal::create($data + ['status' => 'draft']);

        return back()->with('toast', 'Proposal saved.');
    }
}
