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
        Schema::create('laporan_proyeks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('program_id');
            $table->string('program_type'); // polymorphic
            $table->date('tanggal_laporan')->nullable();
            $table->string('foto_kegiatan')->nullable(); // path to image
            $table->text('catatan')->nullable();
            $table->string('status', 50)->default('Pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_proyeks');
    }
};
