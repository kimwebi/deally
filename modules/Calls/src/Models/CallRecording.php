<?php

namespace Deally\Calls\Models;

use Database\Factories\CallRecordingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallRecording extends Model
{
    /** @use HasFactory<CallRecordingFactory> */
    use HasFactory;

    public const SOURCE_AGENT = 'agent';

    public const SOURCE_CUSTOMER = 'customer';

    protected $connection = 'deally';

    protected $fillable = [
        'call_id',
        'source',
        'client_chunk_id',
        'client_sequence',
        'path',
        'mime',
        'started_at_ms',
        'duration_ms',
        'bytes',
    ];

    protected function casts(): array
    {
        return [
            'client_sequence' => 'integer',
            'started_at_ms' => 'integer',
            'duration_ms' => 'integer',
            'bytes' => 'integer',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function isAgent(): bool
    {
        return $this->source === self::SOURCE_AGENT;
    }
}
