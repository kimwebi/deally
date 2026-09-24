<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('company')->unique();
            $table->string('contact_name')->nullable();
            $table->string('contact_title')->nullable();
            $table->unsignedBigInteger('owner_user_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->timestamps();

            $table->index('owner_user_id');
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
