<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_result_zones', function (Blueprint $table) {
            $table->foreignId('plant_recommendation_rule_id')
                ->nullable()
                ->after('rekomendasi_intervensi')
                ->constrained('plant_recommendation_rules')
                ->nullOnDelete();
            $table->json('rekomendasi_tanaman')->nullable()->after('plant_recommendation_rule_id');
            $table->text('rekomendasi_tanaman_alasan')->nullable()->after('rekomendasi_tanaman');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_result_zones', function (Blueprint $table) {
            $table->dropForeign(['plant_recommendation_rule_id']);
            $table->dropColumn([
                'plant_recommendation_rule_id',
                'rekomendasi_tanaman',
                'rekomendasi_tanaman_alasan',
            ]);
        });
    }
};
