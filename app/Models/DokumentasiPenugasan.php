<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokumentasiPenugasan extends Model
{
    protected $fillable = ['penugasan_id', 'file_path', 'keterangan', 'jenis_dokumentasi'];

    public function penugasan()
    {
        return $this->belongsTo(Penugasan::class);
    }
}
