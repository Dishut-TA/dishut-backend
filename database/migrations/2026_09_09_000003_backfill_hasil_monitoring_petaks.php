<?php

use App\Models\HasilMonitoringPetak;
use App\Models\Penugasan;
use App\Models\PetakUkur;
use App\Support\SiklusProgram;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Mengisi riwayat siklus dari data pengukuran yang sudah ada.
 *
 * Dua sumber dipakai, keduanya dilabeli periode yang sedang berjalan pada
 * programnya karena sebelum ini periode memang tidak pernah dicatat:
 *
 * 1. Cuplikan eval_* di petak_ukurs, bila pernah diisi lewat form evaluasi.
 * 2. Kondisi tanaman terkini di data_tanamans, untuk program yang monitoringnya
 *    sudah selesai. Ini hasil monitoring terakhir, jadi sah dilekatkan pada
 *    periode berjalan.
 *
 * Periode yang lebih lama memang sudah tertimpa sejak awal dan tidak bisa
 * dipulihkan. P0 sengaja tidak dibuat-buat: program lama tidak punya rekaman
 * kondisi saat penanaman, dan menebaknya hanya akan memalsukan titik awal
 * grafik. Program baru mendapat P0 dari PenugasanController::approvePelaksanaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hasil_monitoring_petaks')) {
            return;
        }

        $this->dariCuplikanEval();
        $this->dariKondisiTanaman();
    }

    /** Sumber 1: kolom eval_* pada petak_ukurs. */
    private function dariCuplikanEval(): void
    {
        if (!Schema::hasColumn('petak_ukurs', 'eval_at')) {
            return;
        }

        $periodeProgram = [];

        PetakUkur::with('penugasan')
            ->whereNotNull('eval_at')
            ->chunkById(100, function ($petaks) use (&$periodeProgram) {
                foreach ($petaks as $petak) {
                    $penugasan = $petak->penugasan;

                    if (!$penugasan || !$penugasan->penugasanable_type) {
                        continue;
                    }

                    $periode = $this->periodeProgram($periodeProgram, $penugasan);

                    HasilMonitoringPetak::updateOrCreate(
                        ['petak_ukur_id' => $petak->id, 'periode' => $periode],
                        [
                            'penugasan_id' => $petak->penugasan_id,
                            'rencana_tanaman' => $petak->dataTanamans()->count(),
                            'bibit_tumbuh' => (int) $petak->eval_bibit_tumbuh,
                            'persentase_tumbuh' => (float) $petak->eval_persentase_tumbuh,
                            'tinggi_rata' => $petak->eval_tinggi_rata,
                            'koordinat' => $petak->eval_koordinat,
                            'foto' => $petak->eval_foto,
                            'keterangan' => $petak->eval_keterangan,
                            'dicatat_at' => $petak->eval_at,
                        ]
                    );
                }
            });
    }

    /** Sumber 2: kondisi tanaman terkini, untuk monitoring yang sudah selesai. */
    private function dariKondisiTanaman(): void
    {
        $penugasans = Penugasan::whereIn('jenis_kegiatan', ['Monitoring', 'Tindak Lanjut'])
            ->whereIn('status', ['Selesai', 'Monitoring Selesai', 'Menunggu Evaluasi'])
            ->whereNotNull('penugasanable_type')
            ->orderBy('id')
            ->get();

        $sudah = [];

        foreach ($penugasans as $penugasan) {
            $periode = SiklusProgram::periodePenugasan($penugasan);
            $kunci = $penugasan->penugasanable_type . '#' . $penugasan->penugasanable_id . '#' . $periode;

            // Satu program cukup sekali per periode; penugasan Tindak Lanjut
            // berbagi periode dengan Monitoring-nya.
            if (isset($sudah[$kunci])) {
                continue;
            }
            $sudah[$kunci] = true;

            SiklusProgram::rekamHasilPeriode($penugasan, $periode);
        }
    }

    private function periodeProgram(array &$cache, Penugasan $penugasan): string
    {
        $kunci = $penugasan->penugasanable_type . '#' . $penugasan->penugasanable_id;

        $cache[$kunci] ??= SiklusProgram::tebakPeriodeBerjalan(
            $penugasan->penugasanable_type,
            $penugasan->penugasanable_id
        );

        // P0 belum punya pengukuran monitoring; cuplikan eval_* pasti berasal
        // dari siklus monitoring pertama.
        return $cache[$kunci] === 'P0' ? 'P1' : $cache[$kunci];
    }

    public function down(): void
    {
        // Baris hasil salinan dibiarkan; tabelnya dihapus oleh migrasi pembuatnya.
    }
};
