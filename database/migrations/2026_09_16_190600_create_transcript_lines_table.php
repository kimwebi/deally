<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->string('speaker');
            $table->boolean('is_agent')->default(false);
            $table->text('text');
            $table->unsignedInteger('sequence')->default(0);
            $table->string('linked_type')->nullable();
            $table->string('linked_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_lines');
    }
};
