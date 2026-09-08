<?php

namespace App\Support;

use App\Models\DonationProgram;
use App\Models\Evaluasi;
use App\Models\HasilMonitoringPetak;
use App\Models\Penugasan;
use App\Models\PetakUkur;
use App\Models\ProgramApbd;
use App\Models\ProgramCsr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Aturan siklus rehabilitasi P0-P4.
 *
 * P0 adalah penanaman awal, P1-P4 adalah monitoring tahunan. Program dinyatakan
 * tuntas hanya setelah evaluasi P4 memenuhi ambang batas tumbuh; sebelum itu
 * setiap siklus yang lolos hanya menunggu giliran periode berikutnya.
 *
 * Ketiga sumber dana memakai tabel program terpisah tanpa induk bersama, jadi
 * aturannya dikumpulkan di sini alih-alih diulang pada tiap controller.
 */
class SiklusProgram
{
    /** Urutan periode dari penanaman awal sampai serah terima. */
    public const PERIODE = ['P0', 'P1', 'P2', 'P3', 'P4'];

    /** Periode penutup siklus. */
    public const PERIODE_TERAKHIR = 'P4';

    /** Ambang batas persentase tumbuh agar sebuah periode dinyatakan lolos. */
    public const AMBANG_BATAS_TUMBUH = 75;

    public const STATUS_PELAKSANAAN = 'Pelaksanaan';
    public const STATUS_SIAP_MONITORING = 'Siap Monitoring';
    public const STATUS_MENUNGGU_EVALUASI = 'Menunggu Evaluasi';
    public const STATUS_TINDAK_LANJUT = 'Tindak Lanjut';
    public const STATUS_SELESAI_MONITORING = 'Selesai Monitoring';
    public const STATUS_TUNTAS = 'Selesai & Diserahterimakan';

    /** Peta tipe polimorfik penugasans/evaluasis ke kelas modelnya. */
    public const TIPE_PROGRAM = [
        'apbd' => ProgramApbd::class,
        'csr' => ProgramCsr::class,
        'donasi' => DonationProgram::class,
    ];

    /** Periode setelah $periode, atau null bila sudah di ujung siklus. */
    public static function berikutnya(?string $periode): ?string
    {
        $indeks = array_search(self::normalkan($periode), self::PERIODE, true);

        if ($indeks === false) {
            return self::PERIODE[1];
        }

        return self::PERIODE[$indeks + 1] ?? null;
    }

    /** Benar bila $periode adalah periode terakhir siklus. */
    public static function periodeTerakhir(?string $periode): bool
    {
        return self::normalkan($periode) === self::PERIODE_TERAKHIR;
    }

    /**
     * Menarik kode periode dari teks bebas.
     *
     * Data lama menyimpan periode dalam bentuk beragam - "P1", "Monitoring P2",
     * "Penanaman Awal (P0)" - sehingga perlu dinormalkan sebelum dibandingkan.
     */
    public static function normalkan(?string $nilai): string
    {
        if (!$nilai) {
            return self::PERIODE[0];
        }

        if (preg_match('/P\s*([0-4])/i', $nilai, $cocok)) {
            return 'P' . $cocok[1];
        }

        return self::PERIODE[0];
    }

    /** Kelas model program dari nilai penugasanable_type / evaluable_type. */
    public static function kelasDariTipe(?string $tipe): ?string
    {
        if (!$tipe) {
            return null;
        }

        foreach (self::TIPE_PROGRAM as $kelas) {
            if ($tipe === $kelas) {
                return $kelas;
            }
        }

        // Terima juga alias pendek supaya endpoint bisa memakai "apbd" / "csr".
        return self::TIPE_PROGRAM[strtolower($tipe)] ?? null;
    }

    /** Instance program dari pasangan tipe dan id, atau null bila tidak dikenal. */
    public static function program(?string $tipe, $id): ?Model
    {
        $kelas = self::kelasDariTipe($tipe);

        return $kelas ? $kelas::find($id) : null;
    }

    /** Label sumber dana untuk ditampilkan. */
    public static function sumberDana(?string $tipe): string
    {
        return match (self::kelasDariTipe($tipe)) {
            ProgramApbd::class => 'APBD',
            ProgramCsr::class => 'CSR',
            DonationProgram::class => 'Donasi',
            default => '-',
        };
    }

