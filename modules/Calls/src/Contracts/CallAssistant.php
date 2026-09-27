<?php

namespace Deally\Calls\Contracts;

use Deally\Calls\Models\Call;
use Deally\Calls\Services\AssistantProviderException;

interface CallAssistant
{
    /**
     * Short driver identifier persisted alongside call findings, e.g. "groq".
     */
    public function name(): string;

    /**
     * Transcribe a single captured audio chunk.
     *
     * Returns null when the provider produced no usable text, which is not
     * an error — it usually means the chunk was silence.
     *
     * @throws AssistantProviderException
     */
    public function transcribe(string $audio, string $filename): ?string;

    /**
     * Knowledge-grounded suggested replies for the customer's latest words.
     *
     * @return array<int, array<string, string>>
     */
    public function suggest(Call $call, string $customerText): array;

    /**
     * Structured analysis of a rolling window of transcript lines.
     *
     * @param  array<int, array{id: int, speaker: string, is_agent: bool, text: string}>  $lines
     * @param  array<int, string>  $reported  Finding bodies already surfaced for this call.
     * @return array{signals: array<int, array<string, mixed>>, recommendations: array<int, array<string, mixed>>}
     *
     * @throws AssistantProviderException
     */
    public function analyze(Call $call, array $lines, array $reported = []): array;

    public function answer(Call $call, string $query): ?string;

    public function answerGlobal(string $query): ?string;
}
