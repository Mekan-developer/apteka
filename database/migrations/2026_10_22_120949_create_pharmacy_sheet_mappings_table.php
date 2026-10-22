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
        Schema::create('pharmacy_sheet_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('header_row');
            $table->unsignedSmallInteger('name_col');
            $table->unsignedSmallInteger('price_col');
            $table->unsignedSmallInteger('price_discounted_col')->nullable();
            $table->string('header_signature', 64);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pharmacy_sheet_mappings');
    }
};
