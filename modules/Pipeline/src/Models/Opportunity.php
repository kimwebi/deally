<?php

namespace Deally\Pipeline\Models;

use Database\Factories\OpportunityFactory;
use Deally\Calls\Models\Call;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use HasFactory;

    protected $connection = 'deally';

    protected $fillable = [
        'company',
        'contact_name',
        'contact_title',
        'packages',
        'stage',
        'value',
    ];

    /** @return string[] */
    public static function stages(): array
    {
        return ['discovery', 'demo', 'negotiation', 'won'];
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }
}
