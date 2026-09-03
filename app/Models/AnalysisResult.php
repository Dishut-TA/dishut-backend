<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalysisResult extends Model
{
    protected $fillable = [
        'project_id',
        'job_id',
        'status',
        'result_json',
        'table_json',
        'metadata_json',
        'critical_geojson_url',
        'map_html_url',
        'cpi_raster_url',
        'class_raster_url',
    ];

    /**
     * Cast kolom JSON sebagai array PHP secara otomatis.
     * Ini memungkinkan akses langsung $result->result_json['ahp']['cr'] dll.
     */
    protected $casts = [
        'result_json'   => 'array',
        'table_json'    => 'array',
        'metadata_json' => 'array',
    ];

    /**
     * Relasi ke project induk.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(AnalysisProject::class, 'project_id');
    }

    /**
     * Relasi ke breakdown per zona wilayah (opsional).
     */
    public function zones(): HasMany
    {
        return $this->hasMany(AnalysisResultZone::class, 'result_id');
    }

    /**
     * Helper: ambil data AHP dari result_json.
     */
    public function getAhpData(): ?array
    {
        return $this->result_json['ahp'] ?? null;
    }

    /**
     * Helper: ambil data map dari result_json.
     */
    public function getMapData(): ?array
    {
        return $this->result_json['map'] ?? null;
    }
}
