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
}
