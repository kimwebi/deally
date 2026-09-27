<?php

namespace Deally\Calls\Services;

use Deally\Calls\Contracts\CallAssistant;
use Deally\Calls\Models\Call;
use Deally\Proposals\Models\KnowledgeEntry;

/**
 * Demo driver for the live call assistant.
 *
 * Returns deterministic, rule-based transcripts and solution cards so the
 * full two-layer (AI panel / Findings panel) flow works without a real
 * transcription or model provider. Selected by AssistantFactory only when no
 * provider key is configured outside of production, or when the driver is
 * explicitly pinned to "dummy".
 */
class DummyAssistant implements CallAssistant
{
    /**
     * @var string[]
     */
    protected array $script = [
        "We're currently on a legacy tool, but support has been really slow. We're open to looking at alternatives.",
        'Does your platform support HIPAA compliance and per-tenant data isolation?',
        'Your pricing looks higher than what we can approve this quarter — can you do better?',
        'We need SSO for security, native Slack integration, and a faster onboarding path.',
        'Honestly, our current setup mostly works, so it would need to be a clear improvement to switch.',
    ];

    /**
     * @return string[]
     */
    public function demoScript(): array
    {
        return $this->script;
    }

    /**
     * The demo call as a full customer/agent conversation, so ended calls
     * persist both sides of the exchange.
     *
     * @return array<int, array{string, string}>
     */
    public function conversation(): array
    {
        return [
            [
                "We're currently on a legacy tool, but support has been really slow. We're open to looking at alternatives.",
                'Understood — that slow support is exactly what we solve. We guarantee 99.9% uptime with 24/7 dedicated support, and we usually start with a two-week pilot so there is no risk moving off the legacy tool.',
            ],
            [
                'Does your platform support HIPAA compliance and per-tenant data isolation?',
                "Yes — we are HIPAA-compliant with per-tenant data isolation, so every customer's data stays fully separated. I can share our compliance sheet with your security team right away.",
            ],
            [
                'Your pricing looks higher than what we can approve this quarter — can you do better?',
                'I can put together a formal proposal within two days, and we typically start with a two-week pilot at a reduced rate so there is no risk in locking in.',
            ],
            [
                'We need SSO for security, native Slack integration, and a faster onboarding path.',
                'All covered — the Enterprise tier includes SAML SSO with Okta and Azure AD, native Slack integration, and an onboarding path we can complete together in under a week.',
            ],
            [
                'Honestly, our current setup mostly works, so it would need to be a clear improvement to switch.',
                'That is completely fair. If we could make one thing clearly better for your team — say support response time — would that be enough to justify a pilot?',
            ],
        ];
    }

    public function name(): string
    {
        return 'dummy';
    }

    public function transcribe(string $audio, string $filename): ?string
    {
        return null;
    }

