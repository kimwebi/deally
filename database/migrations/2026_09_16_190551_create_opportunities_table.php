<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->nullable()->index();
            $table->string('company');
            $table->string('contact_name')->nullable();
            $table->string('contact_title')->nullable();
            $table->string('packages')->nullable();
            $table->string('stage')->default('discovery');
            $table->decimal('value', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
