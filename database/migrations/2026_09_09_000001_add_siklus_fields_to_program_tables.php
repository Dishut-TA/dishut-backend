<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda siklus rehabilitasi P0-P4 pada ketiga tabel program.
 *
 * Kolom status yang sudah ada tidak dipakai ulang: pada program_apbds dan
 * program_csrs isinya status persetujuan/pendanaan, dan pada donation_programs
 * kolom itu berupa enum sehingga nilai baru tidak bisa ditambahkan tanpa
 * mengubah definisi kolomnya. Siklus monitoring karena itu memakai kolom
 * sendiri agar tidak menabrak filter yang sudah berjalan.
 */
return new class extends Migration
{
    private const TABEL = ['program_apbds', 'program_csrs', 'donation_programs'];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            if (!Schema::hasTable($tabel)) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) use ($tabel) {
                if (!Schema::hasColumn($tabel, 'periode_aktif')) {
                    // P0 = penanaman awal, P1-P4 = siklus monitoring tahunan.
                    $table->string('periode_aktif', 5)->default('P0');
                }

                if (!Schema::hasColumn($tabel, 'status_siklus')) {
                    // Pelaksanaan, Siap Monitoring, Menunggu Evaluasi,
                    // Tindak Lanjut, Selesai Monitoring, Selesai & Diserahterimakan.
                    $table->string('status_siklus', 50)->default('Pelaksanaan');
                }

                if (!Schema::hasColumn($tabel, 'siklus_terakhir_at')) {
                    // Waktu siklus berjalan dinyatakan tuntas; jadi dasar
                    // penjadwalan naik periode setahun berikutnya.
                    $table->timestamp('siklus_terakhir_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            if (!Schema::hasTable($tabel)) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) use ($tabel) {
                $kolom = array_values(array_filter(
                    ['periode_aktif', 'status_siklus', 'siklus_terakhir_at'],
                    fn ($nama) => Schema::hasColumn($tabel, $nama)
                ));

                if ($kolom) {
                    $table->dropColumn($kolom);
                }
            });
        }
    }
};
