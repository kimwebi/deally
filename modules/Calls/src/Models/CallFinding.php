<?php

namespace Deally\Calls\Models;

use Database\Factories\CallFindingFactory;
use Deally\Proposals\Models\KnowledgeGap;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallFinding extends Model
{
    /** @use HasFactory<CallFindingFactory> */
    use HasFactory;

    public const TYPE_SIGNAL = 'signal';

    public const TYPE_RECOMMENDATION = 'recommendation';

    public const STATUS_NEW = 'new';

    public const STATUS_HELPFUL = 'helpful';

    public const STATUS_UNHELPFUL = 'unhelpful';

    protected $connection = 'deally';

    protected $fillable = [
        'call_id',
        'transcript_line_id',
        'type',
        'kind',
        'label',
        'body',
        'package',
        'source',
        'confidence',
        'provider',
        'status',
        'dedupe_key',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function transcriptLine(): BelongsTo
    {
        return $this->belongsTo(TranscriptLine::class);
    }

    public function gaps(): HasMany
    {
        return $this->hasMany(KnowledgeGap::class, 'call_finding_id');
    }

    public function isSignal(): bool
    {
        return $this->type === self::TYPE_SIGNAL;
    }

    /**
     * The findings shelf expects a specific card shape, so render the persisted
     * row into it rather than duplicating the mapping in the client.
     *
     * @return array<string, string|int>
     */
    public function toCard(): array
    {
        $confidence = $this->confidence ?? 0.0;

        return [
            'id' => $this->id,
            'type' => (string) $this->type,
            'kind' => (string) $this->kind,
            'role' => $this->isSignal() ? $this->signalRole() : (string) $this->kind,
            'label' => (string) $this->label,
            'body' => (string) $this->body,
            'package' => (string) $this->package,
            'source' => (string) $this->source,
            'confidence' => $confidence > 0 ? 'AI · '.round($confidence * 100).'%' : '',
            'status' => (string) $this->status,
        ];
    }

    protected function signalRole(): string
    {
        return match ($this->kind) {
            'objection' => 'objection',
            'competitor' => 'reference',
            'buying_signal' => 'ask',
            default => 'say',
        };
    }
}
