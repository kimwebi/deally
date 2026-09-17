@extends('layouts.app')
@php $pageTitle = 'Edit Project - ' . $project->name; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <a href="{{ route('tenant.projects.index', $tenant) }}">Projects</a>
    <span>/</span>
    <span>{{ $project->name }}</span>
</div>

<div class="page-header">
    <h1>Edit Project</h1>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-body">
        <form method="POST" action="{{ route('tenant.projects.update', [$tenant, $project]) }}">
            @csrf
            @method('PUT')

            @if($errors->any())
            <div class="alert alert-danger">
                <ul style="margin-left:20px;">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="form-group">
                <label class="form-label" for="name">Project name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $project->name) }}" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4">{{ old('description', $project->description) }}</textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    @foreach(['active', 'paused', 'archived'] as $status)
                    <option value="{{ $status }}" {{ old('status', $project->status) === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('tenant.projects.index', $tenant) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection