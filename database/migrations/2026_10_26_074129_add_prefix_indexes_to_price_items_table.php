<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The search box suggests names starting with what was typed: LIKE 'аз%' needs text_pattern_ops
     * under a non-C collation, and two letters are too short for the trigram indexes.
     */
    public function up(): void
    {
        Schema::table('price_items', function (Blueprint $table) {
            $table->rawIndex('base_name text_pattern_ops', 'price_items_base_name_prefix_index');
            $table->rawIndex('name_compact text_pattern_ops', 'price_items_name_compact_prefix_index');
            $table->rawIndex('active_ingredient text_pattern_ops', 'price_items_active_ingredient_prefix_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_items', function (Blueprint $table) {
            $table->dropIndex('price_items_base_name_prefix_index');
            $table->dropIndex('price_items_name_compact_prefix_index');
            $table->dropIndex('price_items_active_ingredient_prefix_index');
        });
    }
};
