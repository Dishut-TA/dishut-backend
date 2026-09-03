<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LandResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'desa_id' => $this->village_id,
            'kode' => $this->code,
            'nama' => $this->name,
            'luas_lahan' => $this->area,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'geometry' => $this->geometry,
            'status' => $this->status == 'active' ? 'aktif' : 'tidak_aktif',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
