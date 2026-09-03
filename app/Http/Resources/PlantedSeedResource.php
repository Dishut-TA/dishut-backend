<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlantedSeedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'donation_program_id' => $this->donation_program_id,
            'seed_id' => $this->seed_id,
            'planted_quantity' => $this->planted_quantity,
            'proof_path' => $this->proof_path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}