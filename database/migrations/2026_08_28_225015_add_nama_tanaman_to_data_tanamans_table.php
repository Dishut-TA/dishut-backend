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
        Schema::table('data_tanamans', function (Blueprint $table) {
            $table->string('nama_tanaman')->nullable()->after('seed_id');
            // Seed id is already a foreign key, but we need to make sure it's nullable if not already
            $table->unsignedBigInteger('seed_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_tanamans', function (Blueprint $table) {
            $table->dropColumn('nama_tanaman');
            $table->unsignedBigInteger('seed_id')->nullable(false)->change();
        });
    }
};
