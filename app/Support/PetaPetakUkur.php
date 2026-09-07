<?php

namespace App\Support;

use App\Models\DonationProgram;
use App\Models\PetakUkur;
use App\Models\ProgramApbd;
use App\Models\ProgramCsr;

/**
 * Membangun titik peta modul Pelaksanaan & Monitoring dari petak ukur.
 *
 * Koordinat diambil dari kolom polygon_data pada petak_ukurs, yang disimpan
 * penyuluh sebagai array [{lat, lng}, ...] saat menggambar batas petak.
 * Titik marker adalah centroid sederhana (rata-rata) dari simpul polygon.
 */
class PetaPetakUkur
{
    /**
     * Daftar titik peta seluruh petak ukur yang punya koordinat valid.
     *
     * @param array $filterJenisKegiatan Batasi ke jenis kegiatan tertentu; kosong berarti semua.
     * @return array<int, array<string, mixed>>
     */
    public static function titik(array $filterJenisKegiatan = []): array
    {
        $query = PetakUkur::with(['penugasan.penyuluh', 'penugasan.penugasanable']);

        if ($filterJenisKegiatan) {
            $query->whereHas('penugasan', function ($q) use ($filterJenisKegiatan) {
                $q->whereIn('jenis_kegiatan', $filterJenisKegiatan);
            });
        }

        $titik = [];

        foreach ($query->get() as $petak) {
            $centroid = self::centroid($petak->polygon_data);

            if (!$centroid) {
                continue;
            }

            $penugasan = $petak->penugasan;
            $program = $penugasan ? $penugasan->penugasanable : null;

            $titik[] = [
                'id' => $petak->id,
                'lat' => $centroid['lat'],
                'lng' => $centroid['lng'],
                'polygon' => self::polygonBersih($petak->polygon_data),
                'nama_lokasi' => $petak->nama,
                'luas' => (float) $petak->luas,
                'penugasan_id' => $penugasan ? $penugasan->id : null,
                'jenis_kegiatan' => $penugasan ? $penugasan->jenis_kegiatan : null,
                'status' => $penugasan ? $penugasan->status : null,
                'penyuluh' => $penugasan && $penugasan->penyuluh ? $penugasan->penyuluh->username : '-',
                'program' => self::namaProgram($program),
                'sumber_dana' => self::sumberDana($program),
                'desa' => self::desa($program),
                'persentase_tumbuh' => $petak->eval_persentase_tumbuh !== null
                    ? (float) $petak->eval_persentase_tumbuh
                    : null,
            ];
        }

        return $titik;
    }

    /**
     * Centroid sederhana dari simpul polygon.
     *
     * Menerima bentuk {lat, lng} maupun {latitude, longitude}, dan mengabaikan
     * simpul yang koordinatnya tidak berupa angka.
     */
    public static function centroid($polygon): ?array
    {
        $titik = self::polygonBersih($polygon);

        if (!$titik) {
            return null;
        }

        $jumlah = count($titik);

        return [
            'lat' => array_sum(array_column($titik, 'lat')) / $jumlah,
            'lng' => array_sum(array_column($titik, 'lng')) / $jumlah,
        ];
    }

    /**
     * Menormalkan polygon menjadi array [{lat, lng}] berisi angka saja.
     */
    private static function polygonBersih($polygon): array
    {
        if (!is_array($polygon)) {
            return [];
        }

        $bersih = [];

        foreach ($polygon as $simpul) {
            if (!is_array($simpul)) {
                continue;
            }

            $lat = $simpul['lat'] ?? $simpul['latitude'] ?? null;
            $lng = $simpul['lng'] ?? $simpul['longitude'] ?? null;

            if (!is_numeric($lat) || !is_numeric($lng)) {
                continue;
            }

            $bersih[] = ['lat' => (float) $lat, 'lng' => (float) $lng];
        }

        return $bersih;
    }

    private static function namaProgram($program): string
    {
        if ($program instanceof DonationProgram) {
            return $program->name ?? '-';
        }

        if ($program instanceof ProgramApbd || $program instanceof ProgramCsr) {
            return $program->nama_program ?? '-';
        }

        return '-';
    }

    private static function sumberDana($program): string
    {
        if ($program instanceof DonationProgram) {
            return 'Donasi';
        }

        if ($program instanceof ProgramApbd) {
            return 'APBD';
        }

        if ($program instanceof ProgramCsr) {
            return 'CSR';
        }

        return '-';
    }

    private static function desa($program): string
    {
        if ($program instanceof DonationProgram) {
            return $program->analysisResultZone->desa ?? ($program->location ?? '-');
        }

        if ($program instanceof ProgramApbd || $program instanceof ProgramCsr) {
            return $program->kth->desa_kelurahan ?? ($program->lokasi ?? '-');
        }

        return '-';
    }
}
