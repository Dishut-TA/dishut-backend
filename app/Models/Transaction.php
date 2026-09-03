<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
protected $fillable = [
    // 'donation_id',
    'donor_id',
    'amount',
    'transaction_date',
    'proof_path',
    'payment_method',
    'status',
];

public function donations()
{
    return $this->hasMany(Donation::class);
}

public function donor()
{
    return $this->belongsTo(Donor::class);
}
}
