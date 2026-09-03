<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Http\Resources\RoleResource;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index()
    {
        try {
            $roles = Role::with('permissions')->get();

            if ($roles->isEmpty()) {
                return response()->json([
                    'message' => 'Tidak ada data role',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            return response()->json([
                'message' => 'Data role berhasil diambil',
                'code' => 200,
                'payload' => RoleResource::collection($roles)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data role',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nama' => 'required|string|unique:roles,name',
            ], [
                'nama.required' => 'Nama role harus diisi',
                'nama.string' => 'Nama role harus berupa string',
                'nama.unique' => 'Nama role sudah terdaftar',
            ]);

            $role = Role::create([
                'name' => $validated['nama'],
                'guard_name' => 'web',
            ]);

            return response()->json([
                'message' => 'Role berhasil ditambahkan',
                'code' => 201,
                'payload' => new RoleResource($role->load('permissions'))
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat membuat role',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $role = Role::with('permissions')->find($id);

            if (!$role) {
                return response()->json([
                    'message' => 'Role tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            return response()->json([
                'message' => 'Data role berhasil diambil',
                'code' => 200,
                'payload' => new RoleResource($role)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data role',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'message' => 'Role tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            $validated = $request->validate([
                'nama' => 'required|string|unique:roles,name,' . $id,
            ], [
                'nama.required' => 'Nama role harus diisi',
                'nama.string' => 'Nama role harus berupa string',
                'nama.unique' => 'Nama role sudah terdaftar',
            ]);

            $role->update([
                'name' => $validated['nama'],
            ]);

            return response()->json([
                'message' => 'Role berhasil diperbarui',
                'code' => 200,
                'payload' => new RoleResource($role->load('permissions'))
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui role',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'message' => 'Role tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            $role->delete();

            return response()->json([
                'message' => 'Role berhasil dihapus',
                'code' => 200,
                'payload' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menghapus role',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function assignPermissionToRole(Request $request, $id)
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'message' => 'Role tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            $validated = $request->validate([
                'izin' => 'required|array',
                'izin.*' => 'string|exists:permissions,name',
            ], [
                'izin.required' => 'Izin harus diisi',
                'izin.array' => 'Izin harus berupa array',
                'izin.*.exists' => 'Beberapa izin tidak ditemukan',
            ]);

            $role->syncPermissions($validated['izin']);

            return response()->json([
                'message' => 'Permission berhasil ditambahkan ke role',
                'code' => 200,
                'payload' => new RoleResource($role->load('permissions'))
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menambahkan permission ke role',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }
}
