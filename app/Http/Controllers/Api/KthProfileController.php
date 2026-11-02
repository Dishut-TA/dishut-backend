<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KthResource;
use App\Models\Kth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class KthProfileController extends Controller
{
    /**
     * Mengambil detail profil KTH milik pengguna yang sedang login.
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();
            
            // Ambil KTH milik user atau buatkan instance jika belum terhubung
            $kth = $user->kth ?? $user->getOrCreateKth();

            if (!$kth) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil KTH tidak ditemukan untuk akun ini.',
                    'code'    => 404,
                    'payload' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Profil KTH berhasil dimuat.',
                'code'    => 200,
                'payload' => new KthResource($kth),
                'data'    => new KthResource($kth),
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memuat profil KTH.',
                'code'    => 500,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Memperbarui data profil KTH milik pengguna yang sedang login.
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();
            $kth = $user->kth ?? $user->getOrCreateKth();

            if (!$kth) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil KTH tidak ditemukan untuk akun ini.',
                    'code'    => 404,
                    'payload' => null,
                ], 404);
            }

            // Normalisasi input dari berbagai variasi penamaan field frontend
            $payload = $request->all();
            if (!$request->has('nama') && $request->filled('nama_kelompok')) {
                $payload['nama'] = $request->input('nama_kelompok');
            }
            if (!$request->has('ketua') && ($request->filled('nama_lengkap_ketua') || $request->filled('nama_ketua'))) {
                $payload['ketua'] = $request->input('nama_lengkap_ketua') ?? $request->input('nama_ketua');
            }
            if (!$request->has('no_wa_ketua') && ($request->filled('no_whatsapp_ketua') || $request->filled('no_wa'))) {
                $payload['no_wa_ketua'] = $request->input('no_whatsapp_ketua') ?? $request->input('no_wa');
            }
            if (!$request->has('jenis_usaha') && $request->filled('jenis_usaha_utama')) {
                $payload['jenis_usaha'] = $request->input('jenis_usaha_utama');
            }
            if (!$request->has('no_rekening') && $request->filled('nomor_rekening')) {
                $payload['no_rekening'] = $request->input('nomor_rekening');
            }
            if (!$request->has('nomor_sk') && ($request->filled('no_sk') || $request->filled('nomor_register'))) {
                $payload['nomor_sk'] = $request->input('no_sk') ?? $request->input('nomor_register');
            }

            $validated = validator($payload, [
                'cdk'                   => 'sometimes|required|string|max:255',
                'kabupaten_kota'        => 'sometimes|required|string|max:255',
                'kecamatan'             => 'sometimes|required|string|max:255',
                'desa_kelurahan'        => 'sometimes|required|string|max:255',
                'nomor_sk'              => 'nullable|string|max:255',
                'nama'                  => 'sometimes|required|string|max:255',
                'ketua'                 => 'sometimes|required|string|max:255',
                'no_wa_ketua'           => 'nullable|string|max:30',
                'jumlah_anggota'        => 'nullable|string|max:50',
                'jenis_usaha'           => 'nullable|string|max:255',
                'kelas_kth'             => 'nullable|string|max:100',
                'alamat_sekretariat'    => 'nullable|string',
                'nama_bank'             => 'nullable|string|max:100',
                'no_rekening'           => 'nullable|string|max:50',
                'nama_pemilik_rekening' => 'nullable|string|max:255',
                'foto_profil'           => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            ], [
                'cdk.required'            => 'Cabang Dinas Kehutanan wajib diisi',
                'kabupaten_kota.required' => 'Kabupaten / Kota wajib diisi',
                'kecamatan.required'      => 'Kecamatan wajib diisi',
                'desa_kelurahan.required' => 'Desa / Kelurahan wajib diisi',
                'nama.required'           => 'Nama Kelompok KTH wajib diisi',
                'ketua.required'          => 'Nama Ketua KTH wajib diisi',
                'foto_profil.image'       => 'Foto profil harus berupa file gambar',
                'foto_profil.max'         => 'Ukuran foto profil maksimal 2MB',
            ])->validate();

            $updateData = [
                'cdk'                   => $validated['cdk'] ?? $kth->cdk,
                'kabupaten_kota'        => $validated['kabupaten_kota'] ?? $kth->kabupaten_kota,
                'kecamatan'             => $validated['kecamatan'] ?? $kth->kecamatan,
                'desa_kelurahan'        => $validated['desa_kelurahan'] ?? $kth->desa_kelurahan,
                'nomor_sk'              => array_key_exists('nomor_sk', $validated) ? $validated['nomor_sk'] : $kth->nomor_sk,
                'nama'                  => $validated['nama'] ?? $kth->nama,
                'ketua'                 => $validated['ketua'] ?? $kth->ketua,
                'no_wa_ketua'           => array_key_exists('no_wa_ketua', $validated) ? $validated['no_wa_ketua'] : $kth->no_wa_ketua,
                'jumlah_anggota'        => array_key_exists('jumlah_anggota', $validated) ? $validated['jumlah_anggota'] : $kth->jumlah_anggota,
                'jenis_usaha'           => array_key_exists('jenis_usaha', $validated) ? $validated['jenis_usaha'] : $kth->jenis_usaha,
                'kelas_kth'             => array_key_exists('kelas_kth', $validated) ? $validated['kelas_kth'] : $kth->kelas_kth,
                'alamat_sekretariat'    => array_key_exists('alamat_sekretariat', $validated) ? $validated['alamat_sekretariat'] : $kth->alamat_sekretariat,
                'nama_bank'             => array_key_exists('nama_bank', $validated) ? $validated['nama_bank'] : $kth->nama_bank,
                'no_rekening'           => array_key_exists('no_rekening', $validated) ? $validated['no_rekening'] : $kth->no_rekening,
                'nama_pemilik_rekening' => array_key_exists('nama_pemilik_rekening', $validated) ? $validated['nama_pemilik_rekening'] : $kth->nama_pemilik_rekening,
            ];

            // Penanganan upload foto profil jika ada
            if ($request->hasFile('foto_profil')) {
                if ($kth->foto_profil && Storage::disk('public')->exists($kth->foto_profil)) {
                    Storage::disk('public')->delete($kth->foto_profil);
                }
                $path = $request->file('foto_profil')->store('kth_profiles', 'public');
                $updateData['foto_profil'] = $path;
            }

            $kth->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Profil KTH berhasil diperbarui.',
                'code'    => 200,
                'payload' => new KthResource($kth->fresh()),
                'data'    => new KthResource($kth->fresh()),
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'code'    => 422,
                'payload' => $e->errors(),
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui profil KTH.',
                'code'    => 500,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}

