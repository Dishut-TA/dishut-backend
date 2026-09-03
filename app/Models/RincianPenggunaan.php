<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RincianPenggunaan extends Model
{
    protected $fillable = [
        'laporan_dana_id',
        'nama_kegiatan',
        'tanggal',
        'nominal',
        'bukti_transaksi',
        'status',
        'catatan',
    ];

    public function laporanDana()
    {
        return $this->belongsTo(LaporanDana::class);
    }
}
