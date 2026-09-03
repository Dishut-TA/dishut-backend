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
        $table->string('bast_path')->nullable()->after('seed_status');
        $table->string('proof_path')->nullable()->after('bast_path');
    });
}

public function down(): void
{
    Schema::table('donations', function (Blueprint $table) {
        $table->dropColumn(['bast_path', 'proof_path']);
    });
}
};
