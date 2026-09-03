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
        Schema::create('penugasans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penyuluh_id')->constrained('users')->onDelete('cascade');
            $table->string('jenis_kegiatan', 100); // e.g. 'Validasi Lokasi', 'Pelaksanaan Penanaman'
            $table->morphs('penugasanable'); // creates penugasanable_type and penugasanable_id
            $table->string('status', 50)->default('Menunggu'); // 'Menunggu', 'Berjalan', 'Selesai'
            $table->date('tanggal_penugasan')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('batas_waktu')->nullable();
            $table->text('arahan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penugasans');
    }
};
