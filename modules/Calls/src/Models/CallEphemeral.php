<?php

namespace Deally\Calls\Models;

use Database\Factories\CallEphemeralFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in the ephemeral stream, kept after it has faded from the panel.
 *
 * The stream is a live-only surface: cards hold for six seconds and then go.
 * Their content still has to be retrievable, otherwise the review cannot show
 * what the AI was paying attention to at each moment of the call.
 */
class CallEphemeral extends Model
{
    /** @use HasFactory<CallEphemeralFactory> */
    use HasFactory;

    public const KIND_HEARD = 'heard';

    public const KIND_DETECTED = 'detected';

    public const KIND_GAP = 'gap';

    public const KIND_ASKED = 'asked';

    public const KIND_OBJECTION = 'objection';

    protected $connection = 'deally';

    protected $fillable = [
        'call_id',
        'transcript_line_id',
        'kind',
        'label',
        'body',
        'source',
        'occurred_at_ms',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at_ms' => 'integer',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function transcriptLine(): BelongsTo
    {
        return $this->belongsTo(TranscriptLine::class);
    }
}
