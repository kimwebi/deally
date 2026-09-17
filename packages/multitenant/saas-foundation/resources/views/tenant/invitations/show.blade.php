@extends('layouts.app')
@php $pageTitle = 'Invitation - ' . $invitation->email; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <a href="{{ route('tenant.invitations.index', $tenant) }}">Invitations</a>
    <span>/</span>
    <span>{{ $invitation->email }}</span>
</div>

<div class="page-header">
    <h1>Invitation</h1>
    <div class="flex gap-2">
        @if($invitation->status === 'pending')
        <form method="POST" action="{{ route('tenant.invitations.resend', [$tenant, $invitation]) }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">Resend</button>
        </form>
        <form method="POST" action="{{ route('tenant.invitations.revoke', [$tenant, $invitation]) }}">
            @csrf
            <button type="submit" class="btn btn-danger btn-sm">Revoke</button>
        </form>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="list-group">
            <div class="list-group-item">
                <span class="text-muted">Email</span>
                <span>{{ $invitation->email }}</span>
            </div>
            <div class="list-group-item">
                <span class="text-muted">Role</span>
                <span>{{ $invitation->role?->name ?? 'Default' }}</span>
            </div>
            <div class="list-group-item">
                <span class="text-muted">Invited by</span>
                <span>{{ $invitation->inviter?->name ?? '—' }}</span>
            </div>
            <div class="list-group-item">
                <span class="text-muted">Status</span>
                <span class="badge {{ $invitation->status === 'pending' ? 'badge-primary' : ($invitation->status === 'accepted' ? 'badge-success' : 'badge-gray') }}">{{ ucfirst($invitation->status) }}</span>
            </div>
            <div class="list-group-item">
                <span class="text-muted">Created</span>
                <span>{{ $invitation->created_at->diffForHumans() }}</span>
            </div>
            <div class="list-group-item">
                <span class="text-muted">Expires</span>
                <span>{{ $invitation->expires_at->diffForHumans() }}</span>
            </div>
            @if($invitation->accepted_at)
            <div class="list-group-item">
                <span class="text-muted">Accepted</span>
                <span>{{ $invitation->accepted_at->diffForHumans() }}</span>
            </div>
            @endif
        </div>
        <div class="mt-4">
            <div class="form-label">Invitation link</div>
            <input type="text" readonly value="{{ route('invitations.accept', $invitation->token) }}" class="form-control mono">
        </div>
    </div>
</div>
@endsection