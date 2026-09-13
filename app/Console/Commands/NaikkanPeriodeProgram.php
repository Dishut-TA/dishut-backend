<?php

namespace App\Console\Commands;

use App\Support\SiklusProgram;
use Illuminate\Console\Command;

/**
 * Menaikkan program ke periode monitoring berikutnya setelah jeda satu siklus.
 *
 * Di lapangan monitoring P2 dilakukan setahun setelah P1 dinyatakan selesai.
 * Perintah ini dijadwalkan harian (lihat routes/console.php) dan hanya
 * menyentuh program yang siklus berjalannya sudah tuntas serta jedanya sudah
 * terlampaui. Program yang masih menunggu tindak lanjut tidak ikut naik.
 */
class NaikkanPeriodeProgram extends Command
{
    protected $signature = 'program:naikkan-periode
                            {--bulan=12 : Jeda dalam bulan sejak siklus terakhir selesai}
                            {--kering : Hanya menampilkan program yang akan naik, tanpa mengubah data}';

    protected $description = 'Menaikkan periode program rehabilitasi yang siklusnya sudah tuntas (P0 sampai P4)';

    public function handle(): int
    {
        $bulan = max(0, (int) $this->option('bulan'));
        $kering = (bool) $this->option('kering');
        $batas = now()->subMonths($bulan);

        $jumlah = 0;

        foreach (SiklusProgram::TIPE_PROGRAM as $alias => $kelas) {
            $kandidat = $kelas::query()
                ->where('status_siklus', SiklusProgram::STATUS_SELESAI_MONITORING)
                ->where('periode_aktif', '!=', SiklusProgram::PERIODE_TERAKHIR)
                ->whereNotNull('siklus_terakhir_at')
                ->where('siklus_terakhir_at', '<=', $batas)
                ->get();

            foreach ($kandidat as $program) {
                $sebelum = $program->periode_aktif;

                if ($kering) {
                    $berikutnya = SiklusProgram::berikutnya($sebelum);
                    $this->line("  [kering] {$alias} #{$program->getKey()}: {$sebelum} -> {$berikutnya}");
                    $jumlah++;
                    continue;
                }

                $sesudah = SiklusProgram::naikkanPeriode($program);

                if ($sesudah) {
                    $this->info("  {$alias} #{$program->getKey()}: {$sebelum} -> {$sesudah}");
                    $jumlah++;
                }
            }
        }

        $this->info($jumlah > 0
            ? "Selesai. {$jumlah} program dinaikkan ke periode berikutnya."
            : 'Tidak ada program yang memenuhi syarat naik periode.');

        return self::SUCCESS;
    }
}
