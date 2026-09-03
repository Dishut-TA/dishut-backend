<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Http\Resources\PositionResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PositionController extends Controller
{
    public function index()
    {
        try {
            $positions = Position::all();

            if ($positions->isEmpty()) {
                return response()->json([
                    'message' => 'Tidak ada data jabatan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }
            
            return response()->json([
                'message' => 'Data jabatan berhasil diambil',
                'code' => 200,
                'payload' => PositionResource::collection($positions)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data jabatan',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nama' => 'required|string|max:255|unique:positions,name',
                'deskripsi' => 'nullable|string',
            ], [
                'nama.required' => 'Nama jabatan harus diisi',
                'nama.string' => 'Nama jabatan harus berupa string',
                'nama.unique' => 'Nama jabatan sudah terdaftar',
                'nama.max' => 'Nama jabatan maksimal 255 karakter',
            ]);

            $position = Position::create([
                'name' => $validated['nama'],
                'description' => $validated['deskripsi'],
            ]);

            return response()->json([
                'message' => 'Jabatan berhasil ditambahkan',
                'code' => 201,
                'payload' => new PositionResource($position)
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menambahkan jabatan',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $position = Position::find($id);

            if (!$position) {
                return response()->json([
                    'message' => 'Jabatan tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            return response()->json([
                'message' => 'Data jabatan berhasil diambil',
                'code' => 200,
                'payload' => new PositionResource($position)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data jabatan',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $position = Position::find($id);

            if (!$position) {
                return response()->json([
                    'message' => 'Jabatan tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            $validated = $request->validate([
                'nama' => 'required|string|max:255|unique:positions,name,' . $id,
                'deskripsi' => 'nullable|string',
            ], [
                'nama.required' => 'Nama jabatan harus diisi',
                'nama.string' => 'Nama jabatan harus berupa string',
                'nama.unique' => 'Nama jabatan sudah terdaftar',
                'nama.max' => 'Nama jabatan maksimal 255 karakter',
            ]);

            $position->update([
                'name' => $validated['nama'],
                'description' => $validated['deskripsi'],
            ]);

            return response()->json([
                'message' => 'Jabatan berhasil diperbarui',
                'code' => 200,
                'payload' => new PositionResource($position)
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui jabatan',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $position = Position::find($id);

            if (!$position) {
                return response()->json([
                    'message' => 'Jabatan tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            $position->delete();

            return response()->json([
                'message' => 'Jabatan berhasil dihapus',
                'code' => 200,
                'payload' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menghapus jabatan',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }
}
