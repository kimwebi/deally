<?php

namespace Deally\Pipeline\Models;

use Database\Factories\ServiceReviewSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceReviewSession extends Model
{
    /** @use HasFactory<ServiceReviewSessionFactory> */
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_HELD = 'held';

    public const STATUS_CANCELLED = 'cancelled';

    protected $connection = 'deally';

    protected $fillable = [
        'schedule_id',
        'scheduled_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ServiceReviewSchedule::class, 'schedule_id');
    }

    /**
     * A scheduled session whose time has passed was missed unless it was
     * held or explicitly cancelled.
     */
    public function isMissed(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && $this->scheduled_at->isPast();
    }

    public function isUpcoming(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && $this->scheduled_at->isFuture();
    }
}
