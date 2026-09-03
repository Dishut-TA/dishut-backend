<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'donation_program_id' => $this->donation_program_id,
            'donor_id' => $this->donor_id,
            'seed_id' => $this->seed_id,
            'seed_quantity' => $this->seed_quantity,
            'seed_status' => $this->seed_status,
            'receipt_path' => $this->receipt_path,
            'certificate_path' => $this->certificate_path,
            'bast_path' => $this->bast_path,
            'bast_url' => $this->bast_path ? asset('storage/' . $this->bast_path) : null,
            'proof_path' => $this->proof_path,
            'proof_url' => $this->proof_path ? asset('storage/' . $this->proof_path) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'seed' => new SeedResource($this->whenLoaded('seed')),
            'donor' => new DonorResource($this->whenLoaded('donor')),
            'donation_program' => new DonationProgramResource($this->whenLoaded('donationProgram')),
            'transaction' => new TransactionResource($this->whenLoaded('transaction')),
        ];
    }
}