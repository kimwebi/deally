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
            $table->unsignedBigInteger('number')->nullable()->after('id');
        });

        $this->backfill();

        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('number')->nullable(false)->change();
            $table->unique('number');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->dropColumn('number');
        });
    }

    private function backfill(): void
    {
        $order = DB::connection()->getDriverName() === 'sqlite'
            ? 'datetime(created_at), rowid'
            : 'created_at';

        $ids = DB::table('tenants')
            ->whereNull('number')
            ->orderByRaw($order)
            ->pluck('id');

        $number = 0;

        DB::transaction(function () use ($ids, &$number): void {
            foreach ($ids as $id) {
                $number++;

                DB::table('tenants')->where('id', $id)->update(['number' => $number]);
            }
        });
    }
};
