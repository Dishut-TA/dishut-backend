<?php

use App\Support\SiklusProgram;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Mengisi periode_aktif dan status_siklus untuk program yang sudah ada.
 *
 * Tanpa ini seluruh program lama akan terbaca sebagai P0 dan langsung dianggap
 * belum pernah dimonitor, padahal sebagian sudah menempuh P1. Periode dan
 * statusnya ditebak dari jejak penugasan dan evaluasi program - lihat
 * SiklusProgram::tebakPeriodeBerjalan() dan tebakStatusSiklus().
 *
 * Hanya baris yang masih memakai nilai bawaan yang disentuh, sehingga migrasi
 * ini aman dijalankan ulang dan tidak menimpa data yang sudah dikoreksi manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (SiklusProgram::TIPE_PROGRAM as $kelas) {
            $tabel = (new $kelas)->getTable();

            if (!Schema::hasTable($tabel) || !Schema::hasColumn($tabel, 'periode_aktif')) {
                continue;
            }

            $kelas::query()->chunkById(100, function ($programs) use ($kelas) {
                foreach ($programs as $program) {
                    if ($program->periode_aktif !== 'P0') {
                        continue;
                    }

                    $periode = SiklusProgram::tebakPeriodeBerjalan($kelas, $program->getKey());

                    $program->forceFill([
                        'periode_aktif' => $periode,
                        'status_siklus' => SiklusProgram::tebakStatusSiklus($kelas, $program->getKey(), $periode),
                        'siklus_terakhir_at' => SiklusProgram::tebakSiklusTerakhirAt($kelas, $program->getKey()),
                    ])->saveQuietly();
                }
            });
        }
    }

    public function down(): void
    {
        // Nilai hasil tebakan tidak dikembalikan; kolomnya sendiri dihapus oleh
        // migrasi yang menambahkannya.
    }
};
