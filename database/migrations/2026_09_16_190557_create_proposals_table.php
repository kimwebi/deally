<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('company');
            $table->decimal('value', 12, 2)->default(0);
            $table->string('status')->default('draft');
            $table->string('package')->nullable();
            $table->text('quote')->nullable();
            $table->text('line_items')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
