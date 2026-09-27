<?php

namespace Deally\Calls\Services;

use RuntimeException;

/**
 * Raised when a live call provider cannot fulfil a request.
 *
 * The message is intentionally generic: it is logged and surfaced as a
 * controlled HTTP status, so provider payloads and credentials must never
 * leak into a response body.
 */
class AssistantProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }
}
