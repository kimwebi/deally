<?php

namespace Deally\Calls\Services;

use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallCorrection;
use Deally\Calls\Models\CallEphemeral;
use Deally\Calls\Models\CallFinding;
use Deally\Calls\Models\CallFlag;
use Deally\Proposals\Models\KnowledgeGap;
use Illuminate\Support\Collection;

/**
 * What a call-review task has to show in order to be reviewable.
 *
 * The task is the product's review surface, not a reminder. It carries the AI's
 * read so the rep can correct it, the objections the AI missed so the rep can
 * add them, the actions the call promised so nothing is quietly dropped, and
 * the deal-status flag that keeps the task open.
 *
 * Every list here is a decision, not a dump. "Missed actions" is derived from
 * what the call actually promised and what is still outstanding, because a list
 * of everything said in a call is not a list of anything missed.
 */
class CallReviewBrief
{
    public function __construct(
        public readonly Call $call,
        /** @var Collection<int, CallFinding> */
        public readonly Collection $findings,
        /** @var Collection<int, KnowledgeGap> */
        public readonly Collection $objections,
        /** @var Collection<int, KnowledgeGap> */
        public readonly Collection $gaps,
        /** @var Collection<int, CallFlag> */
        public readonly Collection $openFlags,
        /** @var Collection<int, CallFlag> */
        public readonly Collection $resolvedFlags,
        /** @var Collection<int, CallCorrection> */
        public readonly Collection $corrections,
        /** @var Collection<int, CallEphemeral> */
        public readonly Collection $ephemerals,
    ) {}

    /**
     * @param  array<int, int>  $callIds
     * @return Collection<int, self>
     */
    public static function forCalls(array $callIds): Collection
    {
        $callIds = array_values(array_unique(array_filter($callIds)));

        if ($callIds === []) {
            return collect();
        }

        $calls = Call::query()->whereIn('id', $callIds)->get();

        $findings = CallFinding::query()
            ->whereIn('call_id', $callIds)
            ->orderBy('id')
            ->get()
            ->groupBy('call_id');

        $gaps = KnowledgeGap::query()
            ->whereIn('call_id', $callIds)
            ->orderByDesc('id')
            ->get();

        $flags = CallFlag::query()
            ->whereIn('call_id', $callIds)
            ->orderBy('id')
            ->get()
            ->groupBy('call_id');

        $corrections = CallCorrection::query()
            ->whereIn('call_id', $callIds)
            ->orderBy('id')
            ->get()
            ->groupBy('call_id');

        $ephemerals = CallEphemeral::query()
            ->whereIn('call_id', $callIds)
            ->orderBy('id')
            ->get()
            ->groupBy('call_id');

        return $calls->mapWithKeys(fn (Call $call): array => [
            $call->id => new self(
                call: $call,
                findings: $findings->get($call->id, collect()),
                objections: $gaps->where('type', 'objection')->values(),
                gaps: $gaps->reject(fn (KnowledgeGap $gap): bool => $gap->type === 'objection')->values(),
                openFlags: $flags->get($call->id, collect())->where('status', CallFlag::STATUS_OPEN)->values(),
                resolvedFlags: $flags->get($call->id, collect())->where('status', '!=', CallFlag::STATUS_OPEN)->values(),
                corrections: $corrections->get($call->id, collect()),
                ephemerals: $ephemerals->get($call->id, collect()),
            ),
        ]);
    }

    /**
     * Commitments the call made that are still outstanding.
     *
     * Read from the gap log rather than the transcript, because a promise is only
     * useful if it is tracked; a sentence in a transcript is not a follow-up
     * anyone will be reminded about.
     *
     * @return array<int, array{id: int, text: string, type: string, source: ?string}>
     */
    public function missedActions(): array
    {
        return $this->gaps
            ->reject(fn (KnowledgeGap $gap): bool => $gap->status === 'resolved')
            ->map(fn (KnowledgeGap $gap): array => [
                'id' => $gap->id,
                'text' => $gap->text,
                'type' => (string) $gap->type,
                'source' => $gap->source,
            ])
            ->values()
            ->all();
    }

    /**
     * Objections the AI raised, plus any the rep added by hand.
     *
     * @return array<int, array{id: int, text: string, added_by_rep: bool}>
     */
    public function objectionLog(): array
    {
        $fromAi = $this->findings
            ->where('kind', 'objection')
            ->map(fn (CallFinding $finding): array => [
                'id' => $finding->id,
                'text' => $finding->body,
                // A rep-raised gap has no finding behind it, so anything that
                // only exists in the gap log was added during review.
                'added_by_rep' => false,
            ]);

        $fromRep = $this->objections
            ->map(fn (KnowledgeGap $gap): array => [
                'id' => $gap->id,
                'text' => $gap->text,
                'added_by_rep' => $gap->call_finding_id === null,
            ]);

        return $fromAi->concat($fromRep)->values()->all();
    }

    /**
     * The AI's read, with the rep's correction beside it.
     *
     * Both are shown. A correction that erased the original would leave no way
     * to tell whether the review improved the record or just changed it.
     *
     * @return array{sentiment: string, ai_sentiment: ?string, sentiment_corrected: bool, readiness: string, ai_readiness: ?string, readiness_corrected: bool}
     */
    public function reads(): array
    {
        return [
            'sentiment' => $this->call->effectiveSentiment(),
            'ai_sentiment' => $this->call->ai_sentiment ?: $this->call->sentiment,
            'sentiment_corrected' => $this->call->sentimentWasCorrected(),
            'readiness' => $this->call->effectiveReadiness(),
            'ai_readiness' => $this->call->ai_readiness ?: $this->call->readiness,
            'readiness_corrected' => $this->call->readinessWasCorrected(),
        ];
    }

    /**
     * What DeAlly said it heard, in the order it heard it.
     *
     * The live stream fades each card after a few seconds, which makes it a good
     * live surface and a useless record. These are the same cards, read back
     * from the rows the stream was written from, so a rep reviewing the call
     * later sees what the assistant was attending to at each moment instead of
     * reconstructing it from the transcript.
     *
     * @return array<int, array{text: string, line_id: ?int, at: ?string}>
     */
    public function heard(): array
    {
        return $this->ephemerals
            ->where('kind', CallEphemeral::KIND_HEARD)
            ->map(fn (CallEphemeral $ephemeral): array => [
                'text' => $ephemeral->body,
                'line_id' => $ephemeral->transcript_line_id,
                'at' => $ephemeral->created_at?->format('H:i:s'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{field: string, ai_value: ?string, corrected_value: string, note: ?string, at: ?string}>
     */
    public function correctionLog(): array
    {
        return $this->corrections
            ->map(fn (CallCorrection $correction): array => [
                'field' => $correction->field,
                'ai_value' => $correction->ai_value,
                'corrected_value' => $correction->corrected_value,
                'note' => $correction->note,
                'at' => $correction->created_at?->format('j M, g:ia'),
            ])
            ->all();
    }
}
