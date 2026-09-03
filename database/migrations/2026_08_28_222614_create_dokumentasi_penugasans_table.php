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
        Schema::create('dokumentasi_penugasans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penugasan_id')->constrained('penugasans')->onDelete('cascade');
            $table->string('file_path');
            $table->string('keterangan')->nullable();
            $table->string('jenis_dokumentasi')->nullable(); // e.g., Foto Sebelum, Foto Sesudah, dll
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumentasi_penugasans');
    }
};
