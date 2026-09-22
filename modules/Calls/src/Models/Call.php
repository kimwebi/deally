<?php

namespace Deally\Calls\Models;

use Database\Factories\CallFactory;
use Deally\Pipeline\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Call extends Model
{
    /** @use HasFactory<CallFactory> */
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    protected $connection = 'deally';

    protected $fillable = [
        'opportunity_id',
        'name',
        'company',
        'date',
        'duration',
        'sentiment',
        'status',
        'contact_name',
        'contact_role',
        'notes',
        'summary',
        'owner_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function transcriptLines(): HasMany
    {
        return $this->hasMany(TranscriptLine::class)->orderBy('sequence');
    }
}
