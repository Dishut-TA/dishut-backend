<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat hasil monitoring per petak ukur per periode.
 *
 * Sebelum ini hasil pengukuran lapangan hanya disimpan pada kolom eval_* di
 * tabel petak_ukurs, dan kolom itu ditimpa setiap kali evaluasi baru dikirim.
 * Akibatnya angka P1 hilang begitu P2 diukur, sehingga siklus P0-P4 mustahil
 * dilaporkan. Tabel ini menyimpan tiap pengukuran sebagai baris tersendiri;
 * kolom eval_* tetap dipertahankan sebagai cuplikan pengukuran terakhir agar
 * halaman yang sudah ada tidak perlu diubah serentak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_monitoring_petaks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('petak_ukur_id')->constrained('petak_ukurs')->cascadeOnDelete();
            $table->foreignId('penugasan_id')->nullable()->constrained('penugasans')->nullOnDelete();
            $table->foreignId('evaluasi_id')->nullable()->constrained('evaluasis')->nullOnDelete();

            // P0 sampai P4. Disimpan sebagai string supaya sejalan dengan
            // periode_monitoring pada penugasans dan periode_evaluasi pada evaluasis.
            $table->string('periode', 10);

            // Jumlah tanaman yang direncanakan pada petak saat periode itu diukur,
            // disalin agar persentase lama tetap bisa dihitung ulang walau data
            // tanaman berubah di kemudian hari.
            $table->integer('rencana_tanaman')->default(0);
            $table->integer('bibit_tumbuh')->default(0);
            $table->decimal('persentase_tumbuh', 5, 2)->default(0);

            $table->decimal('tinggi_rata', 8, 2)->nullable();
            $table->string('koordinat')->nullable();
            $table->string('foto')->nullable();
            $table->text('keterangan')->nullable();

            $table->timestamp('dicatat_at')->nullable();
            $table->timestamps();

            // Satu petak hanya punya satu hasil resmi per periode; pengiriman
            // ulang memperbarui baris yang sama, bukan menambah duplikat.
            $table->unique(['petak_ukur_id', 'periode']);
            $table->index(['periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_monitoring_petaks');
    }
};
