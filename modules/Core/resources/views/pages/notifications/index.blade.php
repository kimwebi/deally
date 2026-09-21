@extends('core::layouts.app', [
    'pageTitle' => 'Notifications',
    'pageSub' => 'In-app alerts and updates',
])

@section('content')
<div class="list-page">
    <div class="list-header">
        <div>
            <div class="list-title">Notifications</div>
            <div class="list-subtitle">
                {{ auth()->user()->unreadNotifications()->count() }} unread · {{ $notifications->count() }} shown
            </div>
        </div>
        @if($notifications->isNotEmpty())
        <form method="POST" action="{{ route('deally.notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn-sm primary">Mark all read</button>
        </form>
        @endif
    </div>

    <div class="notif-list">
        @forelse ($notifications as $notification)
            <div class="notif-card {{ $notification->unread() ? 'accent' : '' }}">
                <span class="kb-accent" style="background: {{ $notification->unread() ? 'var(--accent)' : 'transparent' }}"></span>
                <div class="notif-card-main">
                    <div class="notif-card-top">
                        <span class="kb-tag">{{ $notification->data['type'] ?? 'DeAlly' }}</span>
                        @if($notification->unread())
                            <span class="status-pill pending">new</span>
                        @endif
                        <span class="notif-time">{{ $notification->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="notif-title">{{ $notification->data['title'] ?? 'Update' }}</div>
                    @if(! empty($notification->data['body']))
                        <div class="notif-text">{{ $notification->data['body'] }}</div>
                    @endif
                </div>
                @if($notification->unread())
                    <form method="POST" action="{{ route('deally.notifications.read', $notification) }}">
                        @csrf
                        <button type="submit" class="notif-mark">Mark as read</button>
                    </form>
                @else
                    <span class="status-pill read">✓ Read</span>
                @endif
            </div>
        @empty
            <div class="list-page" style="text-align:center; color:var(--text-3); padding: 40px 0;">
                You're all caught up — no notifications yet.
            </div>
        @endforelse
    </div>
</div>
@endsection
