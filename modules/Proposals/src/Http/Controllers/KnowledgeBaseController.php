<?php

namespace Deally\Proposals\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Proposals\Models\KnowledgeEntry;
use Deally\Proposals\Models\KnowledgeGap;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    public function index()
    {
        return view('proposals::pages.knowledge-base', [
            'entries' => KnowledgeEntry::orderBy('type')->get(),
            'gaps' => KnowledgeGap::orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function storeEntry(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        KnowledgeEntry::create($data);

        return back()->with('toast', 'KB entry saved.');
    }
}
