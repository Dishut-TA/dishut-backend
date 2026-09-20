<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_recommendation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('region_name', 255);
            $table->json('region_keywords')->nullable();
            $table->unsignedInteger('elevation_min')->nullable();
            $table->unsignedInteger('elevation_max')->nullable();
            $table->decimal('slope_min', 6, 2)->nullable();
            $table->decimal('slope_max', 6, 2)->nullable();
            $table->unsignedInteger('rainfall_min')->nullable();
            $table->unsignedInteger('rainfall_max')->nullable();
            $table->json('soil_keywords')->nullable();
            $table->text('land_condition')->nullable();
            $table->json('recommended_plants');
            $table->string('category', 150)->nullable();
            $table->text('rhl_function')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_recommendation_rules');
    }
};
