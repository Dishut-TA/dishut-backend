<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penugasans', function (Blueprint $table) {
            $table->decimal('persentase_tumbuh', 5, 2)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('penugasans', function (Blueprint $table) {
            $table->dropColumn('persentase_tumbuh');
        });
    }
};