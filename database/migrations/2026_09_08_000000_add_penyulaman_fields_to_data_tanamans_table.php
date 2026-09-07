<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom penyulaman pada data_tanamans.
 *
 * Halaman Tindak Lanjut penyuluh mendata titik tanaman mati yang perlu
 * disulam beserta realisasinya, tetapi sebelumnya tidak ada tempat menyimpan
 * status tersebut sehingga tampilannya terpaksa memakai data contoh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_tanamans', function (Blueprint $table) {
            $table->string('status_penyulaman')->nullable()->after('kondisi_tanaman');
            $table->integer('penyulaman_jumlah')->nullable()->after('status_penyulaman');
            $table->decimal('penyulaman_tinggi', 8, 2)->nullable()->after('penyulaman_jumlah');
            $table->string('penyulaman_foto')->nullable()->after('penyulaman_tinggi');
            $table->text('penyulaman_keterangan')->nullable()->after('penyulaman_foto');
            $table->timestamp('penyulaman_at')->nullable()->after('penyulaman_keterangan');
        });
    }

    public function down(): void
    {
        Schema::table('data_tanamans', function (Blueprint $table) {
            $table->dropColumn([
                'status_penyulaman',
                'penyulaman_jumlah',
                'penyulaman_tinggi',
                'penyulaman_foto',
                'penyulaman_keterangan',
                'penyulaman_at',
            ]);
        });
    }
};
