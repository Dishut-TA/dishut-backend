<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function districts()
    {
        return $this->hasMany(District::class);
    }
}
