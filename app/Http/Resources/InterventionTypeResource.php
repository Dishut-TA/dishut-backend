<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InterventionTypeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'kode' => $this->code,
            'nama' => $this->name,
            'deskripsi' => $this->description,
            'status' => $this->status == 'active' ? 'aktif' : 'tidak_aktif',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
