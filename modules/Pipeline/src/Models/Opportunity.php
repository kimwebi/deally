<?php

namespace Deally\Pipeline\Models;

use Database\Factories\OpportunityFactory;
use Deally\Calls\Models\Call;
use Deally\Proposals\Models\Proposal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use HasFactory;

    protected $connection = 'deally';

    protected $fillable = [
        'customer_id',
        'company',
        'contact_name',
        'contact_title',
        'packages',
        'stage',
        'value',
        'lost_reason',
    ];

    /** @return string[] */
    public static function stages(): array
    {
        return ['discovery', 'demo', 'negotiation', 'won', 'lost'];
    }

    /**
     * Ownership is never stored on a deal — it is inherited from the customer
     * account the deal belongs to. This accessor keeps legacy owner reads
     * working while the source of truth lives on customers.
     */
    public function getOwnerUserIdAttribute(): ?int
    {
        return $this->customer?->owner_user_id;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    /**
     * Proposals follow deals by company name (the legacy link DeAlly kept —
     * proposals carry the customer company, not a customer id).
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'company', 'company');
    }
}
