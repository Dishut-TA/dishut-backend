<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterventionRecommendation extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function type()
    {
        return $this->belongsTo(InterventionType::class);
    }
}
