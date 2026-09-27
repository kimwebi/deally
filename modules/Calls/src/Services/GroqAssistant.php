<?php

namespace Deally\Calls\Services;

/**
 * Groq-backed live call assistant.
 *
 * Uses the same OpenAI-compatible surface as the OpenAI driver, so only the
 * configured base URL, models, and token parameter differ. Credentials are
 * read from server-side config and never reach the browser.
 */
class GroqAssistant extends LiveAssistant
{
    public function __construct()
    {
        parent::__construct((array) config('services.groq', []));
    }

    public function name(): string
    {
        return 'groq';
    }
}
