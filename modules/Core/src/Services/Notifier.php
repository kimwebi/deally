<?php

namespace Deally\Core\Services;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use SaasFoundation\Models\User;

class Notifier
{
    public function notify(User|int|null $user, string $title, string $body, string $type = 'info', ?string $url = null): ?DatabaseNotification
    {
        if (is_int($user)) {
            $user = User::query()->find($user);
        }

        if ($user === null) {
            return null;
        }

        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => [
                'title' => $title,
                'body' => $body,
                'url' => $url,
            ],
        ]);
    }
}
