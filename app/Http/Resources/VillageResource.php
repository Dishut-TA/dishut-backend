<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VillageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'kecamatan_id' => $this->district_id,
            'kode' => $this->code,
            'nama' => $this->name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'geometry' => $this->geometry,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
