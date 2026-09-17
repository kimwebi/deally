<?php

namespace SaasFoundation\Http\Controllers\Tenant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Http\Requests\StorePageRequest;
use SaasFoundation\Models\Page;
use SaasFoundation\Models\Tenant;

class TenantPageController extends Controller
{
    public function index(Request $request, Tenant $tenant): View
    {
        $pages = Page::forTenant($tenant->id)
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');
                $query->where('title', 'like', "%{$search}%");
            })
            ->orderBy('sort_order')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('tenant.pages.index', compact('tenant', 'pages'));
    }

    public function create(Tenant $tenant): View
    {
        return view('tenant.pages.create', compact('tenant'));
    }

    public function store(StorePageRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $this->hydratePublishedAt($request->validated());

        $page = Page::create([
            'tenant_id' => $tenant->id,
            ...$data,
        ]);

        $page->activities()->create([
            'tenant_id' => $tenant->id,
            'user_id' => auth()->id(),
            'event' => 'page.created',
            'description' => "Page '{$page->title}' created",
        ]);

        return redirect()
            ->route('tenant.pages.index', $tenant)
            ->with('success', "Page '{$page->title}' created.");
    }

    public function publicShow(Tenant $tenant, string $slug): View
    {
        $page = Page::forTenant($tenant->id)
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        return view('pages.show', compact('tenant', 'page'));
    }

    public function edit(Tenant $tenant, Page $page): View
    {
        abort_if($page->tenant_id !== $tenant->id, 404);

        return view('tenant.pages.edit', compact('tenant', 'page'));
    }

    public function update(StorePageRequest $request, Tenant $tenant, Page $page): RedirectResponse
    {
        abort_if($page->tenant_id !== $tenant->id, 404);

        $page->update($this->hydratePublishedAt($request->validated()));

        return redirect()
            ->route('tenant.pages.index', $tenant)
            ->with('success', "Page '{$page->title}' updated.");
    }

    public function destroy(Tenant $tenant, Page $page): RedirectResponse
    {
        abort_if($page->tenant_id !== $tenant->id, 404);

        $page->delete();

        return back()->with('success', "Page '{$page->title}' deleted.");
    }

    private function hydratePublishedAt(array $data): array
    {
        if ($data['status'] === Page::STATUS_PUBLISHED) {
            $data['published_at'] = $data['published_at'] ?? now();

            return $data;
        }

        $data['published_at'] = null;

        return $data;
    }
}
