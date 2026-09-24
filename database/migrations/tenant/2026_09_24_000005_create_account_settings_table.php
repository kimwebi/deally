<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('demand_pipeline_threshold')->default(150000);
            $table->timestamps();
        });

        // A tenant has a single row of account settings, keyed like the
        // retention settings row.
        DB::table('account_settings')->insert([
            'id' => 1,
            'demand_pipeline_threshold' => 150000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('account_settings');
    }
};
