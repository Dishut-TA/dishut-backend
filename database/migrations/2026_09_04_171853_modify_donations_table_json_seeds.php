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
            $table->dropForeign(['seed_id']);
            $table->dropColumn('seed_id');
            $table->dropColumn('seed_quantity');
            $table->json('seed_details')->nullable()->after('donor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn('seed_details');
            $table->foreignId('seed_id')->nullable()->constrained('seeds')->onDelete('cascade');
            $table->integer('seed_quantity')->nullable();
        });
    }
};
