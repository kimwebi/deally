<?php

namespace Deally\Calls\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallQuery extends Model
{
    protected $connection = 'deally';

    protected $fillable = [
        'call_id',
        'prompt',
        'answer',
        'cards',
        'provider',
    ];

    protected function casts(): array
    {
        return [
            'cards' => 'array',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
