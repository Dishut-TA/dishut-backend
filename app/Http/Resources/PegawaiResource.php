<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PegawaiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nip' => $this->nip,
            'no_telp' => $this->no_telp,
            'tanggal_lahir' => $this->tanggal_lahir,
            'alamat' => $this->alamat,
            'foto_profile' => $this->foto_profile ? url('storage/' . $this->foto_profile) : null,
        ];
    }
}
