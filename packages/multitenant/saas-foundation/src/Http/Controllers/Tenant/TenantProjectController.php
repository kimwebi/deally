<?php

namespace SaasFoundation\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Http\Requests\StoreProjectRequest;
use SaasFoundation\Models\Project;
use SaasFoundation\Models\Tenant;

class TenantProjectController extends Controller
{
    public function index(Request $request, Tenant $tenant)
    {
        $projects = Project::forTenant($tenant->id)
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('tenant.projects.index', compact('tenant', 'projects'));
    }

    public function create(Tenant $tenant)
    {
        return view('tenant.projects.create', compact('tenant'));
    }

    public function store(StoreProjectRequest $request, Tenant $tenant)
    {
        $project = Project::create([
            'tenant_id' => $tenant->id,
            'name' => $request->string('name'),
            'description' => $request->input('description'),
            'status' => 'active',
        ]);

        $project->activities()->create([
            'tenant_id' => $tenant->id,
            'user_id' => auth()->id(),
            'event' => 'project.created',
            'description' => "Project '{$project->name}' created",
        ]);

        return redirect()
            ->route('tenant.projects.index', $tenant)
            ->with('success', "Project '{$project->name}' created.");
    }

    public function edit(Tenant $tenant, Project $project)
    {
        abort_if($project->tenant_id !== $tenant->id, 404);

        return view('tenant.projects.edit', compact('tenant', 'project'));
    }

    public function update(StoreProjectRequest $request, Tenant $tenant, Project $project)
    {
        abort_if($project->tenant_id !== $tenant->id, 404);

        $project->update($request->validated());

        return redirect()
            ->route('tenant.projects.index', $tenant)
            ->with('success', "Project '{$project->name}' updated.");
    }

    public function destroy(Tenant $tenant, Project $project)
    {
        abort_if($project->tenant_id !== $tenant->id, 404);

        $project->delete();

        return back()->with('success', "Project '{$project->name}' deleted.");
    }
}
