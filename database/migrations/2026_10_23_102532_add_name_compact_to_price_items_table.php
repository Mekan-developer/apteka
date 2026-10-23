<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every search condition gets an index, so search no longer checks every product of the upload:
     * the base name without spaces («ношпа» = «Но-шпа») and a prefix index for the consonant skeleton.
     */
    public function up(): void
    {
        Schema::table('price_items', function (Blueprint $table) {
            $table->text('name_compact')->default('')->after('base_name');
        });

        DB::statement("update price_items set name_compact = replace(base_name, ' ', '')");

        Schema::table('price_items', function (Blueprint $table) {
            $table->rawIndex('name_compact gin_trgm_ops', 'price_items_name_compact_trgm_index')->algorithm('gin');

            // LIKE 'нлгн%' needs text_pattern_ops under a non-C collation; it also serves equality.
            $table->dropIndex('price_items_name_skeleton_index');
            $table->rawIndex('name_skeleton text_pattern_ops', 'price_items_name_skeleton_prefix_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_items', function (Blueprint $table) {
            $table->dropIndex('price_items_name_skeleton_prefix_index');
            $table->index('name_skeleton');
            $table->dropIndex('price_items_name_compact_trgm_index');
            $table->dropColumn('name_compact');
        });
    }
};
