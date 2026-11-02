<?php

namespace App\Imports;

use App\Models\Kth;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Illuminate\Validation\ValidationException;

class KthImport implements ToCollection, WithHeadingRow, WithCustomCsvSettings
{
    /**
     * Header kolom default template KTH yang baku dan tidak boleh diubah-ubah.
     */
    public const EXPECTED_HEADERS = [
        'CABANG DINAS KEHUTANAN',
        'PROVINSI',
        'KAB/KOTA',
        'KECAMATAN',
        'DESA/KELURAHAN',
        'NAMA KELOMPOK',
        'KETUA KELOMPOK',
        'JENIS USAHA',
    ];

    protected string $delimiter;
    public int $importedCount = 0;

    public function __construct(string $delimiter = ',')
    {
        // Pertahankan casing & nama asli header (jangan di-slugify) agar bisa divalidasi persis
        HeadingRowFormatter::default('none');
        $this->delimiter = $delimiter;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => $this->delimiter,
        ];
    }

    /**
     * Proses collection data dari file Excel / CSV
     *
     * @param Collection $rows
     * @throws ValidationException
     */
    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => ['File tidak memiliki baris data atau kosong.']
            ]);
        }

        $firstRow = $rows->first();
        $actualHeaders = is_array($firstRow) ? array_keys($firstRow) : array_keys($firstRow->toArray());

        $headerErrors = self::validateHeaders($actualHeaders);
        if (!empty($headerErrors)) {
            $formattedMessage = "Format kolom file tidak sesuai template:\n• " . implode("\n• ", $headerErrors);
            throw ValidationException::withMessages([
                'file' => [$formattedMessage]
            ]);
        }

        $recordsToInsert = [];
        $now = now();

        foreach ($rows as $row) {
            $rowData = is_array($row) ? $row : $row->toArray();

            $nama = $this->cleanValue($this->getValueByHeader($rowData, 'NAMA KELOMPOK'));

            if (empty($nama)) {
                continue;
            }

            $recordsToInsert[] = [
                'cdk'            => $this->cleanValue($this->getValueByHeader($rowData, 'CABANG DINAS KEHUTANAN')),
                'kabupaten_kota' => $this->cleanValue($this->getValueByHeader($rowData, 'KAB/KOTA')),
                'kecamatan'      => $this->cleanValue($this->getValueByHeader($rowData, 'KECAMATAN')),
                'desa_kelurahan' => $this->cleanValue($this->getValueByHeader($rowData, 'DESA/KELURAHAN')),
                'nama'           => $nama,
                'ketua'          => $this->cleanValue($this->getValueByHeader($rowData, 'KETUA KELOMPOK')),
                'jenis_usaha'    => $this->cleanValue($this->getValueByHeader($rowData, 'JENIS USAHA')),
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        if (empty($recordsToInsert)) {
            throw ValidationException::withMessages([
                'file' => ['Tidak ada data KTH yang valid untuk diimpor. Pastikan kolom "NAMA KELOMPOK" terisi.']
            ]);
        }

        foreach (array_chunk($recordsToInsert, 200) as $chunk) {
            Kth::insert($chunk);
        }

        $this->importedCount = count($recordsToInsert);
    }

    /**
     * Validasi kecocokan kolom dengan template baku serta deteksi typo (misal NAMA KELONPOK).
     *
     * @param array $actualHeaders
     * @return array List pesan error / typo
     */
    public static function validateHeaders(array $actualHeaders): array
    {
        $normalizedActual = [];
        foreach ($actualHeaders as $h) {
            $cleaned = trim(preg_replace('/\s+/', ' ', strtoupper((string)$h)));
            if ($cleaned !== '') {
                $normalizedActual[] = $cleaned;
            }
        }

        $expectedNormalized = array_map(function ($h) {
            return trim(preg_replace('/\s+/', ' ', strtoupper((string)$h)));
        }, self::EXPECTED_HEADERS);

        $missing = array_values(array_diff($expectedNormalized, $normalizedActual));
        $unknown = array_values(array_diff($normalizedActual, $expectedNormalized));

        $errors = [];
        $matchedTypos = [];

        // Deteksi typo: bandingkan kolom tak dikenal dengan kolom wajib yang hilang
        foreach ($unknown as $unk) {
            $bestMatch = null;
            $bestScore = 999;
            $highestSimilarity = 0;

            foreach ($missing as $exp) {
                $lev = levenshtein($unk, $exp);
                similar_text($unk, $exp, $percent);

                // Kemiripan tinggi: edit distance <= 3 atau similarity >= 70%
                if ($lev <= 3 || $percent >= 70) {
                    if ($lev < $bestScore || $percent > $highestSimilarity) {
                        $bestScore = $lev;
                        $highestSimilarity = $percent;
                        $bestMatch = $exp;
                    }
                }
            }

            if ($bestMatch) {
                $errors[] = "Kolom '{$unk}' salah ketik (typo). Seharusnya '{$bestMatch}'.";
                $matchedTypos[$bestMatch] = true;
            } else {
                $errors[] = "Kolom '{$unk}' tidak dikenali dalam template KTH.";
            }
        }

        // Kolom wajib yang hilang dan bukan terdeteksi sebagai typo
        foreach ($missing as $exp) {
            if (!isset($matchedTypos[$exp])) {
                $errors[] = "Kolom wajib '{$exp}' tidak ditemukan dalam template file.";
            }
        }

        return $errors;
    }

    /**
     * Mengambil nilai cell berdasarkan nama header yang diharapkan (case-insensitive & whitespace tolerant).
     */
    protected function getValueByHeader(array $row, string $expectedHeader): ?string
    {
        if (isset($row[$expectedHeader])) {
            return (string)$row[$expectedHeader];
        }

        $expectedNorm = trim(preg_replace('/\s+/', ' ', strtoupper($expectedHeader)));
        foreach ($row as $key => $val) {
            $keyNorm = trim(preg_replace('/\s+/', ' ', strtoupper((string)$key)));
            if ($keyNorm === $expectedNorm) {
                return (string)$val;
            }
        }

        return null;
    }

    /**
     * Membersihkan string dari spasi berlebih dan karakter baris baru.
     */
    protected function cleanValue(?string $val): ?string
    {
        if ($val === null) {
            return null;
        }

        $cleaned = trim(preg_replace('/\s+/', ' ', $val));
        return $cleaned === '' ? null : $cleaned;
    }
}
