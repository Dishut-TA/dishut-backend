<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeedSpecification extends Model
{
    protected $fillable = [
        'seed_id',
        'min_height',
        'max_height',
        'stock',
        'price',
    ];

    public function seed()
    {
        return $this->belongsTo(Seed::class);
    }

    public function donationPrograms()
    {
        return $this->hasMany(DonationProgram::class);
    }
}
