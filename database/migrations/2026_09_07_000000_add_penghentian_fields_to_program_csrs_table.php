<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_csrs', function (Blueprint $table) {
            $table->unsignedBigInteger('dihentikan_evaluasi_id')->nullable()->after('rekomendasi_intervensi');
            $table->decimal('persentase_tumbuh_terakhir', 5, 2)->nullable()->after('dihentikan_evaluasi_id');
            $table->text('alasan_penghentian')->nullable()->after('persentase_tumbuh_terakhir');
            $table->unsignedBigInteger('dihentikan_by')->nullable()->after('alasan_penghentian');
            $table->timestamp('dihentikan_at')->nullable()->after('dihentikan_by');
        });
    }

    public function down(): void
    {
        Schema::table('program_csrs', function (Blueprint $table) {
            $table->dropColumn([
                'dihentikan_evaluasi_id',
                'persentase_tumbuh_terakhir',
                'alasan_penghentian',
                'dihentikan_by',
                'dihentikan_at',
            ]);
        });
    }
};
