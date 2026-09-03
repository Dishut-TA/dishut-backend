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
        Schema::create('field_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('analysis_result_zones')->cascadeOnDelete();
            $table->string('nama_lokasi', 255);
            $table->enum('sumber_lokasi', ['Analisis CPI', 'Proposal CSR'])->default('Analisis CPI');
            $table->string('nama_penyuluh', 150);
            $table->text('kondisi_lahan')->nullable();
            $table->text('kondisi_vegetasi')->nullable();
            $table->text('kendala_lapangan')->nullable();
            $table->string('titik_koordinat_gps', 255)->nullable();
            $table->string('foto_lokasi_url', 255)->nullable();
            $table->text('catatan_peninjauan')->nullable();
            $table->enum('status_verifikasi', ['Belum', 'Terima', 'Tolak'])->default('Belum');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_validations');
    }
};
