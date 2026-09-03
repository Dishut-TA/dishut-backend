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
            $table->decimal('panjang_pu', 8, 2)->nullable()->after('luas_ha');
            $table->decimal('lebar_pu', 8, 2)->nullable()->after('panjang_pu');
            $table->integer('jumlah_pu')->nullable()->after('lebar_pu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_result_zones', function (Blueprint $table) {
            $table->dropColumn(['panjang_pu', 'lebar_pu', 'jumlah_pu']);
        });
    }
};
