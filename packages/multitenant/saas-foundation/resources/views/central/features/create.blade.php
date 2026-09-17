@extends('layouts.app')
@php $pageTitle = 'Create Feature'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('central.features.index') }}">Features</a>
    <span>/</span>
    <span>Create</span>
</div>

<div class="page-header">
    <h1>Create Feature</h1>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-body">
        <form method="POST" action="{{ route('central.features.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control" required>
                @error('name')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="slug">Slug</label>
                <input type="text" id="slug" name="slug" value="{{ old('slug') }}" class="form-control" placeholder="Auto-generated">
                @error('slug')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="group_name">Group</label>
                <input type="text" id="group_name" name="group_name" value="{{ old('group_name') }}" class="form-control" list="group-options" required>
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
                <textarea id="description" name="description" class="form-control">{{ old('description') }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Create Feature</button>
                <a href="{{ route('central.features.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection