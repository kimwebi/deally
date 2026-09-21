<?php

namespace Deally\Proposals\Models;

use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use HasFactory;

    protected $connection = 'deally';

    protected $fillable = [
        'name',
        'company',
        'value',
        'status',
        'package',
        'quote',
        'line_items',
        'owner_user_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'line_items' => 'array',
        ];
    }
}
