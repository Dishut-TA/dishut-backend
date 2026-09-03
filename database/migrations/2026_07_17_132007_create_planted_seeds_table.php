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
        Schema::create('planted_seeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_program_id')->constrained('donation_programs')->onDelete('cascade');
            $table->foreignId('seed_id')->constrained('seeds')->onDelete('cascade');
            $table->integer('planted_quantity');
            $table->string('proof_path', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planted_seeds');
    }
};
