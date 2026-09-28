<?php

namespace Deally\Calls\Models;

use Database\Factories\CallFlagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A deal-status flag raised from a call.
 *
 * It blocks the review task from closing until it is resolved, so an unresolved
 * risk cannot quietly disappear into a completed checklist.
 */
class CallFlag extends Model
{
    /** @use HasFactory<CallFlagFactory> */
    use HasFactory;

    public const KIND_DEAL_RISK = 'deal_risk';

    public const STATUS_OPEN = 'open';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_ADJUSTED = 'adjusted';

    public const STATUS_DISMISSED = 'dismissed';

    protected $connection = 'deally';

    protected $fillable = [
        'call_id',
        'kind',
        'status',
        'headline',
        'rationale',
        'resolution_note',
        'opportunity_stage_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
