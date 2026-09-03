<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnalysisProject extends Model
{
    /**
     * Status konstanta untuk memudahkan penggunaan di seluruh codebase.
     */
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

    /**
     * Relasi ke user yang membuat project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke hasil analisis CPI (one-to-one).
     */
    public function result(): HasOne
    {
        return $this->hasOne(AnalysisResult::class, 'project_id');
    }

    /**
     * Cek apakah project sudah selesai diproses.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Cek apakah project sedang diproses.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Cek apakah project gagal diproses.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
