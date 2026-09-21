<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['opportunities', 'calls', 'tasks', 'proposals'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('owner_user_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['opportunities', 'calls', 'tasks', 'proposals'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('owner_user_id');
            });
        }
    }
};
