<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('call_invitations') && Schema::hasColumn('call_invitations', 'transcription_notice')) {
            Schema::table('call_invitations', function (Blueprint $table) {
                $table->dropColumn('transcription_notice');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('call_invitations') && ! Schema::hasColumn('call_invitations', 'transcription_notice')) {
            Schema::table('call_invitations', function (Blueprint $table) {
                // Nullable on rollback: the original was not-null with a value
                // on every row, and a rollback cannot restore what was dropped.
                $table->text('transcription_notice')->nullable();
            });
        }
    }
};
