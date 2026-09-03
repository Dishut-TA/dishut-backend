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
        Schema::table('program_apbds', function (Blueprint $table) {
            $table->foreignId('analysis_result_zone_id')->nullable()->constrained('analysis_result_zones')->nullOnDelete();
        });

        Schema::table('program_csrs', function (Blueprint $table) {
            $table->foreignId('analysis_result_zone_id')->nullable()->constrained('analysis_result_zones')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_apbds_csrs', function (Blueprint $table) {
            //
        });
    }
};
