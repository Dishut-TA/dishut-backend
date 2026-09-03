<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Http\Resources\TransactionResource;
use Illuminate\Support\Facades\DB;
use App\Models\Donor;
use App\Models\Donation;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with([
            'donations.donationProgram', 
            'donations.seed', 
            'donor'
        ])->get();

        return TransactionResource::collection($transactions);
    }

    public function store(Request $request)
{
    $request->validate([
        'program_id' => 'required|exists:donation_programs,id',
        'donor_name' => 'required|string',
        'address' => 'nullable|string',
        'amount' => 'required|numeric',
        'payment_method' => 'required|string',
        'selected_bibits' => 'required|json', 
        'proof_file' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120',
    ]);

    DB::beginTransaction();

    try {
        $donor = Donor::create([
            'user_id' => $request->user_id, 
            'donor_name' => $request->donor_name,
            'address' => $request->address ?? 'Tidak diketahui',
        ]);

        $proofPath = null;
        if ($request->hasFile('proof_file')) {
            $proofPath = $request->file('proof_file')->store('proof_transactions', 'public');
        }

        $transaction = Transaction::create([
            'donor_id' => $donor->id,
            'amount' => $request->amount,
            'transaction_date' => now(),
            'payment_method' => $request->payment_method,
            'proof_path' => $proofPath,
            'status' => 'Pending',
        ]);

        $bibits = json_decode($request->selected_bibits, true);
        foreach ($bibits as $bibit) {
            Donation::create([
                'transaction_id' => $transaction->id,
                'donation_program_id' => $request->program_id,
                'donor_id' => $donor->id,
                'seed_id' => $bibit['id'],
                'seed_quantity' => $bibit['quantity'],
                'seed_status' => 'Pending',
            ]);
        }

        DB::commit();

        // Load relasi untuk response
        $transaction->load(['donations.seed', 'donations.donationProgram', 'donor']);
        return new TransactionResource($transaction);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'Gagal memproses donasi: ' . $e->getMessage()], 500);
    }
}

    public function show(string $id)
    {
        $item = Transaction::with([
            'donations.donationProgram', 
            'donations.seed.specifications', 
            'donor'
        ])->findOrFail($id);
        
        return new TransactionResource($item);
    }

    public function update(Request $request, string $id)
    {
        $item = Transaction::findOrFail($id);
        $item->update($request->all());
        
        $item->load(['donations.donationProgram', 'donations.seed', 'donor']);
        return new TransactionResource($item);
    }

    public function destroy(string $id)
    {
        $item = Transaction::findOrFail($id);
        $item->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}