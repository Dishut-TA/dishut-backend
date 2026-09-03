<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seed extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function donationPrograms()
    {
        return $this->belongsToMany(DonationProgram::class, 'donation_program_seed');
    }

    public function specifications()
    {
        return $this->hasMany(SeedSpecification::class);
    }
}
