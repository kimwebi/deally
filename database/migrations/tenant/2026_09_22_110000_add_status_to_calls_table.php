<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->string('status', 30)->default('scheduled')->after('date');
        });

        DB::table('calls')
            ->whereNotNull('duration')
            ->where('duration', '!=', '0m')
            ->update(['status' => 'completed']);
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
