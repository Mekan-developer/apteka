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
        Schema::create('price_upload_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_upload_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->constrained();
            $table->string('sheet_name');
            $table->unsignedSmallInteger('sheet_index');
            $table->string('status');
            $table->unsignedInteger('header_row')->nullable();
            $table->unsignedSmallInteger('name_col')->nullable();
            $table->unsignedSmallInteger('price_col')->nullable();
            $table->unsignedSmallInteger('price_discounted_col')->nullable();
            $table->unsignedInteger('rows_parsed')->default(0);
            $table->text('error')->nullable();
            $table->jsonb('preview_json')->nullable();
            $table->timestamps();

            $table->unique(['price_upload_id', 'sheet_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_upload_sheets');
    }
};
