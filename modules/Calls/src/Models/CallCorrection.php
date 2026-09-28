<?php

namespace Deally\Calls\Models;

use Database\Factories\CallCorrectionFactory;
use Deally\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A correction an agent made to something the AI got wrong.
 *
 * The AI's original value is always kept beside the corrected one. The value of
 * the correction is the difference between the two, so dropping the original
 * would throw away the only part worth learning from.
 */
class CallCorrection extends Model
{
    /** @use HasFactory<CallCorrectionFactory> */
    use HasFactory;

    public const FIELD_SENTIMENT = 'sentiment';

    public const FIELD_READINESS = 'readiness';

    public const FIELD_COMPETITOR_TAG = 'competitor_tag';

    public const FIELD_OBJECTION_TAG = 'objection_tag';

    public const FIELD_GAP_CLASSIFICATION = 'gap_classification';

    protected $connection = 'deally';

    protected $fillable = [
        'call_id',
        'field',
        'target_type',
        'target_id',
        'ai_value',
        'corrected_value',
        'note',
        'corrected_by_user_id',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by_user_id');
    }
}
