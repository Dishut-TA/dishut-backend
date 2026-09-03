<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RincianPenggunaanDana extends Model
{
    protected $fillable = [
        'laporan_dana_id',
        'kategori_kegiatan',
        'nominal',
        'bukti_transaksi_path',
    ];

    public function laporanDana(): BelongsTo
    {
        return $this->belongsTo(LaporanDana::class);
    }
}