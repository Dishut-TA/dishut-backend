<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Donation;
use App\Http\Resources\DonationResource;

class DonationController extends Controller
{
    public function index()
    {
        $donations = Donation::with(['donor', 'donationProgram', 'transaction'])->latest()->get();
        return DonationResource::collection($donations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Add basic validation rules here
        ]);
        $item = Donation::create($request->all());
        return new DonationResource($item);
    }

    public function show(string $id)
    {
        $item = Donation::with(['donor', 'donationProgram'])->findOrFail($id);
        return new DonationResource($item);
    }

    public function getById(string $id)
    {
        return $this->show($id);
    }

    public function update(Request $request, string $id)
    {
        $item = Donation::findOrFail($id);
        
        $originalStatus = strtolower(trim($item->seed_status));

        if ($request->hasFile('bast_file')) {
            $request->validate([
                'bast_file' => 'mimes:pdf,jpg,jpeg,png|max:5120',
            ]);

            if ($item->bast_path && \Storage::disk('public')->exists($item->bast_path)) {
                \Storage::disk('public')->delete($item->bast_path);
            }
            
            $path = $request->file('bast_file')->store('bast_donations', 'public');
            $request->merge(['bast_path' => $path]);
        }

        // Tangani upload file Bukti Tanam
        if ($request->hasFile('proof_file')) {
            $request->validate([
                'proof_file' => 'mimes:pdf,jpg,jpeg,png|max:5120',
            ]);

            if ($item->proof_path && \Storage::disk('public')->exists($item->proof_path)) {
                \Storage::disk('public')->delete($item->proof_path);
            }
            
            $path = $request->file('proof_file')->store('proof_donations', 'public');
            $request->merge(['proof_path' => $path]);
        }

        // 2. Lakukan update data (termasuk status baru dari Frontend)
        $item->update($request->all());
        
        // 3. Ambil status BARU secara fresh dari database
        $newStatus = strtolower(trim($item->fresh()->seed_status));
        
        // 4. Proses Update Stok berdasarkan seed_details
        if (is_array($item->seed_details)) {
            foreach ($item->seed_details as $seedDetail) {
                $quantity = (int) ($seedDetail['quantity'] ?? 0);
                $seedId = $seedDetail['id'] ?? null;
                
                if ($seedId && $quantity > 0) {
                    $seedSpec = \App\Models\SeedSpecification::where('seed_id', $seedId)->first();
                    
                    if ($seedSpec) {
                        // Skenario A: Dari Pending -> Terkumpul
                        if ($originalStatus !== 'terkumpul' && $newStatus === 'terkumpul') {
                            $seedSpec->decrement('stock', $quantity);
                            \Log::info("Berhasil! Stok bibit {$seedId} dikurangi {$quantity}");
                        }
                        // Skenario B: Dari Terkumpul -> Ditolak/Pending (Pembatalan)
                        elseif ($originalStatus === 'terkumpul' && $newStatus !== 'terkumpul') {
                            $seedSpec->increment('stock', $quantity);
                            \Log::info("Dikembalikan! Stok bibit {$seedId} ditambah {$quantity}");
                        }
                    }
                }
            }
        }
        
        $item->load(['donor', 'donationProgram']);
        
        return new DonationResource($item);
    }
}
