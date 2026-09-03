<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_apbds', function (Blueprint $table) {
            // Menambahkan kolom pilihan_intervensi setelah target_luas_lahan
            $table->string('pilihan_intervensi')->nullable()->after('target_luas_lahan');
        });
    }

    public function down(): void
    {
        Schema::table('program_apbds', function (Blueprint $table) {
            $table->dropColumn('pilihan_intervensi');
        });
    }
};