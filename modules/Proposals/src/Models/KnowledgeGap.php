<?php

namespace Deally\Proposals\Models;

use Database\Factories\KnowledgeGapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Note: the optional call_id / transcript_line_id / call_finding_id columns
 * link a gap back to the call that produced it. Those relations are declared on
 * the Calls models instead, to keep the module dependency one-directional.
 */
class KnowledgeGap extends Model
{
    /** @use HasFactory<KnowledgeGapFactory> */
    use HasFactory;

    protected $connection = 'deally';

    protected $fillable = [
        'type',
        'text',
        'source',
        'status',
        'call_id',
        'transcript_line_id',
        'call_finding_id',
    ];

    /**
     * Lowercase a question and strip everything but letters and digits, so
     * wording variants that only differ by case, punctuation, or whitespace
     * ("Do you support HIPAA?" vs "do you support hipaa !") compare equal.
     */
    public static function canonicalText(string $text): string
    {
        $collapsed = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text));

        return trim((string) preg_replace('/\s+/u', ' ', $collapsed ?? ''));
    }
}
