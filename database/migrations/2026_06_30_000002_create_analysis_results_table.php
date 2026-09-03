<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel ini menyimpan hasil analisis CPI yang dikembalikan oleh Python service.
     * Satu project memiliki satu result (hasOne). Result berisi JSON penuh, URL peta, dan URL file.
     */
    public function up(): void
    {
        Schema::create('analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('analysis_projects')->cascadeOnDelete()->comment('FK ke analysis_projects');
            $table->string('job_id', 255)->comment('Job ID dari Python CPI Engine');
            $table->string('status', 50)->comment('Status hasil: completed, failed');

            // JSON payload penuh dari Python
            $table->longText('result_json')->nullable()->comment('Full response JSON dari Python CPI Engine');
            $table->longText('table_json')->nullable()->comment('Tabel zonal statistics JSON (tabel_lokasi_kritis.json)');
            $table->longText('metadata_json')->nullable()->comment('Metadata analisis JSON');

            // URL / Path file output
            $table->text('critical_geojson_url')->nullable()->comment('URL atau path GeoJSON peta kekritisan lahan');
            $table->text('map_html_url')->nullable()->comment('URL atau path HTML preview peta interaktif');
            $table->text('cpi_raster_url')->nullable()->comment('URL atau path file raster CPI score (GeoTIFF)');
            $table->text('class_raster_url')->nullable()->comment('URL atau path file raster kelas kekritisan (GeoTIFF)');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_results');
    }
};
