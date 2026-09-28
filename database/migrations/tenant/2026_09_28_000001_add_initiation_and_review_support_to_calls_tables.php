<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* Which meeting platforms this company has turned on. The picker offers
           only these rows, and each one states plainly whether it is connected.
           A platform that cannot create a meeting must never be presented as
           though it can. */
        if (! Schema::hasTable('meeting_platforms')) {
            Schema::create('meeting_platforms', function (Blueprint $table) {
                $table->id();
                $table->string('key', 40)->unique();
                $table->string('name', 80);
                $table->string('icon', 8)->default('🎥');
                $table->boolean('enabled')->default(false);
                $table->boolean('connected')->default(false);
                $table->text('connection_note')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('calls')) {
            Schema::table('calls', function (Blueprint $table) {
                // Reported on, never branched on: a Service Review session follows
                // exactly the same start, guidance and review as any other call.
                if (! Schema::hasColumn('calls', 'session_type')) {
                    $table->string('session_type', 30)->default('discovery')->after('name');
                }
                /* A reference to a customer record, not a copy of the name. It points
                   at the tenant's own `contacts` table, so it is a plain id with no
                   foreign key — the same treatment every other tenant reference to a
                   central `users` row gets. */
                if (! Schema::hasColumn('calls', 'contact_id')) {
                    $table->unsignedBigInteger('contact_id')->nullable()->after('contact_role');
                }

                if (! Schema::hasColumn('calls', 'meeting_platform')) {
                    $table->string('meeting_platform', 40)->nullable()->after('contact_id');
                }
                if (! Schema::hasColumn('calls', 'meeting_external_id')) {
                    $table->string('meeting_external_id')->nullable()->after('meeting_platform');
                }
                // Only ever populated from a provider response. Never invented.
                if (! Schema::hasColumn('calls', 'meeting_join_url')) {
                    $table->string('meeting_join_url')->nullable()->after('meeting_external_id');
                }
                if (! Schema::hasColumn('calls', 'meeting_start_url')) {
                    $table->string('meeting_start_url')->nullable()->after('meeting_join_url');
                }

                if (! Schema::hasColumn('calls', 'bot_join_status')) {
                    $table->string('bot_join_status', 30)->default('not_requested')->after('meeting_start_url');
                }
                if (! Schema::hasColumn('calls', 'bot_join_note')) {
                    $table->text('bot_join_note')->nullable()->after('bot_join_status');
                }
                if (! Schema::hasColumn('calls', 'invitation_status')) {
                    $table->string('invitation_status', 30)->default('not_sent')->after('bot_join_note');
                }
                if (! Schema::hasColumn('calls', 'invited_at')) {
                    $table->timestamp('invited_at')->nullable()->after('invitation_status');
                }

                if (! Schema::hasColumn('calls', 'ended_reason')) {
                    $table->string('ended_reason', 30)->nullable()->after('ended_at');
                }
                if (! Schema::hasColumn('calls', 'failure_note')) {
                    $table->text('failure_note')->nullable()->after('ended_reason');
                }

                // The AI's read, kept beside the effective value so a correction can
                // show what was originally believed without a second row.
                if (! Schema::hasColumn('calls', 'ai_sentiment')) {
                    $table->string('ai_sentiment', 20)->nullable()->after('sentiment');
                }
                if (! Schema::hasColumn('calls', 'readiness')) {
                    $table->string('readiness', 20)->nullable()->after('ai_sentiment');
                }
                if (! Schema::hasColumn('calls', 'ai_readiness')) {
                    $table->string('ai_readiness', 20)->nullable()->after('readiness');
                }

                // Gates the Create Proposal button, which the spec allows only when
                // a proposal was the agreed next step.
                if (! Schema::hasColumn('calls', 'proposal_intent')) {
                    $table->boolean('proposal_intent')->default(false)->after('ai_readiness');
                }
                if (! Schema::hasColumn('calls', 'proposal_intent_note')) {
                    $table->string('proposal_intent_note')->nullable()->after('proposal_intent');
                }
            });
        }

        /* The invitation the customer actually receives, kept verbatim so a
           rep can see exactly what was promised about transcription. */
        if (! Schema::hasTable('call_invitations')) {
            Schema::create('call_invitations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('call_id')->constrained()->cascadeOnDelete();
                $table->string('channel', 20)->default('email');
                $table->string('recipient_name')->nullable();
                $table->string('recipient_email');
                $table->string('subject');
                $table->text('body');
                $table->text('transcription_notice');
                $table->string('status', 20)->default('pending');
                $table->text('delivery_error')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['call_id', 'id']);
            });
        }

        /* The ephemeral stream is a live-only surface, but its content has to
           outlive the six seconds it is visible for — otherwise the review
           cannot show what the AI was paying attention to at each moment. */
        if (! Schema::hasTable('call_ephemerals')) {
            Schema::create('call_ephemerals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('call_id')->constrained()->cascadeOnDelete();
                $table->foreignId('transcript_line_id')->nullable()->constrained('transcript_lines')->nullOnDelete();
                $table->string('kind', 20);
                $table->string('label', 80);
                $table->text('body');
                $table->string('source', 20)->nullable();
                $table->unsignedBigInteger('occurred_at_ms')->nullable();
                $table->timestamps();

                $table->index(['call_id', 'id']);
            });
        }

        /* Every correction keeps the AI's original value beside the agent's, so
           the corrections dataset can be trained on the difference. */
        if (! Schema::hasTable('call_corrections')) {
            Schema::create('call_corrections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('call_id')->constrained()->cascadeOnDelete();
                $table->string('field', 40);
                $table->string('target_type', 40)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('ai_value', 120)->nullable();
                $table->string('corrected_value', 120);
                $table->text('note')->nullable();
                /* `users` lives on the central connection, not the tenant's, so a
                   foreign key here would point at a table that does not exist in this
                   database. Every other tenant table records a user id the same way:
                   an indexed column and no constraint. */
                $table->unsignedBigInteger('corrected_by_user_id')->nullable()->index();
                $table->timestamps();

                $table->index(['call_id', 'field']);
            });
        }

        /* A deal-status flag raised on a call. It blocks the review task from
           closing until it is resolved, so it has to be first class. */
        if (! Schema::hasTable('call_flags')) {
            Schema::create('call_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('call_id')->constrained()->cascadeOnDelete();
                $table->string('kind', 40)->default('deal_risk');
                $table->string('status', 20)->default('open');
                $table->string('headline', 160);
                $table->text('rationale')->nullable();
                $table->text('resolution_note')->nullable();
                $table->unsignedTinyInteger('opportunity_stage_id')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['call_id', 'status']);
            });
        }

        /* A review task is about one specific call, not about a company name.
           Matching on the company string meant the first task for a company
           shadowed every later one, so "Open full review" could point at the
           wrong conversation and a flag on the newest call could not be known to
           block closing the task for the oldest. */
        if (Schema::hasTable('tasks') && ! Schema::hasColumn('tasks', 'call_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('call_id')->nullable()->after('linked_company');
                $table->index('call_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['call_id']);
            $table->dropColumn('call_id');
        });

        Schema::dropIfExists('call_flags');
        Schema::dropIfExists('call_corrections');
        Schema::dropIfExists('call_ephemerals');
        Schema::dropIfExists('call_invitations');
        Schema::dropIfExists('meeting_platforms');

        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn([
                'session_type',
                'contact_id',
                'meeting_platform',
                'meeting_external_id',
                'meeting_join_url',
                'meeting_start_url',
                'bot_join_status',
                'bot_join_note',
                'invitation_status',
                'invited_at',
                'ended_reason',
                'failure_note',
                'ai_sentiment',
                'readiness',
                'ai_readiness',
                'proposal_intent',
                'proposal_intent_note',
            ]);
        });
    }
};
