<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill transaksi_csrs untuk program yang sudah didanai sebelum pencatatan
 * pendanaan diaktifkan.
 *
 * Kepemilikan program di Modul Investasi CSR dibaca lewat transaksi_csrs.
 * Program lama tidak punya barisnya, sehingga mitra CSR pendananya akan
 * tertolak 403 saat membuka Monitoring Proyek atau menghentikan pendanaan.
 *
 * PENTING: csr_id tidak bisa ditebak dari data yang ada. Migration ini hanya
 * mengisi otomatis bila di database HANYA ADA SATU mitra CSR. Bila lebih dari
 * satu, migration sengaja dilewati dan mencetak daftar mitra supaya pemetaan
 * dilakukan manual. Menebak di sini berarti memberi satu mitra kuasa untuk
 * menghentikan program milik mitra lain.
 */
return new class extends Migration
{
    /**
     * Status program yang berarti mitra CSR sudah berkomitmen mendanai.
     */
    private const STATUS_SUDAH_DIDANAI = [
        'Menunggu Pembayaran',
        'Disetujui',
        'Selesai',
        'Dihentikan',
    ];

    /**
     * Status program yang berarti dananya sudah cair.
     */
    private const STATUS_SUDAH_CAIR = [
        'Selesai',
        'Dihentikan',
    ];

    public function up(): void
    {
        $mitra = DB::table('csrs')->select('id', 'nama_perusahaan')->get();

        if ($mitra->isEmpty()) {
            $this->catat('Backfill transaksi_csrs dilewati: belum ada data pada tabel csrs.');

            return;
        }

        if ($mitra->count() > 1) {
            $daftar = $mitra
                ->map(fn ($m) => "  - csrs.id={$m->id} ({$m->nama_perusahaan})")
                ->implode(PHP_EOL);

            $this->catat(
                'Backfill transaksi_csrs dilewati: terdapat ' . $mitra->count() . ' mitra CSR, '
                . 'sehingga pendana tiap program tidak dapat ditentukan otomatis.' . PHP_EOL
                . 'Petakan manual lewat INSERT ke transaksi_csrs untuk mitra berikut:' . PHP_EOL
                . $daftar
            );

            return;
        }

        $csrId = $mitra->first()->id;

        $programs = DB::table('program_csrs')
            ->whereIn('status', self::STATUS_SUDAH_DIDANAI)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('transaksi_csrs')
                    ->whereColumn('transaksi_csrs.program_csr_id', 'program_csrs.id');
            })
            ->select('id', 'status', 'anggaran', 'created_at')
            ->get();

        if ($programs->isEmpty()) {
            $this->catat('Backfill transaksi_csrs dilewati: tidak ada program yang perlu diisi.');

            return;
        }

        $sekarang = now();

        $baris = $programs->map(fn ($program) => [
            'csr_id' => $csrId,
            'program_csr_id' => $program->id,
            'tanggal_pendanaan' => $program->created_at
                ? date('Y-m-d', strtotime($program->created_at))
                : $sekarang->toDateString(),
            'nominal' => $program->anggaran ?? 0,
            'status' => in_array($program->status, self::STATUS_SUDAH_CAIR, true)
                ? 'Dibayar'
                : 'Menunggu Pembayaran',
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ])->all();

        DB::table('transaksi_csrs')->insert($baris);

        $this->catat(
            'Backfill transaksi_csrs: ' . count($baris) . ' program dipetakan ke csrs.id=' . $csrId . '.'
        );
    }

    /**
     * Sengaja tidak dibalik.
     *
     * Baris hasil backfill tidak punya penanda yang membedakannya dari
     * pendanaan asli, sehingga menghapusnya berisiko membuang data pendanaan
     * yang sah. Bila memang perlu dibatalkan, hapus manual berdasarkan
     * program_csr_id yang diketahui.
     */
    public function down(): void
    {
        //
    }

    private function catat(string $pesan): void
    {
        echo PHP_EOL . $pesan . PHP_EOL;
    }
};
