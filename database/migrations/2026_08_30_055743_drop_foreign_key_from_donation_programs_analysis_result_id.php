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
        Schema::table('donation_programs', function (Blueprint $table) {
            $table->dropForeign(['analysis_result_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donation_programs', function (Blueprint $table) {
            $table->foreign('analysis_result_id')->references('id')->on('analysis_results')->onDelete('set null');
        });
    }
};
