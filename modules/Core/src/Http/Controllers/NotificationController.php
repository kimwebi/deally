<?php

namespace Deally\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->take(100)
            ->get();

        return view('core::pages.notifications.index', ['notifications' => $notifications]);
    }

    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $this->abortIfNotOwned($request, $notification);

        if ($notification->read()) {
            $notification->markAsUnread();
        } else {
            $notification->markAsRead();
        }

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('toast', 'All notifications marked as read.');
    }

    protected function abortIfNotOwned(Request $request, DatabaseNotification $notification): void
    {
        $user = $request->user();

        abort_unless(
            (string) $notification->notifiable_type === $user::class
            && (string) $notification->notifiable_id === (string) $user->getKey(),
            404
        );
    }
}
