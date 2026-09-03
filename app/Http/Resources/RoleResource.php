<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
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
            'nama' => $this->name,
            'nama_penjaga' => $this->guard_name,
            'izin' => PermissionResource::collection($this->whenLoaded('permissions')),
            'dibuat_pada' => $this->created_at,
            'diperbarui_pada' => $this->updated_at,
        ];
    }
}
