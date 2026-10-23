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
        Schema::create('drug_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('term');
            $table->string('synonym');
            $table->string('term_normalized');
            $table->string('synonym_normalized');
            $table->timestamps();

            $table->unique(['term_normalized', 'synonym_normalized']);
            $table->index('synonym_normalized');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drug_synonyms');
    }
};
