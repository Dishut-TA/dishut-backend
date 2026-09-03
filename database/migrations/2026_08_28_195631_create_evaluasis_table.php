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
        Schema::create('evaluasis', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_surat')->nullable();
            $table->date('tanggal_surat')->nullable();
            $table->morphs('evaluable'); // evaluable_id & evaluable_type (DonationProgram, ProgramApbd, ProgramCsr)
            $table->string('periode_evaluasi')->nullable(); // P0, P1, P2...
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->decimal('persentase_tumbuh', 5, 2)->nullable();
            $table->string('status')->default('Menunggu Pelaksanaan'); // Menunggu Pelaksanaan, Sedang Berjalan, Menunggu Verifikasi, Selesai, Tindak Lanjut, Dihentikan
            $table->string('file_surat_tugas')->nullable();
            $table->string('file_laporan')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluasis');
    }
};
