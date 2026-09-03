<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel ini menyimpan informasi project analisis lahan kritis.
     * Setiap project berisi path file yang di-upload user dan status proses kalkulasi CPI.
     */
    public function up(): void
    {
        Schema::create('analysis_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 100)->unique()->comment('Kode unik project, contoh: project_20260630_ab12cd34');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('User yang membuat project');
            $table->string('project_name', 255)->nullable()->comment('Nama deskriptif project');
            $table->string('status', 50)->default('uploaded')->comment('Status: uploaded, processing, completed, failed');

            // Path file yang di-upload
            $table->text('dem_path')->nullable()->comment('Path file DEM raster (GeoTIFF)');
            $table->text('landcover_path')->nullable()->comment('Path file tutupan lahan raster/shapefile ZIP');
            $table->text('rainfall_path')->nullable()->comment('Path file curah hujan raster');
            $table->text('soil_path')->nullable()->comment('Path file jenis tanah raster/shapefile ZIP');
            $table->text('das_path')->nullable()->comment('Path file batas DAS shapefile ZIP');
            $table->text('admin_path')->nullable()->comment('Path file batas wilayah administrasi ZIP/GeoJSON (opsional)');

            // Referensi ke Python Job
            $table->string('python_job_id', 255)->nullable()->comment('Job ID yang dikembalikan Python CPI Engine');
            $table->text('error_message')->nullable()->comment('Pesan error jika analisis gagal');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_projects');
    }
};
