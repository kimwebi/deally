<?php

namespace Deally\Pipeline\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer account is owned by exactly one agent and belongs to exactly
 * one team (the owning agent's team). Deals inherit their ownership from the
 * customer — ownership is never stored on a deal.
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $connection = 'deally';

    protected $fillable = [
        'company',
        'contact_name',
        'contact_title',
        'owner_user_id',
        'team_id',
    ];

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class)->orderBy('is_primary', 'desc')->orderBy('name');
    }

    public function serviceReviews(): HasMany
    {
        return $this->hasMany(ServiceReviewSchedule::class)->orderByDesc('started_at');
    }

    /**
     * The active Service Review schedule, or null when the customer has none
     * (either never set up or deliberately ended).
     */
    public function activeServiceReview(): ?ServiceReviewSchedule
    {
        return $this->serviceReviews()
            ->where('status', ServiceReviewSchedule::STATUS_ACTIVE)
            ->first();
    }
}
