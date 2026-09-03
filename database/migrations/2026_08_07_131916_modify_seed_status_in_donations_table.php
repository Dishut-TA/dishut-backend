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
    Schema::table('donations', function (Blueprint $table) {
        $table->string('seed_status', 50)->nullable()->change();
    });
}

public function down(): void
{
    Schema::table('donations', function (Blueprint $table) {
        $table->enum('seed_status', ['Pending', 'Verified'])->nullable()->change(); // Sesuaikan dengan enum lama jika rollback
    });
}
};
