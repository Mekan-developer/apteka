<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A pharmacy writes several phones and often its address above the price table; they are read on import.
     * The single phone column was never filled, so its values (if any) just move into the list.
     */
    public function up(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->json('phones')->nullable()->after('name');
            $table->string('address')->nullable()->after('phones');
        });

        DB::statement('update pharmacies set phones = json_build_array(phone) where phone is not null');

        Schema::table('pharmacies', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }

    /**
     * Only the first phone survives a rollback.
     */
    public function down(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('name');
        });

        DB::statement('update pharmacies set phone = phones->>0 where phones is not null');

        Schema::table('pharmacies', function (Blueprint $table) {
            $table->dropColumn(['phones', 'address']);
        });
    }
};
