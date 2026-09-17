@extends('layouts.app')
@php $pageTitle = 'Invitations'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <span>Invitations</span>
</div>

<div class="page-header">
    <h1>Invitations</h1>
    <a href="{{ route('tenant.invitations.create', $tenant) }}" class="btn btn-primary">Send Invitation</a>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Invited By</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invitations as $invitation)
                    <tr>
                        <td>{{ $invitation->email }}</td>
                        <td>
                            @if($invitation->role)
                            <span class="badge badge-gray">{{ $invitation->role->name }}</span>
                            @else
                            <span class="text-muted">Default</span>
                            @endif
                        </td>
                        <td>{{ $invitation->inviter?->name ?? '—' }}</td>
                        <td>
                            @php
                            $badgeClass = match($invitation->status) {
                                'pending' => 'badge-primary',
                                'accepted' => 'badge-success',
                                'revoked' => 'badge-danger',
                                'expired' => 'badge-gray',
                                default => 'badge-gray',
                            };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ ucfirst($invitation->status) }}</span>
                        </td>
                        <td class="text-muted text-sm">
                            @if($invitation->status === 'pending')
                            {{ $invitation->expires_at->diffForHumans() }}
                            @else
                            —
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex gap-2" style="justify-content:flex-end;">
                                <a href="{{ route('tenant.invitations.show', [$tenant, $invitation]) }}" class="btn btn-secondary btn-sm">View</a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted" style="padding:32px;">No invitations.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($invitations->hasPages())
    <div class="card-footer">{{ $invitations->links() }}</div>
    @endif
</div>
@endsection