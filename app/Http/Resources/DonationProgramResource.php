<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonationProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalCollected = $this->whenLoaded('donations', function () {
            return $this->donations->whereIn('seed_status', ['Terkumpul', 'Disalurkan', 'Terealisasi'])->sum('seed_quantity');
        }, $this->total_seeds_collected ?? 0);

        $totalRealized = $this->whenLoaded('plantedSeeds', function () {
            return $this->plantedSeeds->sum('planted_quantity');
        }, $this->total_seeds_realized ?? 0);

        $allocations = [];
        $totalDana = 0;

        if ($this->relationLoaded('donations') && $this->relationLoaded('seeds')) {
            $validDonations = $this->donations->whereIn('seed_status', ['Terkumpul', 'Disalurkan', 'Terealisasi']);
            
            $grouped = $validDonations->groupBy('seed_id');
            
            $idx = 1;
            foreach ($grouped as $seedId => $group) {
                $qty = $group->sum('seed_quantity');
                
                $seed = $this->seeds->firstWhere('id', $seedId);
                $seedName = $seed ? $seed->name : 'Bibit';
                
                $price = 0;
                if ($seed && $seed->specifications && $seed->specifications->isNotEmpty()) {
                    $price = $seed->specifications->first()->price;
                }

                $subTotal = $qty * $price;
                
                $allocations[] = [
                    'id' => $idx,
                    'namaBibit' => $seedName,
                    'jumlah' => $qty,
                    'hargaSatuan' => $price,
                    'subTotal' => $subTotal
                ];
                
                $totalDana += $subTotal;
                $idx++;
            }
        }

        return [
            'id' => $this->id,
            'analysis_result_id' => $this->analysis_result_id,
            'kth_id' => $this->kth_id,
            'seed_specification_id' => $this->seed_specification_id,
            'name' => $this->name,
            'location' => $this->location,
            'total_seeds_collected' => $totalCollected,
            'total_seeds_realized' => $totalRealized,
            'status' => $this->status,
            'start_date' => $this->start_date, 
            'end_date' => $this->end_date,
            'image_url' => $this->image ? asset('storage/' . $this->image) : null, 
            'jenis_bibit' => SeedResource::collection($this->whenLoaded('seeds')),
            'specifications' => SeedSpecificationResource::collection($this->whenLoaded('specifications')),
            'description' => $this->description,
            'allocations' => $allocations,
            'total_dana' => $totalDana,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}