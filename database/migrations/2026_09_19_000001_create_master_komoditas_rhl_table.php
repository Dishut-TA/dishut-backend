<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('master_komoditas_rhl', function(Blueprint $table){
   $table->id();
   $table->string('nama_tanaman');
   $table->string('jenis')->nullable();
   $table->float('min_elevation')->nullable();
   $table->float('max_elevation')->nullable();
   $table->float('min_slope')->nullable();
   $table->float('max_slope')->nullable();
   $table->float('min_rainfall')->nullable();
   $table->float('max_rainfall')->nullable();
   $table->text('soil_match')->nullable();
   $table->timestamps();
  });
 }
 public function down(): void { Schema::dropIfExists('master_komoditas_rhl'); }
};
