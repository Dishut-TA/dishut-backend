<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Csr extends Model
{
    protected $fillable = [
        'user_id',
        'nama_perusahaan',
        'no_telepon',
        'alamat',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaksiCsrs()
    {
        return $this->hasMany(TransaksiCsr::class);
    }
}
