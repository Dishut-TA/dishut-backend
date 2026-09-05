<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petak_ukurs', function (Blueprint $table) {
            $table->integer('eval_bibit_tumbuh')->nullable()->after('status');
            $table->decimal('eval_persentase_tumbuh', 5, 2)->nullable()->after('eval_bibit_tumbuh');
            $table->decimal('eval_tinggi_rata', 8, 2)->nullable()->after('eval_persentase_tumbuh');
            $table->string('eval_koordinat')->nullable()->after('eval_tinggi_rata');
            $table->string('eval_foto')->nullable()->after('eval_koordinat');
            $table->text('eval_keterangan')->nullable()->after('eval_foto');
            $table->timestamp('eval_at')->nullable()->after('eval_keterangan');
        });
    }

    public function down(): void
    {
        Schema::table('petak_ukurs', function (Blueprint $table) {
            $table->dropColumn(['eval_bibit_tumbuh', 'eval_persentase_tumbuh', 'eval_tinggi_rata', 'eval_koordinat', 'eval_foto', 'eval_keterangan', 'eval_at']);
        });
    }
};