<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel opsional ini menyimpan breakdown tabular per unit wilayah administrasi.
     * Data diisi dari field `table` di response Python CPI Engine.
     * Berguna untuk query cepat per wilayah tanpa parse JSON besar.
     */
    public function up(): void
    {
        Schema::create('analysis_result_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained('analysis_results')->cascadeOnDelete()->comment('FK ke analysis_results');
            $table->string('zone_id', 100)->nullable()->comment('ID zona dari Python (zone_id)');

            // Data wilayah administrasi
            $table->string('provinsi', 255)->nullable();
            $table->string('kabupaten', 255)->nullable();
            $table->string('kecamatan', 255)->nullable();
            $table->string('desa', 255)->nullable();

            // Hasil analisis
            $table->decimal('skor_cpi', 8, 4)->nullable()->comment('Skor CPI rata-rata zona');
            $table->string('status_lahan_kritis', 100)->nullable()->comment('Klasifikasi: Sangat Kritis, Kritis, Agak Kritis, dll');
            $table->decimal('luas_ha', 12, 4)->nullable()->comment('Luas zona dalam hektar');

            // Skor per indikator (opsional, untuk analisis detail)
            $table->decimal('score_landcover', 8, 4)->nullable();
            $table->decimal('score_rainfall', 8, 4)->nullable();
            $table->decimal('score_soil', 8, 4)->nullable();
            $table->decimal('score_slope', 8, 4)->nullable();
            $table->decimal('slope_percent', 8, 4)->nullable()->comment('Kemiringan lereng rata-rata (%)');

            // Intervensi
            $table->text('alasan_skor')->nullable()->comment('Narasi alasan skor CPI');
            $table->text('riwayat_intervensi')->nullable()->comment('Riwayat intervensi sebelumnya');
            $table->text('rekomendasi_intervensi')->nullable()->comment('Rekomendasi intervensi berbasis rules');

            // Data KTH & Validation
            $table->string('cdk', 100)->nullable();
            $table->string('nama_kelompok', 150)->nullable();
            $table->string('ketua_kelompok', 100)->nullable();
            $table->enum('status_validasi_penyuluh', ['Belum', 'Sudah'])->default('Belum');
            $table->enum('status_kelayakan', ['Belum Diverifikasi', 'Layak', 'Tidak Layak'])->default('Belum Diverifikasi');

            $table->timestamps();

            // Index untuk query berdasarkan wilayah
            $table->index(['result_id', 'status_lahan_kritis']);
            $table->index(['kabupaten', 'kecamatan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_result_zones');
    }
};
