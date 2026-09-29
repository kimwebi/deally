<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * customers.team_id was declared unsignedBigInteger while teams use UUID
     * ids (teams.id, team_user.team_id). Every writer stores the team's UUID
     * string there — inline customer creation, the Add Customer modal, and
     * reassignment — which MySQL strict mode rejects with a 1265 data-truncated
     * error while SQLite silently accepts. The column now matches the id type
     * it actually holds.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->uuid('team_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->change();
        });
    }
};
