<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Search compares prices only within one dosage form. Products imported before this column existed
     * get it from `php artisan prices:refresh`.
     */
    public function up(): void
    {
        Schema::table('price_items', function (Blueprint $table) {
            $table->string('dosage_form', 20)->nullable()->after('dose');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_items', function (Blueprint $table) {
            $table->dropColumn('dosage_form');
        });
    }
};
