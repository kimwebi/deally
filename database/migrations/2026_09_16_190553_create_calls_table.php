<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->nullable()->index();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
