<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu hasil pengukuran petak ukur pada satu periode siklus (P0-P4).
 *
 * Baris di sini bersifat historis: pengukuran P1 tetap utuh setelah P2 diukur.
 */
class HasilMonitoringPetak extends Model
{
    protected $fillable = [
        'petak_ukur_id',
        'penugasan_id',
        'evaluasi_id',
        'periode',
        'rencana_tanaman',
        'bibit_tumbuh',
        'persentase_tumbuh',
        'tinggi_rata',
        'koordinat',
        'foto',
        'keterangan',
        'dicatat_at',
    ];

    protected $casts = [
        'rencana_tanaman' => 'integer',
        'bibit_tumbuh' => 'integer',
        'persentase_tumbuh' => 'float',
        'tinggi_rata' => 'float',
        'dicatat_at' => 'datetime',
    ];

    public function petakUkur()
    {
        return $this->belongsTo(PetakUkur::class);
    }

    public function penugasan()
    {
        return $this->belongsTo(Penugasan::class);
    }

    public function evaluasi()
    {
        return $this->belongsTo(Evaluasi::class);
    }
}
