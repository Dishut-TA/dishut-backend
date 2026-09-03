<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterventionType extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function recommendations()
    {
        return $this->hasMany(InterventionRecommendation::class);
    }
}
