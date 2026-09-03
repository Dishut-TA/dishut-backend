<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class District extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function villages()
    {
        return $this->hasMany(Village::class);
    }
}
