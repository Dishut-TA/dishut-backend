<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Header Laporan Dana
        Schema::create('laporan_danas', function (Blueprint $table) {
            $table->id();
            $table->string('sumber_dana');
            $table->unsignedBigInteger('program_id'); 
            $table->string('nama_program');
            $table->string('tahap')->default('Tahap 1');
            $table->date('tanggal_pengeluaran');
            $table->decimal('dana_disalurkan', 15, 2)->default(0);
            $table->decimal('dana_direalisasikan', 15, 2)->default(0);
            // Status: 'Menunggu Verifikasi', 'Terverifikasi', 'Revisi'
            $table->string('status')->default('Menunggu Verifikasi');
            $table->text('catatan')->nullable();
            
            $table->timestamps();
        });

        Schema::create('rincian_penggunaan_danas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_dana_id')->constrained('laporan_danas')->cascadeOnDelete();
            $table->string('kategori_kegiatan');
            $table->decimal('nominal', 15, 2);
            $table->string('bukti_transaksi_path')->nullable(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rincian_penggunaan_danas');
        Schema::dropIfExists('laporan_danas');
    }
};