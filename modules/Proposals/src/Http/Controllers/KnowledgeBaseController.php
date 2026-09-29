<?php

namespace Deally\Proposals\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Proposals\Models\KnowledgeEntry;
use Deally\Proposals\Models\KnowledgeGap;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeDeally('deally.kb.view');

        $search = trim((string) $request->query('search', ''));

        $entries = KnowledgeEntry::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            }))
            ->orderBy('type')
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        $gaps = KnowledgeGap::query()
            ->orderBy('created_at', 'desc')
            ->get();

        return view('proposals::pages.knowledge-base', [
            'entries' => $entries,
            'gaps' => $gaps,
            'search' => $search,
        ]);
    }

    public function storeEntry(Request $request)
    {
        $this->authorizeDeally('deally.kb.manage');

        $data = $request->validate([
            'type' => ['required', 'string'],
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        KnowledgeEntry::create($data);

        return back()->with('toast', 'KB entry saved.');
    }
}
