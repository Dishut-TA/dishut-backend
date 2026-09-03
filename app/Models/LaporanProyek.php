<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaporanProyek extends Model
{
    protected $fillable = [
        'program_id',
        'program_type',
        'tanggal_laporan',
        'foto_kegiatan',
        'catatan',
        'status',
    ];

    public function program()
    {
        return $this->morphTo();
    }
}
