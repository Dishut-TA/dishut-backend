<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'kth_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function kth()
    {
        return $this->belongsTo(Kth::class, 'kth_id');
    }

    public function csr()
    {
        return $this->hasOne(Csr::class);
    }

    public function getOrCreateCsr()
    {
        if (!$this->csr) {
            $this->csr()->create([
                'nama_perusahaan' => 'Mitra CSR ' . $this->username,
                'no_telepon' => '-',
                'alamat' => '-'
            ]);
            $this->load('csr');
        }
        return $this->csr;
    }
    
    public function pegawai()
    {
        return $this->hasOne(Pegawai::class);
    }
}

