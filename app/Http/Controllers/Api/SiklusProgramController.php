<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SiklusProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Siklus rehabilitasi P0-P4 sebuah program.
 *
 * Menyediakan riwayat antar periode untuk timeline dan grafik perkembangan,
 * serta pemicu manual kenaikan periode. Di produksi kenaikan periode dijalankan
 * penjadwal (lihat App\Console\Commands\NaikkanPeriodeProgram); pemicu manual
 * dipakai Kepala Bidang PDAS ketika siklus perlu dijalankan lebih awal.
 */
class SiklusProgramController extends Controller
{
    /**
     * GET /api/program-siklus/{tipe}/{id}
     * Ringkasan siklus dan riwayat per periode.
     */
    public function show(string $tipe, $id): JsonResponse
    {
        return $this->ringkasan(SiklusProgram::kelasDariTipe($tipe), $id);
    }

    /**
     * GET /api/penugasan/{id}/siklus
     * Jalur pintas untuk halaman detail, yang hanya memegang id penugasan.
     */
    public function showByPenugasan($id): JsonResponse
    {
        $penugasan = \App\Models\Penugasan::find($id);

        if (!$penugasan) {
            return response()->json(['message' => 'Penugasan tidak ditemukan'], 404);
        }

        return $this->ringkasan($penugasan->penugasanable_type, $penugasan->penugasanable_id);
    }

    private function ringkasan(?string $kelas, $id): JsonResponse
    {
        $program = SiklusProgram::program($kelas, $id);

        if (!$program) {
            return response()->json(['message' => 'Program tidak ditemukan'], 404);
        }

        $kelas = SiklusProgram::kelasDariTipe($kelas);
        $periodeAktif = SiklusProgram::normalkan($program->periode_aktif);

        return response()->json([
            'data' => [
                'program_id' => $program->getKey(),
                'program_type' => $kelas,
                'nama_program' => $program->nama_program ?? $program->name ?? '-',
                'sumber_dana' => SiklusProgram::sumberDana($kelas),
                'periode_aktif' => $periodeAktif,
                'status_siklus' => $program->status_siklus,
                'siklus_terakhir_at' => $program->siklus_terakhir_at,
                'periode_terakhir' => SiklusProgram::PERIODE_TERAKHIR,
                'ambang_batas_tumbuh' => SiklusProgram::AMBANG_BATAS_TUMBUH,
                'bisa_naik_periode' => $this->alasanTidakBisaNaik($program) === null,
                'alasan_tidak_bisa_naik' => $this->alasanTidakBisaNaik($program),
                'riwayat' => SiklusProgram::riwayatPeriode($kelas, $program->getKey()),
            ],
        ]);
    }

    /**
     * POST /api/program-siklus/{tipe}/{id}/naikkan
     * Menaikkan program ke periode berikutnya secara manual.
     */
    public function naikkanPeriode(Request $request, string $tipe, $id): JsonResponse
    {
        $program = SiklusProgram::program($tipe, $id);

        if (!$program) {
            return response()->json(['message' => 'Program tidak ditemukan'], 404);
        }

        $alasan = $this->alasanTidakBisaNaik($program);
        if ($alasan) {
            return response()->json(['message' => $alasan], 422);
        }

        $sebelum = SiklusProgram::normalkan($program->periode_aktif);
        $sesudah = SiklusProgram::naikkanPeriode($program);

        return response()->json([
            'message' => "Program dinaikkan dari {$sebelum} ke {$sesudah} dan siap dimonitor kembali.",
            'data' => [
                'periode_sebelumnya' => $sebelum,
                'periode_aktif' => $sesudah,
                'status_siklus' => $program->status_siklus,
            ],
        ]);
    }

    /**
     * Alasan program belum boleh naik periode, atau null bila boleh.
     *
     * Aturannya sama dengan yang dipakai penjadwal, hanya tanpa syarat sudah
     * lewat satu tahun - itulah gunanya pemicu manual.
     */
    private function alasanTidakBisaNaik($program): ?string
    {
        if (SiklusProgram::periodeTerakhir($program->periode_aktif)) {
            return 'Program sudah berada di periode terakhir (P4) dan tidak bisa dinaikkan lagi.';
        }

        if ($program->status_siklus === SiklusProgram::STATUS_TUNTAS) {
            return 'Program sudah selesai dan diserahterimakan.';
        }

        if ($program->status_siklus !== SiklusProgram::STATUS_SELESAI_MONITORING) {
            return "Siklus berjalan belum tuntas (status saat ini: {$program->status_siklus}). "
                . 'Periode hanya bisa naik setelah monitoring dan tindak lanjutnya selesai.';
        }

        return null;
    }
}
