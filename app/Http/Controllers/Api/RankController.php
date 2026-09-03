<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rank;
use App\Http\Resources\RankResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RankController extends Controller
{
    public function index()
    {
        try {
            $ranks = Rank::all();

            if ($ranks->isEmpty()) {
                return response()->json([
                    'message' => 'Tidak ada data pangkat',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            return response()->json([
                'message' => 'Data pangkat berhasil diambil',
                'code' => 200,
                'payload' => RankResource::collection($ranks)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data pangkat',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nama' => 'required|string|max:255|unique:ranks,name',
                'golongan' => 'required|string|max:10',
                'ruang' => 'required|string|max:1',
            ], [
                'nama.required' => 'Nama pangkat harus diisi',
                'nama.string' => 'Nama pangkat harus berupa string',
                'nama.unique' => 'Nama pangkat sudah terdaftar',
                'nama.max' => 'Nama pangkat maksimal 255 karakter',
                'golongan.required' => 'Golongan harus diisi',
                'golongan.string' => 'Golongan harus berupa string',
                'golongan.max' => 'Golongan maksimal 10 karakter',
                'ruang.required' => 'Ruang harus diisi',
                'ruang.string' => 'Ruang harus berupa string',
                'ruang.max' => 'Ruang maksimal 1 karakter',
            ]);

            $rank = Rank::create([
                'name' => $validated['nama'],
                'group' => $validated['golongan'],
                'grade' => $validated['ruang'],
            ]);

            return response()->json([
                'message' => 'Pangkat berhasil dibuat',
                'code' => 201,
                'payload' => new RankResource($rank)
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat membuat pangkat',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $rank = Rank::find($id);

            if (!$rank) {
                return response()->json([
                    'message' => 'Pangkat tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            return response()->json([
                'message' => 'Detail pangkat berhasil diambil',
                'code' => 200,
                'payload' => new RankResource($rank)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data pangkat',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $rank = Rank::find($id);

            if (!$rank) {
                return response()->json([
                    'message' => 'Pangkat tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            $validated = $request->validate([
                'nama' => 'required|string|max:255|unique:ranks,name,' . $id,
                'golongan' => 'required|string|max:10',
                'ruang' => 'required|string|max:1',
            ], [
                'nama.required' => 'Nama pangkat harus diisi',
                'nama.string' => 'Nama pangkat harus berupa string',
                'nama.unique' => 'Nama pangkat sudah terdaftar',
                'nama.max' => 'Nama pangkat maksimal 255 karakter',
                'golongan.required' => 'Golongan harus diisi',
                'golongan.string' => 'Golongan harus berupa string',
                'golongan.max' => 'Golongan maksimal 10 karakter',
                'ruang.required' => 'Ruang harus diisi',
                'ruang.string' => 'Ruang harus berupa string',
                'ruang.max' => 'Ruang maksimal 1 karakter',
            ]);

            $rank->update([
                'name' => $validated['nama'],
                'group' => $validated['golongan'],
                'grade' => $validated['ruang'],
            ]);

            return response()->json([
                'message' => 'Pangkat berhasil diupdate',
                'code' => 200,
                'payload' => new RankResource($rank)
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui pangkat',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $rank = Rank::find($id);

            if (!$rank) {
                return response()->json([
                    'message' => 'Pangkat tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            $rank->delete();

            return response()->json([
                'message' => 'Pangkat berhasil dihapus',
                'code' => 200,
                'payload' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menghapus pangkat',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }
}
