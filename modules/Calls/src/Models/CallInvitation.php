<?php

namespace Deally\Calls\Models;

use Database\Factories\CallInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The invitation the customer received, kept verbatim.
 *
 * Storing the exact body matters for one reason above all: it is the record of
 * what was told about transcription. A rep who later disputes consent can read
 * the promise that was actually made.
 */
class CallInvitation extends Model
{
    /** @use HasFactory<CallInvitationFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $connection = 'deally';

    protected $fillable = [
        'call_id',
        'channel',
        'recipient_name',
        'recipient_email',
        'subject',
        'body',
        'transcription_notice',
        'status',
        'delivery_error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function wasDelivered(): bool
    {
        return $this->status === self::STATUS_SENT;
    }
}
