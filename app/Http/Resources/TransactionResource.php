<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'donation_id' => $this->donation_id,
            'donor_id' => $this->donor_id,
            'amount' => $this->amount,
            'transaction_date' => $this->transaction_date,
            'proof_path' => $this->proof_path,
            'proof_url' => $this->proof_path ? asset('storage/' . $this->proof_path) : null,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'donations' => DonationResource::collection($this->whenLoaded('donations')),
            'donor' => new DonorResource($this->whenLoaded('donor')),
        ];
    }
}