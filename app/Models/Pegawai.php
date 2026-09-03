<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    protected $fillable = [
        'user_id',
        'nip',
        'no_telp',
        'tanggal_lahir',
        'alamat',
        'foto_profile',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
