<?php

namespace Deally\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Notification types that get their own tab; everything else lands in General.
     *
     * @var array<int, string>
     */
    private const SPECIFIC_TABS = ['gap', 'call'];

    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->take(100)
            ->get();

        $tab = $this->resolveTab($request);

        $visible = match ($tab) {
            'general' => $notifications->reject(
                fn (DatabaseNotification $notification): bool => in_array($notification->type, self::SPECIFIC_TABS, true)
            ),
            'gap', 'call' => $notifications->where('type', $tab),
            default => $notifications,
        };

        return view('core::pages.notifications.index', [
            'notifications' => $notifications,
            'visible' => $visible,
            'tab' => $tab,
            'tabCounts' => $this->tabCounts($notifications),
        ]);
    }

    protected function resolveTab(Request $request): string
    {
        $tab = (string) $request->query('tab', 'all');

        if (! in_array($tab, ['all', 'general', ...self::SPECIFIC_TABS], true)) {
            return 'all';
        }

        return $tab;
    }

    /**
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return array<string, int>
     */
    protected function tabCounts(Collection $notifications): array
    {
        $general = $notifications->reject(
            fn (DatabaseNotification $notification): bool => in_array($notification->type, self::SPECIFIC_TABS, true)
        );

        return [
            'all' => $notifications->count(),
            'general' => $general->count(),
            'gap' => $notifications->where('type', 'gap')->count(),
            'call' => $notifications->where('type', 'call')->count(),
        ];
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