    /**
     * Deterministic signal detection mirroring the scripted demo conversation.
     *
     * @param  array<int, array{id: int, speaker: string, is_agent: bool, text: string}>  $lines
     * @param  array<int, string>  $reported
     * @return array{signals: array<int, array<string, mixed>>, recommendations: array<int, array<string, mixed>>}
     */
    public function analyze(Call $call, array $lines, array $reported = []): array
    {
        $signals = [];
        $recommendations = [];

        foreach ($lines as $line) {
            if ($line['is_agent']) {
                continue;
            }

            $text = strtolower($line['text']);

            if ($this->isObjection($text)) {
                $signals[] = [
                    'kind' => 'objection',
                    'text' => 'Objection raised — price or switching risk.',
                    'quote' => $line['text'],
                    'confidence' => 0.8,
                    'source_line' => $line['id'],
                ];
            }

            if ($this->mentionsCompetitor($text)) {
                $signals[] = [
                    'kind' => 'competitor',
                    'text' => 'Competitor mentioned.',
                    'quote' => $line['text'],
                    'confidence' => 0.75,
                    'source_line' => $line['id'],
                ];
            }

            if (str_contains($text, 'need') || str_contains($text, 'looking for')) {
                $signals[] = [
                    'kind' => 'intent',
                    'text' => 'Stated requirement.',
                    'quote' => $line['text'],
                    'confidence' => 0.7,
                    'source_line' => $line['id'],
                ];
            }
        }

        $latest = collect($lines)->filter(fn (array $line): bool => ! $line['is_agent'])->last();

        if ($latest !== null) {
            $cards = $this->suggest($call, $latest['text']);

            foreach ($cards as $card) {
                $recommendations[] = [
                    'role' => $card['role'],
                    'label' => $card['label'],
                    'body' => $card['body'],
                    'package' => $card['package'],
                    'source' => $card['source'],
                    'confidence' => 0.9,
                    'source_line' => $latest['id'],
                ];
            }
        }

        return [
            'signals' => array_slice($signals, 0, 3),
            'recommendations' => array_slice($recommendations, 0, 2),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function suggest(Call $call, string $customerText): array
    {
        $cards = [];
        $text = strtolower($customerText);

        if ($this->isObjection($text)) {
            $cards[] = $this->objectionCard($text);
        }

        $cards[] = $this->answerCard($call, $customerText);

        if ($this->mentionsCompetitor($text)) {
            $cards[] = $this->referenceCard($text);
        }

        return array_slice($cards, 0, 2);
    }

    public function answer(Call $call, string $query): ?string
    {
        return $this->answerGlobal($query);
    }

    public function answerGlobal(string $query): ?string
    {
        $text = strtolower($query);

        if (str_contains($text, 'hipaa') || str_contains($text, 'compliance') || str_contains($text, 'security')) {
            return 'Yes — we offer HIPAA-compliant hosting with per-tenant data isolation. I can share our compliance sheet with your security team today.';
        }

        if (str_contains($text, 'price') || str_contains($text, 'cost') || str_contains($text, 'discount') || str_contains($text, 'budget')) {
            return 'I can put together a formal proposal within two days, and we typically start with a 2-week pilot at a reduced rate so there is no risk in locking in.';
        }

        if ($this->mentionsCompetitor($text)) {
            return 'We differentiate on real-time, in-call AI interventions rather than post-call coaching alone, and objection handling is a first-class feature rather than an afterthought.';
        }

        return 'Our implementation runs on per-tenant isolation, which keeps your data separate and makes SSO and native Slack integration straightforward to enable.';
    }

    /**
     * @return array<string, string>
     */
    protected function answerCard(Call $call, string $customerText): array
    {
        $entry = $this->matchKnowledgeBase($customerText);

        if ($entry === null) {
            return [
                'role' => 'ask',
                'label' => 'Ask this',
                'subtype' => '',
                'confidence' => '',
                'body' => 'No confident KB match yet. Ask a clarifying question: "Can you tell me more about what would make this a clear improvement for you?"',
                'package' => 'Clarify · add to gap queue',
                'source' => 'Knowledge base · no match',
            ];
        }

        return [
            'role' => 'say',
            'label' => 'Say this',
            'subtype' => '',
            'confidence' => 'High · 92%',
            'body' => $entry->description,
            'package' => $entry->title,
            'source' => 'Knowledge base · '.ucfirst($entry->type),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function objectionCard(string $customerText): array
    {
        $entry = KnowledgeEntry::query()->where('type', 'objection')->first();

        if ($entry === null) {
            return [
                'role' => 'objection',
                'label' => 'Objection · Ask',
                'subtype' => 'ask',
                'confidence' => '',
                'body' => 'No documented response for this pushback yet. Ask: "What specifically is driving that concern for you?" The objection is logged as a gap.',
                'package' => 'Log as gap · notify Solutions Lead',
                'source' => 'Knowledge base · no match',
            ];
        }

        return [
            'role' => 'objection',
            'label' => 'Objection · Reply',
            'subtype' => 'reply',
            'confidence' => 'High · 88%',
            'body' => $entry->description,
            'package' => $entry->title,
            'source' => 'Knowledge base · objection playbook',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function referenceCard(string $customerText): array
    {
        $entry = KnowledgeEntry::query()->where('type', 'competitor')->first();

        return [
            'role' => 'reference',
            'label' => 'Reference',
            'subtype' => '',
            'confidence' => '',
            'body' => 'Customer flagged a competitor. Context: '.($entry->description ?? 'no competitor brief in the KB yet.')
                .' No action needed right now — keep listening.',
            'package' => 'Competitor context',
            'source' => 'Knowledge base · competitor brief',
        ];
    }

    protected function isObjection(string $text): bool
    {
        return str_contains($text, 'price')
            || str_contains($text, 'expensive')
            || str_contains($text, 'cost')
            || str_contains($text, 'budget')
            || str_contains($text, 'discount')
            || str_contains($text, 'current setup')
            || str_contains($text, 'locked in')
            || str_contains($text, 'happy with');
    }

    protected function mentionsCompetitor(string $text): bool
    {
        return str_contains($text, 'cisco')
            || str_contains($text, 'dealmate')
            || str_contains($text, 'competitor')
            || str_contains($text, 'legacy');
    }

    protected function matchKnowledgeBase(string $text): ?KnowledgeEntry
    {
        $needle = strtolower($text);

        $preferred = collect(KnowledgeEntry::types())->map(
            fn (string $type): string => ucfirst($type)
        );

        return KnowledgeEntry::query()
            ->get()
            ->sortBy(fn (KnowledgeEntry $entry): int => $preferred->search($entry->type))
            ->first(fn (KnowledgeEntry $entry): bool => $this->entryCovers($entry, $needle));
    }

    protected function entryCovers(KnowledgeEntry $entry, string $needle): bool
    {
        $haystack = strtolower($entry->title.' '.$entry->description);

        return str_contains($needle, strtolower((string) $entry->type))
            || str_contains($haystack, $needle)
            || collect(explode(' ', $needle))->contains(
                fn (string $word): bool => strlen($word) > 4 && str_contains($haystack, $word)
            );
    }
}
