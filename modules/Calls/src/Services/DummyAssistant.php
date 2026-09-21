<?php

namespace Deally\Calls\Services;

use Deally\Calls\Models\Call;
use Deally\Proposals\Models\KnowledgeEntry;

/**
 * Demo driver for the live call assistant.
 *
 * Returns deterministic, rule-based transcripts and solution cards so the
 * full two-layer (AI panel / Findings panel) flow works without a real
 * transcription or model provider. Selected automatically whenever no
 * OpenAI key is configured.
 */
class DummyAssistant
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

    public function transcribe(Call $call, string $audio, string $filename): ?string
    {
        return $this->script[$call->transcriptLines()->count() % count($this->script)];
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
