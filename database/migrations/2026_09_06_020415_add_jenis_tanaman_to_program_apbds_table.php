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
            $table->string('jenis_tanaman')->nullable()->after('pilihan_intervensi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_apbds', function (Blueprint $table) {
            $table->dropColumn('jenis_tanaman');
        });
    }
};
