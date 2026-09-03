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
        Schema::create('seed_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seed_id')->constrained('seeds')->onDelete('cascade');
            $table->integer('min_height');
            $table->integer('max_height')->nullable();
            $table->integer('stock')->default(0);
            $table->decimal('price', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seed_specifications');
    }
};
