<?php

namespace Deally\Calls\Services;

use Deally\Calls\Contracts\MeetingPlatformConnector;
use Deally\Calls\Models\Call;
use Deally\Calls\Models\MeetingPlatform;

/**
 * The connector used when a platform is enabled but not connected.
 *
 * This is the default for every platform in the app today. It reports what it
 * cannot do, in the call's own words, so the initiation flow can be exercised
 * end to end without any of it pretending a meeting was created.
 */
class UnconnectedMeetingPlatform implements MeetingPlatformConnector
{
    public function __construct(private readonly MeetingPlatform $platform) {}

    public function key(): string
    {
        return $this->platform->key;
    }

    public function isConnected(): bool
    {
        return false;
    }

    public function connectionNote(): string
    {
        return $this->platform->connection_note
            ?: "{$this->platform->name} is enabled but not connected, so DeAlly cannot create meetings or admit a bot on it.";
    }

    public function createMeeting(Call $call, array $context = []): array
    {
        return [
            'external_id' => null,
            'join_url' => null,
            'start_url' => null,
            'reason' => $this->connectionNote(),
        ];
    }

    public function requestBotJoin(Call $call, array $meeting): array
    {
        return [
            'status' => Call::BOT_JOIN_UNAVAILABLE,
            'note' => $this->connectionNote(),
        ];
    }
}
