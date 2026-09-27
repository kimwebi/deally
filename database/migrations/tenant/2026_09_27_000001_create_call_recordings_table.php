<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* Every captured window is kept, whether or not it transcribed. The
           browser already tells a rep that "the call is still being recorded"
           when the provider drops a window, so the audio has to outlive the
           request that failed. */
        Schema::create('call_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->string('client_chunk_id', 64);
            $table->unsignedBigInteger('client_sequence');
            $table->string('path');
            $table->string('mime', 60)->nullable();
            $table->unsignedBigInteger('started_at_ms')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('bytes');
            $table->timestamps();

            // A retried upload replaces its own window rather than adding one.
            $table->unique(['call_id', 'client_chunk_id'], 'call_recordings_call_chunk_unique');
            $table->index(['call_id', 'source', 'client_sequence'], 'call_recordings_playback_index');
        });

        /* What the rep asked the assistant mid-call, and what it answered. The
           live panel shows these as a chat; without a row the review page could
           only ever show the questions the customer asked. */
        Schema::create('call_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');
            $table->text('answer')->nullable();
            $table->json('cards')->nullable();
            $table->string('provider', 30)->nullable();
            $table->timestamps();

            $table->index(['call_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_queries');
        Schema::dropIfExists('call_recordings');
    }
};
