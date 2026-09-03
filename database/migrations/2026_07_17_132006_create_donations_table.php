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
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donation_program_id')->constrained('donation_programs')->onDelete('cascade');
            $table->foreignId('donor_id')->nullable()->constrained('donors')->onDelete('cascade'); 
            $table->foreignId('seed_id')->constrained('seeds')->onDelete('cascade');
            $table->integer('seed_quantity');
            $table->enum('seed_status', ['Pending', 'Collected', 'Distributed'])->default('Pending');
            $table->string('receipt_path', 255)->nullable();
            $table->string('certificate_path', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
