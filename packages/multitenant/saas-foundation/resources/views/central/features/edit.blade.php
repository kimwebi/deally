@extends('layouts.app')
@php $pageTitle = 'Edit Feature - ' . $feature->name; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('central.features.index') }}">Features</a>
    <span>/</span>
    <span>{{ $feature->name }}</span>
</div>

<div class="page-header">
    <h1>Edit Feature</h1>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-body">
        <form method="POST" action="{{ route('central.features.update', $feature) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $feature->name) }}" class="form-control" required>
                @error('name')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="slug">Slug</label>
                <input type="text" id="slug" name="slug" value="{{ old('slug', $feature->slug) }}" class="form-control" required>
                @error('slug')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="group_name">Group</label>
                <input type="text" id="group_name" name="group_name" value="{{ old('group_name', $feature->group_name) }}" class="form-control" list="group-options" required>
                <datalist id="group-options">
                    @foreach($groups as $group)
                    <option value="{{ $group }}"></option>
                    @endforeach
                </datalist>
                @error('group_name')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-control">{{ old('description', $feature->description) }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('central.features.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection