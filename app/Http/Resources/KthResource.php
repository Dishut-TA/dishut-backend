<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'cdk'                   => $this->cdk,
            'kabupaten_kota'        => $this->kabupaten_kota,
            'kecamatan'             => $this->kecamatan,
            'desa_kelurahan'        => $this->desa_kelurahan,
            'nomor_sk'              => $this->nomor_sk,
            'nama'                  => $this->nama,
            'nama_kelompok'         => $this->nama,
            'ketua'                 => $this->ketua,
            'nama_ketua'            => $this->ketua,
            'no_wa_ketua'           => $this->no_wa_ketua,
            'jumlah_anggota'        => $this->jumlah_anggota,
            'jenis_usaha'           => $this->jenis_usaha,
            'jenis_usaha_utama'     => $this->jenis_usaha,
            'kelas_kth'             => $this->kelas_kth,
            'alamat_sekretariat'    => $this->alamat_sekretariat,
            'nama_bank'             => $this->nama_bank,
            'no_rekening'           => $this->no_rekening,
            'nomor_rekening'        => $this->no_rekening,
            'nama_pemilik_rekening' => $this->nama_pemilik_rekening,
            'foto_profil'           => $this->foto_profil ? asset('storage/' . $this->foto_profil) : null,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
        ];
    }
}