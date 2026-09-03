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
        Schema::table('penugasans', function (Blueprint $table) {
            $table->string('periode_monitoring', 50)->nullable();
            $table->string('metode', 100)->nullable();
            $table->string('prioritas', 50)->nullable();
            $table->text('tujuan')->nullable();
            $table->string('lampiran_penugasan')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penugasans', function (Blueprint $table) {
            $table->dropColumn([
                'periode_monitoring',
                'metode',
                'prioritas',
                'tujuan',
                'lampiran_penugasan'
            ]);
        });
    }
};
