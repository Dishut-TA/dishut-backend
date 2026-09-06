<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kth extends Model
{
    protected $fillable = [
        'cdk',
        'no_rekening',
        'kabupaten_kota',
        'kecamatan',
        'desa_kelurahan',
        'nama',
        'ketua',
        'jenis_usaha',
    ];

    public function donationPrograms()
    {
        return $this->hasMany(DonationProgram::class);
    }

    public function user()
    {
        return $this->hasMany(User::class, 'kth_id');
    }

    public function programApbds()
    {
        return $this->hasMany(ProgramApbd::class);
    }

    public function programCsrs()
    {
        return $this->hasMany(ProgramCsr::class);
    }
}
