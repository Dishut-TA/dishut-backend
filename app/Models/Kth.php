<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kth extends Model
{
    protected $fillable = [
        'cdk',
        'kabupaten_kota',
        'kecamatan',
        'desa_kelurahan',
        'nomor_sk',
        'nama',
        'ketua',
        'no_wa_ketua',
        'jumlah_anggota',
        'jenis_usaha',
        'kelas_kth',
        'alamat_sekretariat',
        'nama_bank',
        'no_rekening',
        'nama_pemilik_rekening',
        'foto_profil',
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