    /**
     * Menaikkan program ke periode berikutnya dan mengembalikannya ke antrean
     * Staff PDAS. Mengembalikan null bila program sudah tidak bisa naik lagi.
     */
    public static function naikkanPeriode(Model $program): ?string
    {
        $berikutnya = self::berikutnya($program->periode_aktif);

        if (!$berikutnya) {
            return null;
        }

        $program->forceFill([
            'periode_aktif' => $berikutnya,
            'status_siklus' => self::STATUS_SIAP_MONITORING,
        ])->save();

        return $berikutnya;
    }

    /**
     * Menutup satu periode setelah hasil evaluasinya diketahui.
     *
     * Lolos di P4 berarti program tuntas; lolos sebelum P4 hanya menunggu
     * giliran tahun berikutnya. Tidak lolos berarti wajib tindak lanjut dan
     * periode tidak naik sampai penyulaman beres.
     */
    public static function tutupPeriode(Model $program, float $persentaseTumbuh): string
    {
        $lolos = $persentaseTumbuh >= self::AMBANG_BATAS_TUMBUH;

        if (!$lolos) {
            $status = self::STATUS_TINDAK_LANJUT;
        } elseif (self::periodeTerakhir($program->periode_aktif)) {
            $status = self::STATUS_TUNTAS;
        } else {
            $status = self::STATUS_SELESAI_MONITORING;
        }

        $program->forceFill([
            'status_siklus' => $status,
            'siklus_terakhir_at' => now(),
        ])->save();

        return $status;
    }

    /**
     * Menebak periode yang sedang berjalan untuk program yang datanya dibuat
     * sebelum kolom periode_aktif ada. Dipakai migrasi backfill dan sebagai
     * jaring pengaman bila kolomnya masih kosong.
     */
    public static function tebakPeriodeBerjalan(string $tipe, $id): string
    {
        $dariEvaluasi = Evaluasi::where('evaluable_type', $tipe)
            ->where('evaluable_id', $id)
            ->pluck('periode_evaluasi');

        $dariPenugasan = Penugasan::where('penugasanable_type', $tipe)
            ->where('penugasanable_id', $id)
            ->pluck('periode_monitoring');

        $tertinggi = 0;

        foreach ($dariEvaluasi->merge($dariPenugasan) as $nilai) {
            $indeks = array_search(self::normalkan($nilai), self::PERIODE, true);
            if ($indeks !== false && $indeks > $tertinggi) {
                $tertinggi = $indeks;
            }
        }

        // Data lama sering tidak mengisi periode_monitoring sama sekali. Adanya
        // penugasan monitoring atau tindak lanjut sudah membuktikan program
        // melewati penanaman awal, jadi lantainya P1 dan bukan P0.
        if ($tertinggi === 0) {
            $adaMonitoring = Penugasan::where('penugasanable_type', $tipe)
                ->where('penugasanable_id', $id)
                ->whereIn('jenis_kegiatan', ['Monitoring', 'Tindak Lanjut'])
                ->exists();

            if ($adaMonitoring) {
                $tertinggi = 1;
            }
        }

        return self::PERIODE[$tertinggi];
    }

    /**
     * Seluruh petak ukur milik sebuah program.
     *
     * Petak ukur menempel pada penugasan Pelaksanaan Penanaman, sedangkan
     * penugasan Monitoring dan Tindak Lanjut memakai petak yang sama. Karena
     * itu pencariannya lewat program, bukan lewat satu penugasan.
     */
    public static function petakUkurProgram(string $tipe, $id): Collection
    {
        return PetakUkur::with('dataTanamans')
            ->whereIn(
                'penugasan_id',
                Penugasan::where('penugasanable_type', $tipe)
                    ->where('penugasanable_id', $id)
                    ->select('id')
            )
            ->get();
    }

