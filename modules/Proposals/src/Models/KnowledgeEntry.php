<?php

namespace Deally\Proposals\Models;

use Database\Factories\KnowledgeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeEntry extends Model
{
    /** @use HasFactory<KnowledgeEntryFactory> */
    use HasFactory;

    protected $connection = 'deally';

    protected $fillable = [
        'type',
        'title',
        'description',
    ];

    /** @return string[] */
    public static function types(): array
    {
        return ['product', 'pricing', 'competitor', 'objection'];
    }
}
