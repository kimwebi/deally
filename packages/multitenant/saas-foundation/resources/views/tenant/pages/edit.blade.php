@extends('layouts.app')
@php $pageTitle = 'Edit Page - ' . $page->title; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <a href="{{ route('tenant.pages.index', $tenant) }}">Pages</a>
    <span>/</span>
    <span>{{ $page->title }}</span>
</div>

<div class="page-header">
    <h1>Edit Landing Page</h1>
</div>

<div class="card" style="max-width:860px;">
    <div class="card-body">
        <form method="POST" action="{{ route('tenant.pages.update', [$tenant, $page]) }}">
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
                <label class="form-label" for="title">Title</label>
                <input type="text" id="title" name="title" value="{{ old('title', $page->title) }}" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="slug">Slug <span class="text-muted text-sm">(lowercase, hyphens)</span></label>
                <input type="text" id="slug" name="slug" value="{{ old('slug', $page->slug) }}" class="form-control" required>
            </div>

            <div class="flex gap-3">
                <div class="form-group" style="flex:1;">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        @foreach(['draft', 'published'] as $status)
                        <option value="{{ $status }}" {{ old('status', $page->status) === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="form-label" for="sort_order">Sort order</label>
                    <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $page->sort_order) }}" min="0" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="content">Content <span class="text-muted text-sm">(HTML allowed)</span></label>
                <textarea id="content" name="content" class="form-control" rows="14" style="font-family:ui-monospace,Menlo,monospace; font-size:13px;">{{ old('content', $page->content) }}</textarea>
            </div>

            <div class="flex gap-3">
                <div class="form-group" style="flex:1;">
                    <label class="form-label" for="meta_title">Meta title</label>
                    <input type="text" id="meta_title" name="meta_title" value="{{ old('meta_title', $page->meta_title) }}" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="meta_description">Meta description</label>
                <textarea id="meta_description" name="meta_description" class="form-control" rows="3">{{ old('meta_description', $page->meta_description) }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('tenant.pages.index', $tenant) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection