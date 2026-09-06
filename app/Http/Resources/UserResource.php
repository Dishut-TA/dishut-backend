<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'nama_pengguna' => $this->username,
            'email' => $this->email,
            'nip' => $this->pegawai?->nip,
            'profil' => new PegawaiResource($this->whenLoaded('pegawai')),
            'kth_id' => $this->kth_id,
            'kth' => $this->whenLoaded('kth'),
            'peran' => RoleResource::collection($this->whenLoaded('roles')),
            'izin' => PermissionResource::collection($this->whenLoaded('permissions')),
            'dibuat_pada' => $this->created_at,
            'diperbarui_pada' => $this->updated_at,
        ];
    }
}
