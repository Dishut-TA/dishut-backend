<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataTanaman extends Model
{
    protected $table = 'data_tanamans';
    protected $fillable = ['petak_ukur_id', 'seed_id', 'nama_tanaman', 'jumlah', 'kondisi_tanaman', 'keterangan', 'foto_url', 'tinggi_tanaman', 'latitude', 'longitude',
        'status_penyulaman', 'penyulaman_jumlah', 'penyulaman_tinggi',
        'penyulaman_foto', 'penyulaman_keterangan', 'penyulaman_at'];

    protected $casts = [
        'penyulaman_at' => 'datetime',
    ];

    /** Titik dianggap perlu disulam bila tanamannya mati atau rusak. */
    public function perluDisulam(): bool
    {
        $kondisi = strtolower($this->kondisi_tanaman ?? '');

        return str_contains($kondisi, 'mati') || str_contains($kondisi, 'rusak');
    }

    public function petakUkur()
    {
        return $this->belongsTo(PetakUkur::class);
    }

    public function seed()
    {
        return $this->belongsTo(Seed::class);
    }
}
