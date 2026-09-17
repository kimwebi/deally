<?php

namespace Deally\Calls\Services;

use Deally\Calls\Models\Call;
use Deally\Proposals\Models\KnowledgeEntry;
use Illuminate\Support\Facades\Http;

class LiveAssistant
{
    public function transcribe(string $audio, string $filename): ?string
    {
        if ($this->missingConfig()) {
            return null;
        }

        $response = $this->client()
            ->attach('file', $audio, $filename)
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => config('services.openai.transcription_model'),
            ]);

        if ($response->failed()) {
            $response->throw();
        }

        $text = trim((string) $response->json('text'));

        return $text === '' ? null : $text;
    }

    /**
     * @return string[]
     */
    public function suggest(Call $call, string $customerText): array
    {
        if ($this->missingConfig()) {
            return [];
        }

        $response = $this->chat([
            [
                'role' => 'system',
                'content' => $this->systemPrompt($call),
            ],
            [
                'role' => 'user',
                'content' => 'The customer just said: "'.$customerText.'"'.PHP_EOL.PHP_EOL
                    .'Give the agent 2 short suggested replies, one per line, prefixed with "1. " and "2. ". Plain spoken english, no markdown, say the exact words the agent should say.',
            ],
        ]);

        if ($response === null) {
            return [];
        }

        return collect(explode("\n", $response))
            ->map(fn (string $line): string => trim((string) preg_replace('/^\s*\d+\.\s*/', '', $line)))
            ->filter()
            ->take(2)
            ->values()
            ->all();
    }

    public function answer(Call $call, string $query): ?string
    {
        if ($this->missingConfig()) {
            return null;
        }

        $recent = $call->transcriptLines()
            ->latest('sequence')
            ->limit(10)
            ->pluck('text')
            ->reverse()
            ->implode(' | ');

        $context = $this->knowledgeContext();
        $recentContext = $recent === '' ? '(no transcript yet)' : $recent;

        return $this->chat([
            [
                'role' => 'system',
                'content' => "You are DeAlly, a real-time sales assistant helping an agent on a call with {$call->company}."
                    .' You get the conversation so far and a knowledge base. Answer in plain spoken english with the exact words the agent should say to the customer.'
                    .' Be concise and accurate; if the knowledge base does not cover the question, say to confirm with the onboarding team.'.PHP_EOL.PHP_EOL
                    .'Knowledge base:'.PHP_EOL.$context.PHP_EOL.PHP_EOL
                    .'Conversation so far: '.$recentContext,
            ],
            [
                'role' => 'user',
                'content' => $query,
            ],
        ]);
    }

    protected function chat(array $messages): ?string
    {
        if ($this->missingConfig()) {
            return null;
        }

        $response = $this->client()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.chat_model'),
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 200,
            ]);

        if ($response->failed()) {
            $response->throw();
        }

        $content = trim((string) $response->json('choices.0.message.content'));

        return $content === '' ? null : $content;
    }

    protected function client()
    {
        return Http::withToken((string) config('services.openai.key'))
            ->acceptJson()
            ->timeout(config('services.openai.timeout'));
    }

    protected function missingConfig(): bool
    {
        return blank(config('services.openai.key'));
    }

    protected function systemPrompt(Call $call): string
    {
        return 'You are DeAlly, a real-time sales assistant helping an agent on a call with '.$call->company.'.'
            .' Keep suggestions short, specific, and in plain spoken english, with the exact words the agent should say.'
            .' Use the knowledge base below; never invent facts.'.PHP_EOL.PHP_EOL
            .$this->knowledgeContext();
    }

    protected function knowledgeContext(): string
    {
        $entries = KnowledgeEntry::query()->get();

        if ($entries->isEmpty()) {
            return '(no knowledge base entries yet)';
        }

        return $entries->map(
            fn (KnowledgeEntry $entry): string => "[{$entry->type}] {$entry->title}: {$entry->description}"
        )->implode(PHP_EOL);
    }
}
