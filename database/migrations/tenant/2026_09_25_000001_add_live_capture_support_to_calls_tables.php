<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('status');
            $table->timestamp('ended_at')->nullable()->after('started_at');
            $table->string('ai_provider', 30)->nullable()->after('ended_at');
            $table->timestamp('last_analyzed_at')->nullable()->after('ai_provider');
        });

        Schema::table('transcript_lines', function (Blueprint $table) {
            $table->string('client_chunk_id', 64)->nullable()->after('sequence');
            $table->unsignedBigInteger('client_sequence')->nullable()->after('client_chunk_id');
            $table->unsignedBigInteger('started_at_ms')->nullable()->after('client_sequence');
            $table->unsignedInteger('duration_ms')->nullable()->after('started_at_ms');
            $table->boolean('is_final')->default(false)->after('duration_ms');
            $table->string('provider', 30)->nullable()->after('is_final');
        });

        // Idempotent chunk uploads: a retried request must not duplicate a line.
        Schema::table('transcript_lines', function (Blueprint $table) {
            $table->unique(['call_id', 'client_chunk_id'], 'transcript_lines_call_chunk_unique');
        });

        Schema::create('call_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transcript_line_id')->nullable()->constrained('transcript_lines')->nullOnDelete();
            $table->string('type', 20);
            $table->string('kind', 40);
            $table->string('label');
            $table->text('body');
            $table->string('package')->nullable();
            $table->string('source')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('status', 20)->default('new');
            $table->string('dedupe_key', 64);
            $table->timestamps();

            $table->unique(['call_id', 'dedupe_key']);
            $table->index(['call_id', 'type']);
        });

        Schema::table('knowledge_gaps', function (Blueprint $table) {
            $table->foreignId('call_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('transcript_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('call_finding_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_gaps', function (Blueprint $table) {
            $table->dropColumn(['call_id', 'transcript_line_id', 'call_finding_id']);
        });

        Schema::dropIfExists('call_findings');

        Schema::table('transcript_lines', function (Blueprint $table) {
            $table->dropUnique('transcript_lines_call_chunk_unique');
            $table->dropColumn([
                'client_chunk_id',
                'client_sequence',
                'started_at_ms',
                'duration_ms',
                'is_final',
                'provider',
            ]);
        });

        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'ended_at', 'ai_provider', 'last_analyzed_at']);
        });
    }
};
