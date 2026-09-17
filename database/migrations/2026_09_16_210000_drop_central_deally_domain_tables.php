<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('transcript_lines');
        Schema::dropIfExists('knowledge_gaps');
        Schema::dropIfExists('knowledge_entries');
        Schema::dropIfExists('proposals');
        Schema::dropIfExists('calls');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('opportunities');
    }

    public function down(): void
    {
        //
    }
};
