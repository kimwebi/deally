<?php

namespace Deally\Calls\Models;

use Database\Factories\CallFactory;
use Deally\Pipeline\Models\Opportunity;
use Deally\Proposals\Models\KnowledgeGap;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Call extends Model
{
    /** @use HasFactory<CallFactory> */
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    protected $connection = 'deally';

    protected $fillable = [
        'opportunity_id',
        'name',
        'company',
        'date',
        'duration',
        'sentiment',
        'status',
        'started_at',
        'ended_at',
        'ai_provider',
        'last_analyzed_at',
        'contact_name',
        'contact_role',
        'notes',
        'summary',
        'owner_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_analyzed_at' => 'datetime',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function transcriptLines(): HasMany
    {
        return $this->hasMany(TranscriptLine::class)->orderBy('sequence');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(CallFinding::class);
    }

    public function gaps(): HasMany
    {
        return $this->hasMany(KnowledgeGap::class);
    }

    /**
     * Captured audio windows in the order they were captured. The review page
     * plays these back in sequence, so the ordering is the contract rather than
     * a default.
     *
     * Ordered by the browser's window timestamp, not by `client_sequence`: each
     * source keeps its own sequence counter, so the two streams both start at 0
     * and ordering by it interleaves them arbitrarily. `id` breaks ties between
     * windows that share a timestamp.
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(CallRecording::class)->orderBy('started_at_ms')->orderBy('id');
    }

    /**
     * Questions the rep asked the assistant during the call, and the answers.
     */
    public function queries(): HasMany
    {
        return $this->hasMany(CallQuery::class)->orderBy('id');
    }

    /**
     * A call is replayable only when a window was actually stored. Seeded and
     * imported calls have a transcript but no audio.
     */
    public function isReplayable(): bool
    {
        return $this->recordings()->exists();
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
