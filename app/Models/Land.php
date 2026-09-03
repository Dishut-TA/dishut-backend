<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Land extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function village()
    {
        return $this->belongsTo(Village::class);
    }
}
