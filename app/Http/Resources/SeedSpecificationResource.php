<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeedSpecificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seed_id' => $this->seed_id,
            'seed' => new SeedResource($this->whenLoaded('seed')),
            'min_height' => $this->min_height,
            'max_height' => $this->max_height,
            'stock' => $this->stock,
            'price' => $this->price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}