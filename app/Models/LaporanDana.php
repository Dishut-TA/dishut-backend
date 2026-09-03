<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaporanDana extends Model
{
    protected $fillable = [
        'sumber_dana',
        'program_id',
        'nama_program',
        'tahap',
        'tanggal_pengeluaran',
        'dana_disalurkan',
        'dana_direalisasikan',
        'status',
        'catatan',
    ];

    public function rincian(): HasMany
    {
        return $this->hasMany(RincianPenggunaanDana::class);
    }
}