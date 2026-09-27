<?php

namespace Deally\Calls\Services;

use Deally\Calls\Contracts\CallAssistant;
use InvalidArgumentException;

/**
 * Resolves the live call assistant driver from configuration.
 *
 * Outside of production an unconfigured install falls back to the demo driver
 * so the app stays usable. In production it does not: a missing key surfaces a
 * controlled provider error rather than silently showing fabricated AI output.
 */
class AssistantFactory
{
    public const DRIVERS = ['openai', 'groq', 'dummy'];

    public function make(): CallAssistant
    {
        return match ($this->driver()) {
            'dummy' => new DummyAssistant,
            'groq' => new GroqAssistant,
            default => new LiveAssistant,
        };
    }

    public function name(): string
    {
        return $this->make()->name();
    }

    public function usingDummy(): bool
    {
        return $this->make() instanceof DummyAssistant;
    }

    protected function driver(): string
    {
        $configured = strtolower(trim((string) config('services.live_ai.driver', 'auto')));

        if ($configured !== '' && $configured !== 'auto') {
            if (! in_array($configured, self::DRIVERS, true)) {
                throw new InvalidArgumentException(
                    'Unknown live call assistant driver ['.$configured.']. Expected one of: '.implode(', ', self::DRIVERS).'.'
                );
            }

            return $configured;
        }

        foreach (['groq', 'openai'] as $provider) {
            if (filled(config('services.'.$provider.'.key'))) {
                return $provider;
            }
        }

        // Never fabricate AI output in production: prefer reporting a missing
        // credential over silently running the deterministic demo.
        return app()->isProduction() ? 'groq' : 'dummy';
    }
}
