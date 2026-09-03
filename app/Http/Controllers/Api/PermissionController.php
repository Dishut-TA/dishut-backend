<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use App\Http\Resources\PermissionResource;
use Illuminate\Validation\ValidationException;

class PermissionController extends Controller
{
    public function index()
    {
        try {
            $permissions = Permission::all();

            if ($permissions->isEmpty()) {
                return response()->json([
                    'message' => 'Tidak ada data permission',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            return response()->json([
                'message' => 'Data permission berhasil diambil',
                'code' => 200,
                'payload' => PermissionResource::collection($permissions)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data permission',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nama' => 'required|string|unique:permissions,name',
                'nama_penjaga' => 'nullable|string',
            ], [
                'nama.required' => 'Nama permission harus diisi',
                'nama.string' => 'Nama permission harus berupa string',
                'nama.unique' => 'Nama permission sudah terdaftar',
            ]);

            $permission = Permission::create([
                'name' => $validated['nama'],
                'guard_name' => $validated['nama_penjaga'] ?? 'web',
            ]);

            return response()->json([
                'message' => 'Permission berhasil ditambahkan',
                'code' => 201,
                'payload' => new PermissionResource($permission)
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat membuat permission',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $permission = Permission::find($id);

            if (!$permission) {
                return response()->json([
                    'message' => 'Permission tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            return response()->json([
                'message' => 'Data permission berhasil diambil',
                'code' => 200,
                'payload' => new PermissionResource($permission)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data permission',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $permission = Permission::find($id);

            if (!$permission) {
                return response()->json([
                    'message' => 'Permission tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            $validated = $request->validate([
                'nama' => 'required|string|unique:permissions,name,' . $id,
                'nama_penjaga' => 'nullable|string',
            ], [
                'nama.required' => 'Nama permission harus diisi',
                'nama.string' => 'Nama permission harus berupa string',
                'nama.unique' => 'Nama permission sudah terdaftar',
            ]);

            $permission->update([
                'name' => $validated['nama'],
                'guard_name' => $validated['nama_penjaga'] ?? $permission->guard_name,
            ]);

            return response()->json([
                'message' => 'Permission berhasil diperbarui',
                'code' => 200,
                'payload' => new PermissionResource($permission)
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui permission',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $permission = Permission::find($id);

            if (!$permission) {
                return response()->json([
                    'message' => 'Permission tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            $permission->delete();

            return response()->json([
                'message' => 'Permission berhasil dihapus',
                'code' => 200,
                'payload' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menghapus permission',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }
}
