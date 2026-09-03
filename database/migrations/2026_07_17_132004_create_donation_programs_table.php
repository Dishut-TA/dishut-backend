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
        Schema::create('donation_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_result_id')->nullable()->constrained('analysis_results')->onDelete('set null'); 
            $table->foreignId('kth_id')->constrained('kths')->onDelete('cascade');
            $table->string('name', 150);
            $table->string('location', 150);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->integer('total_seeds_collected')->default(0);
            $table->integer('total_seeds_realized')->default(0);
            $table->enum('status', ['Menunggu Verifikasi', 'Menunggu Proses', 'Aktif', 'Ditolak', 'Selesai'])->default('Menunggu Verifikasi');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donation_programs');
    }
};
