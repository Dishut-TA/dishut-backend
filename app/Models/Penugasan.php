<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Penugasan extends Model
{
    use HasFactory;

    protected $table = 'penugasans';

    protected $fillable = [
        'penyuluh_id',
        'jenis_kegiatan',
        'penugasanable_type',
        'penugasanable_id',
        'status',
        'tanggal_penugasan',
        'tanggal_mulai',
        'batas_waktu',
        'arahan',
        'periode_monitoring',
        'metode',
        'prioritas',
        'tujuan',
        'lampiran_penugasan'
    ];

    /**
     * Relasi ke penyuluh (user)
     */
    public function penyuluh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penyuluh_id');
    }

    /**
     * Relasi polymorphic ke entitas sumber (AnalysisResultZone, DonationProgram, dll)
     */
    public function penugasanable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relasi ke Petak Ukur (untuk Pelaksanaan Penanaman)
     */
    public function petakUkurs()
    {
        return $this->hasMany(PetakUkur::class, 'penugasan_id');
    }

    public function dokumentasi()
    {
        return $this->hasMany(DokumentasiPenugasan::class, 'penugasan_id');
    }
}
