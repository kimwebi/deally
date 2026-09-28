<?php

namespace Deally\Calls\Contracts;

use Deally\Calls\Models\Call;

/**
 * A meeting platform DeAlly can create meetings on and admit a transcription
 * bot to.
 *
 * The interface exists so the *flow* around it is real and testable while the
 * provider calls stay honestly unimplemented. An implementation that cannot
 * complete an operation must return it as unavailable with a reason — never a
 * plausible-looking identifier or URL. A fabricated join link is worse than no
 * link at all, because a rep will believe the customer has it.
 */
interface MeetingPlatformConnector
{
    public function key(): string;

    /**
     * Whether this connector has the credentials it needs to do anything.
     */
    public function isConnected(): bool;

    /**
     * Plain-language reason this connector is not connected, for the UI.
     */
    public function connectionNote(): string;

    /**
     * Create the meeting.
     *
     * Both identifiers are nullable on purpose: a platform that is connected
     * but refuses the request returns nulls with a note rather than something
     * invented.
     *
     * @param  array{join_url?: string|null, start_url?: string|null, reason?: string}  $context
     * @return array{external_id: ?string, join_url: ?string, start_url: ?string, reason: ?string}
     */
    public function createMeeting(Call $call, array $context = []): array;

    /**
     * Ask the platform's transcription bot to join silently.
     *
     * @param  array{external_id: ?string, join_url: ?string}  $meeting
     * @return array{status: string, note: ?string}
     */
    public function requestBotJoin(Call $call, array $meeting): array;
}
