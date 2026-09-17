<?php

namespace Deally\Tasks\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $connection = 'deally';

    protected $fillable = [
        'title',
        'linked_company',
        'due_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'date',
        ];
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', 'closed')->whereNotNull('due_at')->where('due_at', '<', now()->startOfDay());
    }

    public function scopeTodo($query)
    {
        return $query->where('status', '!=', 'closed');
    }
}
