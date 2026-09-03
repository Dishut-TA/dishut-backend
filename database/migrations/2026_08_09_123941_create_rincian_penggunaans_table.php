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
        Schema::create('rincian_penggunaans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_dana_id')->constrained('laporan_danas')->cascadeOnDelete();
            $table->string('kategori_pengeluaran');
            $table->decimal('nominal', 15, 2);
            $table->string('bukti_transaksi_url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rincian_penggunaans');
    }
};
