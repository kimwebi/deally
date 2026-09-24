<?php

namespace Deally\Pipeline\Models;

use Database\Factories\ServiceReviewScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's recurring Service Review cadence. Only one schedule is ever
 * active per customer: a new deal can re-trigger the setup prompt once the
 * active schedule has ended.
 */
class ServiceReviewSchedule extends Model
{
    /** @use HasFactory<ServiceReviewScheduleFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    protected $connection = 'deally';

    protected $fillable = [
        'customer_id',
        'cadence_days',
        'status',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'cadence_days' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ServiceReviewSession::class, 'schedule_id')->orderBy('scheduled_at');
    }
}
