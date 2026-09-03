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
        Schema::table('analysis_result_zones', function (Blueprint $table) {
            $table->enum('status_kelayakan', ['Belum Diverifikasi', 'Layak', 'Tidak Layak', 'Valid', 'Tidak Valid'])->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_result_zones', function (Blueprint $table) {
            $table->enum('status_kelayakan', ['Belum Diverifikasi', 'Layak', 'Tidak Layak'])->nullable()->default(null)->change();
        });
    }
};
