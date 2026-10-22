<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('status')->default('uploaded');
            $table->unsignedInteger('sheets_total')->default(0);
            $table->unsignedInteger('sheets_ok')->default(0);
            $table->unsignedInteger('rows_total')->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        // Only one upload takes part in search, so the database refuses a second active one.
        DB::statement("create unique index price_uploads_single_active_unique on price_uploads (status) where status = 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_uploads');
    }
};
