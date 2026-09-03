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
        Schema::create('kths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_number', 50)->nullable();
            $table->string('cdk')->nullable();
            $table->string('kabupaten_kota')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('desa_kelurahan')->nullable();
            $table->string('nama'); 
            $table->string('ketua')->nullable();
            $table->string('jenis_usaha')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kths');
    }
};