    /**
     * Membekukan kondisi tanaman yang tercatat sekarang sebagai hasil periode
     * berjalan, lalu mengembalikan jumlah petak yang terekam.
     *
     * Kondisi tanaman disimpan per titik di data_tanamans dan terus diperbarui
     * penyuluh. Tanpa pembekuan ini angka P1 ikut berubah ketika titik yang
     * sama diukur lagi pada P2, sehingga perbandingan antar periode mustahil.
     *
     * Dasar hitungnya jumlah batang - kolom jumlah pada data_tanamans - agar
     * sama dengan angka yang ditampilkan halaman monitoring.
     */
    public static function rekamHasilPeriode(Penugasan $penugasan, ?string $periode = null): int
    {
        if (!$penugasan->penugasanable_type) {
            return 0;
        }

        $periode ??= self::periodePenugasan($penugasan);

        $petaks = self::petakUkurProgram(
            $penugasan->penugasanable_type,
            $penugasan->penugasanable_id
        );

        foreach ($petaks as $petak) {
            $rencana = 0;
            $tumbuh = 0;

            foreach ($petak->dataTanamans as $tanaman) {
                $jumlah = (int) ($tanaman->jumlah ?? 0);
                $rencana += $jumlah;

                $kondisi = strtolower((string) $tanaman->kondisi_tanaman);
                if (!str_contains($kondisi, 'mati') && !str_contains($kondisi, 'rusak')) {
                    $tumbuh += $jumlah;
                }
            }

            HasilMonitoringPetak::updateOrCreate(
                ['petak_ukur_id' => $petak->id, 'periode' => $periode],
                [
                    'penugasan_id' => $penugasan->id,
                    'rencana_tanaman' => $rencana,
                    'bibit_tumbuh' => $tumbuh,
                    'persentase_tumbuh' => $rencana > 0 ? round(($tumbuh / $rencana) * 100, 2) : 0,
                    'tinggi_rata' => $petak->eval_tinggi_rata,
                    'koordinat' => $petak->eval_koordinat,
                    'foto' => $petak->eval_foto,
                    'dicatat_at' => now(),
                ]
            );
        }

        return $petaks->count();
    }

    /**
     * Kapan siklus berjalan terakhir dinyatakan tuntas, untuk data yang dibuat
     * sebelum kolom siklus_terakhir_at ada.
     *
     * Diambil dari penugasan monitoring atau tindak lanjut terakhir yang sudah
     * selesai. Tanpa nilai ini penjadwal tidak punya titik hitung jeda dan
     * program lama tidak akan pernah naik periode.
     */
    public static function tebakSiklusTerakhirAt(string $tipe, $id)
    {
        return Penugasan::where('penugasanable_type', $tipe)
            ->where('penugasanable_id', $id)
            ->whereIn('jenis_kegiatan', ['Monitoring', 'Tindak Lanjut', 'Pelaksanaan Penanaman'])
            ->whereIn('status', ['Selesai', 'Monitoring Selesai'])
            ->max('updated_at');
    }

    /**
     * Periode yang berlaku untuk sebuah penugasan.
     *
     * Diambil dari periode_monitoring bila diisi; kalau tidak, dari periode
     * yang sedang berjalan pada programnya. Penugasan Pelaksanaan Penanaman
     * selalu P0, sedangkan Monitoring dan Tindak Lanjut minimal P1 karena
     * keduanya baru terjadi setelah penanaman.
     */
    public static function periodePenugasan(Penugasan $penugasan): string
    {
        if ($penugasan->jenis_kegiatan === 'Pelaksanaan Penanaman') {
            return self::PERIODE[0];
        }

        if ($penugasan->periode_monitoring) {
            $periode = self::normalkan($penugasan->periode_monitoring);

            if ($periode !== self::PERIODE[0]) {
                return $periode;
            }
        }

        $program = self::program($penugasan->penugasanable_type, $penugasan->penugasanable_id);
        $periode = self::normalkan($program?->periode_aktif);

        return $periode === self::PERIODE[0] ? self::PERIODE[1] : $periode;
    }

