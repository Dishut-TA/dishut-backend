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
        Schema::table('kths', function (Blueprint $table) {
            // $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('no_rekening', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kths', function (Blueprint $table) {
            // $table->dropForeign(['user_id']);
            // $table->dropColumn('user_id');
            $table->dropColumn('no_rekening');
        });
    }
};
