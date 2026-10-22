<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Price search matches drug names by trigram similarity.
     */
    public function up(): void
    {
        Schema::ensureExtensionExists('pg_trgm');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop extension if exists pg_trgm');
    }
};
