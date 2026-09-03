<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    protected $fillable = [
        'donation_program_id',
        'transaction_id',
        'donor_id',
        'seed_id',
        'seed_quantity',
        'seed_status',
        'receipt_path',
        'certificate_path',
        'bast_path',   
        'proof_path',
    ];

    public function donationProgram()
    {
        return $this->belongsTo(DonationProgram::class);
    }

    public function donor()
    {
        return $this->belongsTo(Donor::class);
    }

    public function seed()
    {
        return $this->belongsTo(Seed::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
