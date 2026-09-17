<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('instance')->nullable()->unique()->after('id');
        });

        $position = 0;

        $tenants = DB::table('tenants')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id']);

        foreach ($tenants as $tenant) {
            DB::table('tenants')
                ->where('id', $tenant->id)
                ->update(['instance' => ++$position]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['instance']);
            $table->dropColumn('instance');
        });
    }
};