    /**
     * Menebak status siklus dari jejak penugasan program, untuk data yang
     * dibuat sebelum kolom status_siklus ada.
     */
    public static function tebakStatusSiklus(string $tipe, $id, string $periode): string
    {
        $dasar = Penugasan::where('penugasanable_type', $tipe)->where('penugasanable_id', $id);

        $tindakLanjutBerjalan = (clone $dasar)
            ->where('jenis_kegiatan', 'Tindak Lanjut')
            ->whereNotIn('status', ['Selesai', 'Monitoring Selesai', 'Dihentikan'])
            ->exists();

        if ($tindakLanjutBerjalan) {
            return self::STATUS_TINDAK_LANJUT;
        }

        $monitoringSelesai = (clone $dasar)
            ->where('jenis_kegiatan', 'Monitoring')
            ->whereIn('status', ['Selesai', 'Monitoring Selesai'])
            ->exists();

        if ($monitoringSelesai) {
            return self::periodeTerakhir($periode)
                ? self::STATUS_TUNTAS
                : self::STATUS_SELESAI_MONITORING;
        }

        $penanamanSelesai = (clone $dasar)
            ->where('jenis_kegiatan', 'Pelaksanaan Penanaman')
            ->whereIn('status', ['Selesai', 'Monitoring Selesai'])
            ->exists();

        return $penanamanSelesai ? self::STATUS_SIAP_MONITORING : self::STATUS_PELAKSANAAN;
    }

    /**
     * Riwayat rekap per periode untuk sebuah program: satu baris per periode
     * yang pernah diukur, dipakai timeline dan grafik perkembangan di frontend.
     */
    public static function riwayatPeriode(string $tipe, $id): array
    {
        $petakIds = Penugasan::where('penugasanable_type', $tipe)
            ->where('penugasanable_id', $id)
            ->join('petak_ukurs', 'petak_ukurs.penugasan_id', '=', 'penugasans.id')
            ->pluck('petak_ukurs.id');

        $hasil = HasilMonitoringPetak::whereIn('petak_ukur_id', $petakIds)
            ->orderBy('periode')
            ->get()
            ->groupBy('periode');

        // Evaluasi dan penugasan monitoring baru terjadi setelah penanaman, jadi
        // yang periodenya kosong dimasukkan ke P1 - bukan P0 - supaya hasil
        // monitoring lama tidak tampil sebagai bagian dari penanaman awal.
        $periodeMonitoring = function (?string $nilai): string {
            $periode = self::normalkan($nilai);

            return $periode === self::PERIODE[0] ? self::PERIODE[1] : $periode;
        };

        $evaluasi = Evaluasi::where('evaluable_type', $tipe)
            ->where('evaluable_id', $id)
            ->get()
            ->keyBy(fn ($e) => $periodeMonitoring($e->periode_evaluasi));

        $penugasan = Penugasan::where('penugasanable_type', $tipe)
            ->where('penugasanable_id', $id)
            ->whereIn('jenis_kegiatan', ['Monitoring', 'Tindak Lanjut'])
            ->get()
            ->groupBy(fn ($p) => $periodeMonitoring($p->periode_monitoring));

        $riwayat = [];

        foreach (self::PERIODE as $periode) {
            $baris = $hasil->get($periode);
            $eval = $evaluasi->get($periode);
            $tugas = $penugasan->get($periode);

            // Periode yang belum pernah disentuh sama sekali dilewati supaya
            // grafik tidak menggambar titik nol palsu.
            if (!$baris && !$eval && !$tugas) {
                continue;
            }

            $rencana = $baris?->sum('rencana_tanaman') ?? 0;
            $tumbuh = $baris?->sum('bibit_tumbuh') ?? 0;

            $persentase = $eval?->persentase_tumbuh;
            if ($persentase === null) {
                $persentase = $rencana > 0 ? round(($tumbuh / $rencana) * 100, 2) : null;
            }

            $riwayat[] = [
                'periode' => $periode,
                'label' => $periode === 'P0' ? 'Penanaman Awal (P0)' : "Monitoring {$periode}",
                'jumlah_petak' => $baris?->count() ?? 0,
                'rencana_tanaman' => $rencana,
                'bibit_tumbuh' => $tumbuh,
                'bibit_mati' => max($rencana - $tumbuh, 0),
                'persentase_tumbuh' => $persentase !== null ? (float) $persentase : null,
                'lolos_ambang_batas' => $persentase !== null
                    ? $persentase >= self::AMBANG_BATAS_TUMBUH
                    : null,
                'status_evaluasi' => $eval?->status,
                'tanggal_evaluasi' => $eval?->tanggal_selesai,
                'ada_tindak_lanjut' => (bool) $tugas?->contains(
                    fn ($p) => $p->jenis_kegiatan === 'Tindak Lanjut'
                ),
                'diukur_at' => $baris?->max('dicatat_at'),
            ];
        }

        return $riwayat;
    }
}
