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
        Schema::create('transaksi_csrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('csr_id')->constrained('csrs')->cascadeOnDelete();
            $table->foreignId('program_csr_id')->constrained('program_csrs')->cascadeOnDelete();
            $table->date('tanggal_pendanaan')->nullable();
            $table->decimal('nominal', 15, 2);
            $table->string('status')->default('Menunggu Pembayaran');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi_csrs');
    }
};
