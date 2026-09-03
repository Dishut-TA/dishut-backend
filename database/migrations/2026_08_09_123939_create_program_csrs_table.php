<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_csrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kth_id')->constrained('kths')->cascadeOnDelete();
            $table->string('nama_program');
            $table->text('deskripsi_rencana')->nullable();
            $table->string('lokasi')->nullable();
            $table->unsignedBigInteger('anggaran');
            $table->float('target_luas_lahan')->default(0);
            $table->integer('jumlah_bibit')->nullable();
            $table->string('jenis_tanaman')->nullable(); 
            $table->string('proposal_file_path')->nullable(); 
            $table->text('tanggapan_perusahaan')->nullable();
            $table->string('status')->default('Menunggu Verifikasi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_csrs');
    }
};