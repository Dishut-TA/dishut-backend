<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnalysisProject extends Model
{
    const STATUS_UPLOADED   = 'uploaded';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'project_code',
        'user_id',
        'project_name',
        'status',
        'dem_path',
        'landcover_path',
        'rainfall_path',
        'soil_path',
        'das_path',
        'admin_path',
        'python_job_id',
        'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Hasil analisis aktif adalah revisi terbaru.
     * Re-analysis membuat row AnalysisResult baru agar zona/result lama yang sudah
     * direferensikan validasi lapangan atau program rehabilitasi tidak terhapus.
     */
    public function result(): HasOne
    {
        return $this->hasOne(AnalysisResult::class, 'project_id')->latestOfMany();
    }

    public function results(): HasMany
    {
        return $this->hasMany(AnalysisResult::class, 'project_id')->latest();
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
