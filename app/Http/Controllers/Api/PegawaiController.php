<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Http\Resources\PegawaiResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class PegawaiController extends Controller
{
    public function index()
    {
        try {
            $pegawais = Pegawai::all();

            if ($pegawais->isEmpty()) {
                return response()->json([
                    'message' => 'Tidak ada data profile pegawai',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            return response()->json([
                'message' => 'Data profile pegawai berhasil diambil',
                'code' => 200,
                'payload' => PegawaiResource::collection($pegawais)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data profile pegawai',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $pegawai = Pegawai::find($id);

            if (!$pegawai) {
                return response()->json([
                    'message' => 'Profile pegawai tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            return response()->json([
                'message' => 'Detail profile pegawai berhasil diambil',
                'code' => 200,
                'payload' => new PegawaiResource($pegawai)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil detail profile pegawai',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $pegawai = Pegawai::find($id);

            if (!$pegawai) {
                return response()->json([
                    'message' => 'Profile pegawai tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            $validated = $request->validate([
                'nip' => 'nullable|string|min:18|max:18|unique:pegawais,nip,' . $id,
                'no_telp' => 'nullable|string|max:15',
                'tanggal_lahir' => 'nullable|date',
                'alamat' => 'nullable|string',
                'foto_profile' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ], [
                'nip.unique' => 'NIP sudah terdaftar',
                'nip.min' => 'NIP minimal 18 karakter',
                'nip.max' => 'NIP maksimal 18 karakter',
                'foto_profile.image' => 'File harus berupa gambar',
                'foto_profile.mimes' => 'Format gambar harus jpeg, png, jpg, atau gif',
                'foto_profile.max' => 'Ukuran gambar maksimal 2MB',
            ]);

            $pegawaiData = [
                'nip' => $validated['nip'] ?? $pegawai->nip,
                'no_telp' => $validated['no_telp'] ?? $pegawai->no_telp,
                'tanggal_lahir' => $validated['tanggal_lahir'] ?? $pegawai->tanggal_lahir,
                'alamat' => $validated['alamat'] ?? $pegawai->alamat,
            ];

            if ($request->hasFile('foto_profile')) {
                if ($pegawai->foto_profile && Storage::disk('public')->exists($pegawai->foto_profile)) {
                    Storage::disk('public')->delete($pegawai->foto_profile);
                }
                
                $path = $request->file('foto_profile')->store('profiles', 'public');
                $pegawaiData['foto_profile'] = $path;
            }

            $pegawai->update($pegawaiData);

            return response()->json([
                'message' => 'Profile pegawai berhasil diperbarui',
                'code' => 200,
                'payload' => new PegawaiResource($pegawai)
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui profile pegawai',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }
}
