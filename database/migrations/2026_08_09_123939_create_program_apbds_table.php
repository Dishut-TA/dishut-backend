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
        Schema::create('program_apbds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kth_id')->constrained('kths')->cascadeOnDelete();
            $table->string('nama_program');
            $table->text('deskripsi_rencana')->nullable();
            $table->unsignedBigInteger('anggaran'); 
            $table->integer('jumlah_bibit')->nullable();
            $table->float('target_luas_lahan')->default(0); 
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('status')->default('Menunggu Persetujuan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_apbds');
    }
};