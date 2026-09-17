<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('feature_id')->constrained('features');
            $table->integer('usage')->default(0);
            $table->string('period');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'feature_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
