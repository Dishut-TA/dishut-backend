<?php

namespace App\Imports;

use App\Models\Kth;
use Illuminate\Database\Eloquent\Model; 
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class KthImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return Model|array|null
     */
    public function model(array $row): Model|array|null
    {
        return new Kth([
            'cdk'            => $row['cabang_dinas_kehutanan'] ?? null,
            'kabupaten_kota' => $row['kabkota'] ?? null,
            'kecamatan'      => $row['kecamatan'] ?? null,
            'desa_kelurahan' => $row['desakelurahan'] ?? null,
            'nama'           => $row['nama_kelompok'] ?? null,
            'ketua'          => $row['ketua_kelompok'] ?? null,
            'jenis_usaha'    => $row['jenis_usaha'] ?? null,
        ]);
    }
}