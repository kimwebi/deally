<?php

namespace Deally\Tasks\Models;

use Database\Factories\TaskFactory;
use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallFlag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $connection = 'deally';

    protected $fillable = [
        'title',
        'assignee',
        'linked_company',
        'call_id',
        'due_at',
        'status',
        'owner_user_id',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    /**
     * Deal-status flags on this call's review that are still unresolved.
     *
     * Read through the call, not through the company name: a company with three
     * calls has three sets of flags, and only the ones belonging to the call
     * under review can hold its review task open.
     */
    public function unresolvedFlags()
    {
        if ($this->call_id === null) {
            return collect();
        }

        return CallFlag::query()
            ->where('call_id', $this->call_id)
            ->where('status', CallFlag::STATUS_OPEN)
            ->get();
    }

    /**
     * The reason this task cannot be closed yet, or null when it can.
     *
     * Kept as one sentence so the same wording is used in the task modal and on
     * the full review, rather than each inventing its own.
     */
    public function blockedReason(): ?string
    {
        $count = $this->unresolvedFlags()->count();

        if ($count === 0) {
            return null;
        }

        return $count === 1
            ? 'Resolve the deal-status flag on this call before closing the review: '.$this->unresolvedFlags()->first()->headline
            : "Resolve the {$count} deal-status flags on this call before closing the review.";
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', 'closed')->whereNotNull('due_at')->where('due_at', '<', now());
    }

    public function scopeTodo($query)
    {
        return $query->where('status', '!=', 'closed');
    }
}
