<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_review_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cadence_days')->default(30);
            $table->string('status')->default('active');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('service_review_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('service_review_schedules')->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('status')->default('scheduled');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['schedule_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_review_sessions');
        Schema::dropIfExists('service_review_schedules');
    }
};
