<?php

namespace Deally\Calls\Models;

use Database\Factories\TranscriptLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptLine extends Model
{
    /** @use HasFactory<TranscriptLineFactory> */
    use HasFactory;

    protected $connection = 'deally';

    protected $fillable = [
        'call_id',
        'speaker',
        'is_agent',
        'text',
        'sequence',
        'linked_type',
        'linked_text',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
