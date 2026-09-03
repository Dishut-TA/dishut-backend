<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kth extends Model
{
    protected $fillable = [
        'cdk',
        'user_id',
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
        return $this->belongsTo(User::class);
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
