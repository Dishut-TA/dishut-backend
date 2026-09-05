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
        Schema::table('data_tanamans', function (Blueprint $table) {
            $table->decimal('tinggi_tanaman', 8, 2)->nullable()->after('foto_url');
            $table->decimal('latitude', 10, 7)->nullable()->after('tinggi_tanaman');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_tanamans', function (Blueprint $table) {
            $table->dropColumn(['tinggi_tanaman', 'latitude', 'longitude']);
        });
    }
};