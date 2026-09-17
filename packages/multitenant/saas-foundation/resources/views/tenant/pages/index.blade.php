@extends('layouts.app')
@php $pageTitle = 'Pages'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <span>Pages</span>
</div>

<div class="page-header">
    <h1>Landing Pages</h1>
    <a href="{{ route('tenant.pages.create', $tenant) }}" class="btn btn-primary">New Page</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('tenant.pages.index', $tenant) }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Page title...">
            </div>
            <div class="form-group" style="margin-bottom:0; min-width:150px;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All statuses</option>
                    @foreach(['draft', 'published'] as $status)
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
                        <th>Title</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pages as $page)
                    <tr>
                        <td style="font-weight:500;">{{ $page->title }}</td>
                        <td class="text-muted text-sm">{{ $page->slug }}</td>
                        <td>
                            <span class="badge {{ $page->status === 'published' ? 'badge-success' : 'badge-gray' }}">{{ ucfirst($page->status) }}</span>
                        </td>
                        <td class="text-muted text-sm">{{ $page->updated_at->diffForHumans() }}</td>
                        <td class="text-right">
                            <div class="flex gap-2" style="justify-content:flex-end;">
                                @if($page->isPublished())
                                <a href="{{ route('tenant.pages.show', [$tenant, $page->slug]) }}" target="_blank" class="btn btn-secondary btn-sm">View</a>
                                @endif
                                <a href="{{ route('tenant.pages.edit', [$tenant, $page]) }}" class="btn btn-secondary btn-sm">Edit</a>
                                <form method="POST" action="{{ route('tenant.pages.destroy', [$tenant, $page]) }}" onsubmit="return confirm('Delete this page?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted" style="padding:32px;">No pages found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($pages->hasPages())
    <div class="card-footer">{{ $pages->links() }}</div>
    @endif
</div>
@endsection