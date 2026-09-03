<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cdk' => $this->cdk,
            'kabupaten_kota' => $this->kabupaten_kota,
            'kecamatan' => $this->kecamatan,
            'desa_kelurahan' => $this->desa_kelurahan,
            'nama' => $this->nama,
            'ketua' => $this->ketua,
            'jenis_usaha' => $this->jenis_usaha,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}