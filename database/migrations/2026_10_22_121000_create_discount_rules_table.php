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
        Schema::create('discount_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_upload_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->constrained();
            $table->string('type');
            $table->decimal('percent', 5, 2);
            $table->decimal('min_sum', 12, 2)->nullable();
            $table->text('raw_text');

            $table->index(['price_upload_id', 'pharmacy_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_rules');
    }
};
