@extends('layouts.app')
@php $pageTitle = 'Projects'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <span>Projects</span>
</div>

<div class="page-header">
    <h1>Projects</h1>
    <a href="{{ route('tenant.projects.create', $tenant) }}" class="btn btn-primary">New Project</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('tenant.projects.index', $tenant) }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Project name...">
            </div>
            <div class="form-group" style="margin-bottom:0; min-width:150px;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All statuses</option>
                    @foreach(['active', 'archived', 'paused'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Description</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($projects as $project)
                    <tr>
                        <td style="font-weight:500;">{{ $project->name }}</td>
                        <td>
                            <span class="badge {{ $project->status === 'active' ? 'badge-success' : ($project->status === 'paused' ? 'badge-warning' : 'badge-gray') }}">{{ ucfirst($project->status) }}</span>
                        </td>
                        <td class="text-muted text-sm" style="max-width:280px;">{{ \Illuminate\Support\Str::limit($project->description, 60) }}</td>
                        <td class="text-muted text-sm">{{ $project->created_at->diffForHumans() }}</td>
                        <td class="text-right">
                            <div class="flex gap-2" style="justify-content:flex-end;">
                                <a href="{{ route('tenant.projects.edit', [$tenant, $project]) }}" class="btn btn-secondary btn-sm">Edit</a>
                                <form method="POST" action="{{ route('tenant.projects.destroy', [$tenant, $project]) }}" onsubmit="return confirm('Delete this project?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted" style="padding:32px;">No projects found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($projects->hasPages())
    <div class="card-footer">{{ $projects->links() }}</div>
    @endif
</div>
@endsection