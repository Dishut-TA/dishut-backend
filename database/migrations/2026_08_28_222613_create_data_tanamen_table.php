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
        Schema::create('data_tanamans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petak_ukur_id')->constrained('petak_ukurs')->onDelete('cascade');
            $table->foreignId('seed_id')->nullable()->constrained('seeds')->onDelete('set null');
            $table->integer('jumlah');
            $table->string('kondisi_tanaman')->nullable(); // e.g., Baik, Kurang Baik, dll
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_tanamans');
    }
};
