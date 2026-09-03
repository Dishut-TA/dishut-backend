<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Evaluasi extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'tanggal_surat' => 'date',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function evaluable()
    {
        return $this->morphTo();
    }

    public function tim()
    {
        return $this->hasMany(EvaluasiTim::class);
    }
}
