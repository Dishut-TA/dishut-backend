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
        Schema::create('petak_ukurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penugasan_id')->constrained('penugasans')->cascadeOnDelete();
            $table->string('nama');
            $table->decimal('luas', 8, 2)->default(0);
            $table->json('polygon_data');
            $table->enum('status', ['Selesai', 'Belum Dibuat'])->default('Selesai');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petak_ukurs');
    }
};
