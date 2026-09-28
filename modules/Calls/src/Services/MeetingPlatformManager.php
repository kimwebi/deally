<?php

namespace Deally\Calls\Services;

use Deally\Calls\Contracts\MeetingPlatformConnector;
use Deally\Calls\Models\Call;
use Deally\Calls\Models\MeetingPlatform;
use Illuminate\Support\Collection;

/**
 * Resolves the connector for a platform key and runs the initiation flow.
 *
 * Every state this writes is a real state. `bot_join_status` reaches
 * `joined` only when a provider said the bot joined, and `meeting_join_url` is
 * only ever populated from a provider response — there is no code path that
 * assembles one, which is the whole point.
 */
class MeetingPlatformManager
{
    /**
     * @var array<string, MeetingPlatformConnector>
     */
    private array $connectors = [];

    /**
     * Register a real connector. Nothing registers one yet; see
     * `config/services.php` for what a connector needs before it can be added.
     */
    public function register(MeetingPlatformConnector $connector): void
    {
        $this->connectors[$connector->key()] = $connector;
    }

    /**
     * The platforms a rep may pick from: enabled, in display order.
     *
     * @return Collection<int, MeetingPlatform>
     */
    public function available()
    {
        return MeetingPlatform::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(?string $key): ?MeetingPlatform
    {
        if (blank($key)) {
            return null;
        }

        return MeetingPlatform::query()->where('key', $key)->first();
    }

    public function connectorFor(MeetingPlatform $platform): MeetingPlatformConnector
    {
        return $this->connectors[$platform->key] ?? new UnconnectedMeetingPlatform($platform);
    }

    /**
     * Create the meeting for a call, recording exactly what came back.
     *
     * @param  array{join_url?: string|null, start_url?: string|null}  $context
     * @return array{created: bool, meeting: array<string, ?string>}
     */
    public function createMeetingFor(Call $call, MeetingPlatform $platform, array $context = []): array
    {
        $result = $this->connectorFor($platform)->createMeeting($call, $context);

        $call->forceFill([
            'meeting_platform' => $platform->key,
            'meeting_external_id' => $result['external_id'],
            'meeting_join_url' => $result['join_url'],
            'meeting_start_url' => $result['start_url'],
        ])->save();

        return [
            'created' => $result['external_id'] !== null || $result['join_url'] !== null,
            'meeting' => [
                'external_id' => $result['external_id'],
                'join_url' => $result['join_url'],
                'start_url' => $result['start_url'],
                'reason' => $result['reason'],
            ],
        ];
    }

    /**
     * Ask the platform's bot to join silently.
     *
     * @return array{status: string, note: ?string}
     */
    public function requestBotJoinFor(Call $call): array
    {
        $platform = $this->find($call->meeting_platform);

        if ($platform === null) {
            $note = 'No meeting platform was chosen for this call, so there is nothing for a bot to join.';

            $call->forceFill([
                'bot_join_status' => Call::BOT_JOIN_UNAVAILABLE,
                'bot_join_note' => $note,
            ])->save();

            return [
                'status' => Call::BOT_JOIN_UNAVAILABLE,
                'note' => $note,
            ];
        }

        $result = $this->connectorFor($platform)->requestBotJoin($call, [
            'external_id' => $call->meeting_external_id,
            'join_url' => $call->meeting_join_url,
        ]);

        $call->forceFill([
            'bot_join_status' => $result['status'],
            'bot_join_note' => $result['note'],
        ])->save();

        return $result;
    }

    /**
     * One line describing the platform's state, for the live-call header.
     */
    public function describe(Call $call): string
    {
        if (blank($call->meeting_platform)) {
            return 'No meeting platform';
        }

        $platform = $this->find($call->meeting_platform);

        if ($platform === null) {
            return $call->meeting_platform;
        }

        $state = match ($call->bot_join_status) {
            Call::BOT_JOIN_JOINED => 'bot joined',
            Call::BOT_JOIN_REQUESTED => 'bot joining',
            Call::BOT_JOIN_FAILED => 'bot failed to join',
            default => 'bot not admitted',
        };

        return $platform->name.' · '.$state;
    }
}
