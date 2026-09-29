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

    /** The platform could not join, or the call could not be held at all. */
    public const STATUS_FAILED = 'failed';

    /** The meeting happened on the platform's side and nobody attended. */
    public const STATUS_NO_SHOW = 'no_show';

    public const SESSION_DISCOVERY = 'discovery';

    public const SESSION_SERVICE_REVIEW = 'service_review';

    public const SESSION_FOLLOW_UP = 'follow_up';

    /** Select value that means "type their title instead of picking one". */
    public const CONTACT_ROLE_OTHER = '__other__';

    /**
     * The customer contact roles a rep can pick from when scheduling a call.
     *
     * Stored as a plain string on the call; this list is the picker, not a
     * constraint, so a rep can always type a title that is not listed here.
     *
     * @return array<int, string>
     */
    public static function contactRoles(): array
    {
        return [
            'CEO',
            'CFO / Finance',
            'COO / Operations',
            'CTO',
            'Vice President',
            'Director',
            'Manager',
            'Procurement',
            'Solutions Admin',
            'IT Lead',
            'Legal',
            'Founder',
        ];
    }

    public const INVITATION_NOT_SENT = 'not_sent';

    public const INVITATION_SENT = 'sent';

    public const INVITATION_FAILED = 'failed';

    public const BOT_JOIN_NOT_REQUESTED = 'not_requested';

    public const BOT_JOIN_UNAVAILABLE = 'unavailable';

    public const BOT_JOIN_REQUESTED = 'requested';

    public const BOT_JOIN_JOINED = 'joined';

    public const BOT_JOIN_FAILED = 'failed';

    public const READINESS_HOT = 'hot';

    public const READINESS_WARM = 'warm';

    public const READINESS_COLD = 'cold';

    protected $connection = 'deally';

    protected $fillable = [
        'opportunity_id',
        'name',
        'session_type',
        'contact_id',
        'company',
        'date',
        'duration',
        'sentiment',
        'ai_sentiment',
        'readiness',
        'ai_readiness',
        'status',
        'started_at',
        'ended_at',
        'ended_reason',
        'failure_note',
        'ai_provider',
        'last_analyzed_at',
        'contact_name',
        'contact_role',
        'notes',
        'summary',
        'owner_user_id',
        'meeting_platform',
        'meeting_external_id',
        'meeting_join_url',
        'meeting_start_url',
        'bot_join_status',
        'bot_join_note',
        'invitation_status',
        'invited_at',
        'proposal_intent',
        'proposal_intent_note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_analyzed_at' => 'datetime',
            'invited_at' => 'datetime',
            'proposal_intent' => 'boolean',
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

    public function invitations(): HasMany
    {
        return $this->hasMany(CallInvitation::class)->orderBy('id');
    }

    /**
     * What the AI was paying attention to, kept after the live panel faded it.
     */
    public function ephemerals(): HasMany
    {
        return $this->hasMany(CallEphemeral::class)->orderBy('id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(CallCorrection::class)->orderBy('id');
    }

    public function flags(): HasMany
    {
        return $this->hasMany(CallFlag::class)->orderBy('id');
    }

    public function openFlags(): HasMany
    {
        return $this->hasMany(CallFlag::class)->where('status', CallFlag::STATUS_OPEN)->orderBy('id');
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

    /**
     * A call that went nowhere. No analysis ever runs on one of these, and the
     * transcript stays empty because there was nothing to transcribe.
     */
    public function wentUnattended(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_NO_SHOW], true);
    }

    public function hasOpenFlag(): bool
    {
        return $this->flags()->where('status', CallFlag::STATUS_OPEN)->exists();
    }

    /**
     * The sentiment in force, falling back to the AI's original read when no
     * correction has been made.
     */
    public function effectiveSentiment(): string
    {
        return (string) ($this->sentiment ?: $this->ai_sentiment ?: 'neutral');
    }

    public function effectiveReadiness(): string
    {
        return (string) ($this->readiness ?: $this->ai_readiness ?: 'warm');
    }

    public function sentimentWasCorrected(): bool
    {
        return filled($this->ai_sentiment) && $this->ai_sentiment !== $this->sentiment;
    }

    public function readinessWasCorrected(): bool
    {
        return filled($this->ai_readiness) && $this->ai_readiness !== $this->readiness;
    }
}
