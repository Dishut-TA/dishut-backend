<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldValidation extends Model
{
    protected $fillable = [
        'zone_id',
        'nama_lokasi',
        'sumber_lokasi',
        'nama_penyuluh',
        'kondisi_lahan',
        'kondisi_vegetasi',
        'kendala_lapangan',
        'titik_koordinat_gps',
        'foto_lokasi_url',
        'catatan_peninjauan',
        'status_verifikasi',
    ];

    /**
     * Relasi ke analysis result zone.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(AnalysisResultZone::class, 'zone_id');
    }
}
