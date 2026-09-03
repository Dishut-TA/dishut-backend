<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluasiTim extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function evaluasi()
    {
        return $this->belongsTo(Evaluasi::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
