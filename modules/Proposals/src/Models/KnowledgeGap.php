<?php

namespace Deally\Proposals\Models;

use Database\Factories\KnowledgeGapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];
}
