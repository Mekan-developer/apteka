<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_upload_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->constrained();
            $table->text('raw_name');
            $table->text('name_normalized');
            $table->string('name_skeleton');
            $table->text('base_name');
            $table->text('active_ingredient')->nullable();
            $table->unsignedInteger('pack_count')->nullable();
            $table->string('dose')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('price_discounted', 12, 2)->nullable();
            $table->decimal('effective_price', 12, 2);
            $table->decimal('goods_discount_percent', 5, 2)->nullable();
            $table->text('discount_note')->nullable();
            $table->string('manufacturer')->nullable();
            $table->unsignedInteger('source_row');

            $table->index('price_upload_id');
            $table->index('name_skeleton');
            $table->rawIndex('name_normalized gin_trgm_ops', 'price_items_name_normalized_trgm_index')->algorithm('gin');
            $table->rawIndex('base_name gin_trgm_ops', 'price_items_base_name_trgm_index')->algorithm('gin');
            $table->rawIndex('active_ingredient gin_trgm_ops', 'price_items_active_ingredient_trgm_index')->algorithm('gin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_items');
    }
};
