@extends('layouts.app')
@php $pageTitle = 'Invite Member'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <a href="{{ route('tenant.invitations.index', $tenant) }}">Invitations</a>
    <span>/</span>
    <span>Send</span>
</div>

<div class="page-header">
    <h1>Invite Member</h1>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-body">
        <form method="POST" action="{{ route('tenant.invitations.store', $tenant) }}">
            @csrf

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
                <label class="form-label" for="email">Email address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
                <div class="form-help">The person will receive an email with a link to join this tenant.</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="role_id">Role</label>
                <select id="role_id" name="role_id" class="form-control">
                    <option value="">Default (Member)</option>
                    @foreach($roles as $role)
                    <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Send Invitation</button>
                <a href="{{ route('tenant.invitations.index', $tenant) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection