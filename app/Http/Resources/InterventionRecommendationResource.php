<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InterventionRecommendationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'jenis_intervensi_id' => $this->intervention_type_id,
            'nama' => $this->name,
            'deskripsi' => $this->description,
            'status' => $this->status == 'active' ? 'aktif' : 'tidak_aktif',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
