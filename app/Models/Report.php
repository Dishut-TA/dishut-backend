<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'donation_program_id',
        'user_id',
        'start_date',
        'end_date',
        'status',
        'report_file_path',
    ];

    public function donationProgram()
    {
        return $this->belongsTo(DonationProgram::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
