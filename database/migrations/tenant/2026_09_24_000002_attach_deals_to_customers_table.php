<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->index();
        });

        // Every deal belongs to exactly one customer account. Backfill the
        // customer table from the companies already on the pipeline: the first
        // opportunity with an owner decides the account owner, and every deal
        // for that company inherits ownership through the customer. Ownerless
        // legacy deals stay unlinked (their customer is unknown).
        $companies = DB::table('opportunities')
            ->select('company')
            ->distinct()
            ->orderBy('company')
            ->pluck('company');

        foreach ($companies as $company) {
            $first = DB::table('opportunities')
                ->where('company', $company)
                ->orderBy('id')
                ->first();

            $ownerId = DB::table('opportunities')
                ->where('company', $company)
                ->whereNotNull('owner_user_id')
                ->orderBy('id')
                ->value('owner_user_id');

            if ($first === null || $ownerId === null) {
                continue;
            }

            $customerId = DB::table('customers')->insertGetId([
                'company' => $company,
                'contact_name' => $first->contact_name,
                'contact_title' => $first->contact_title,
                'owner_user_id' => $ownerId,
                'team_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('opportunities')
                ->where('company', $company)
                ->update(['customer_id' => $customerId]);
        }

        // Ownership lives on the customer, never on the deal itself. Drop the
        // deprecated owner column with its index — SQLite cannot drop a column
        // that an index still references.
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex('opportunities_owner_user_id_index');
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_user_id')->nullable()->index();
        });

        foreach (DB::table('customers')->get() as $customer) {
            DB::table('opportunities')
                ->where('customer_id', $customer->id)
                ->update(['owner_user_id' => $customer->owner_user_id]);
        }

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex('opportunities_customer_id_index');
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });

        Schema::dropIfExists('customers');
    }
};
