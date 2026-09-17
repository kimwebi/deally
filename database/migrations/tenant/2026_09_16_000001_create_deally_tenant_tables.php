<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->string('contact_name')->nullable();
            $table->string('contact_title')->nullable();
            $table->string('packages')->nullable();
            $table->string('stage');
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });

        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('company');
            $table->date('date');
            $table->string('duration');
            $table->string('sentiment')->default('neutral');
            $table->string('contact_name')->nullable();
            $table->string('contact_role')->nullable();
            $table->text('notes')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('transcript_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->string('speaker');
            $table->boolean('is_agent')->default(false);
            $table->text('text');
            $table->unsignedInteger('sequence')->default(0);
            $table->string('linked_type')->nullable();
            $table->text('linked_text')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('linked_company')->nullable();
            $table->date('due_at')->nullable();
            $table->string('status')->default('todo');
            $table->timestamps();
        });

        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company');
            $table->unsignedBigInteger('value')->default(0);
            $table->string('status')->default('draft');
            $table->string('package')->nullable();
            $table->text('quote')->nullable();
            $table->json('line_items')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_gaps', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->text('text');
            $table->string('source')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_gaps');
        Schema::dropIfExists('knowledge_entries');
        Schema::dropIfExists('proposals');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('transcript_lines');
        Schema::dropIfExists('calls');
        Schema::dropIfExists('opportunities');
    }
};
