@extends('layouts.app')
@php $pageTitle = 'Features'; @endphp

@section('content')
<div class="page-header">
    <h1>Features</h1>
    <a href="{{ route('central.features.create') }}" class="btn btn-primary">Create Feature</a>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Group</th>
                        <th>Description</th>
                        <th>Plans</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($features as $feature)
                    <tr>
                        <td style="font-weight:500;">{{ $feature->name }}</td>
                        <td class="mono text-muted">{{ $feature->slug }}</td>
                        <td><span class="badge badge-gray">{{ $feature->group_name }}</span></td>
                        <td class="text-muted text-sm">{{ $feature->description ?: '—' }}</td>
                        <td>{{ $feature->plans_count }}</td>
                        <td class="text-right">
                            <div class="flex gap-2" style="justify-content:flex-end;">
                                <a href="{{ route('central.features.edit', $feature) }}" class="btn btn-secondary btn-sm">Edit</a>
                                <form method="POST" action="{{ route('central.features.destroy', $feature) }}" onsubmit="return confirm('Are you sure?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted" style="padding:32px;">No features found. <a href="{{ route('central.features.create') }}">Create one</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection