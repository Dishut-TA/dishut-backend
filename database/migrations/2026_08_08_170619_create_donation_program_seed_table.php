<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('donation_program_seed', function (Blueprint $table) {
            $table->id();
            
            // Foreign key ke tabel donation_programs
            $table->foreignId('donation_program_id')
                  ->constrained('donation_programs')
                  ->cascadeOnDelete();
                  
            // Foreign key ke tabel seeds
            $table->foreignId('seed_id')
                  ->constrained('seeds')
                  ->cascadeOnDelete();
                  
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('donation_program_seed');
    }
};