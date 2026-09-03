<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantedSeed extends Model
{
    protected $fillable = [
        'donation_program_id',
        'seed_id',
        'planted_quantity',
        'proof_path',
    ];

    public function donationProgram()
    {
        return $this->belongsTo(DonationProgram::class);
    }

    public function seed()
    {
        return $this->belongsTo(Seed::class);
    }
}
