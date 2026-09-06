<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = User::with(['roles', 'pegawai']);
            
            if ($request->has('role')) {
                $query->role($request->role);
            }
            
            $users = $query->get();

            if ($users->isEmpty()) {
                return response()->json([
                    'message' => 'Tidak ada data user',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            return response()->json([
                'message' => 'Data user berhasil diambil',
                'code' => 200,
                'payload' => UserResource::collection($users)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data user',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Store User Request:', $request->all());
        try {
            $validated = $request->validate([
                'nama_pengguna' => 'required|string|max:255|unique:users,username',
                'email' => 'required|string|email|max:255|unique:users,email',
                'nip' => 'nullable|string|min:18|max:18|unique:pegawais,nip',
                'kata_sandi' => 'required|string|min:6',
                'peran' => 'nullable|array',
                'peran.*' => 'string|exists:roles,name',
                'kth_id' => 'nullable|exists:kths,id',
            ], [
                'nama_pengguna.required' => 'Nama pengguna harus diisi',
                'nama_pengguna.unique' => 'Nama pengguna sudah terdaftar',
                'email.required' => 'Email harus diisi',
                'email.unique' => 'Email sudah terdaftar',
                'nip.unique' => 'NIP sudah terdaftar',
                'kata_sandi.required' => 'Kata sandi harus diisi',
                'kata_sandi.min' => 'Kata sandi minimal 6 karakter',
                'peran.array' => 'Peran harus berupa array',
                'peran.*.exists' => 'Beberapa peran tidak ditemukan',
            ]);

            $user = User::create([
                'username' => $validated['nama_pengguna'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['kata_sandi']),
                'kth_id' => $validated['kth_id'] ?? null,
            ]);

            $user->pegawai()->create([
                'nip' => $validated['nip'] ?? null,
            ]);

            if (!empty($validated['peran'])) {
                $user->syncRoles($validated['peran']);
            }

            return response()->json([
                'message' => 'User berhasil ditambahkan',
                'code' => 201,
                'payload' => new UserResource($user->load(['roles', 'pegawai']))
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menambahkan user',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function showUserWithRolesAndPermissions($id)
    {
        try {
            $user = User::with(['roles.permissions', 'pegawai'])->find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            // Get all permissions for the user (from roles + direct permissions)
            $permissions = $user->getAllPermissions();

            return response()->json([
                'message' => 'Detail user berhasil diambil',
                'code' => 200,
                'payload' => [
                    'user' => new UserResource($user)
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data user',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            $validated = $request->validate([
                'nama_pengguna' => 'required|string|max:255|unique:users,username,' . $id,
                'email' => 'required|string|email|max:255|unique:users,email,' . $id,
                'nip' => 'nullable|string|min:18|max:18|unique:pegawais,nip,' . ($user->pegawai ? $user->pegawai->id : 'NULL'),
                'kata_sandi' => 'nullable|string|min:6',
            ], [
                'nama_pengguna.required' => 'Nama pengguna harus diisi',
                'nama_pengguna.unique' => 'Nama pengguna sudah terdaftar',
                'email.required' => 'Email harus diisi',
                'email.unique' => 'Email sudah terdaftar',
                'nip.unique' => 'NIP sudah terdaftar',
                'kata_sandi.min' => 'Kata sandi minimal 6 karakter',
            ]);

            $userData = [
                'username' => $validated['nama_pengguna'],
                'email' => $validated['email'],
            ];

            if (!empty($validated['kata_sandi'])) {
                $userData['password'] = Hash::make($validated['kata_sandi']);
            }

            if (array_key_exists('kth_id', $validated)) {
                $userData['kth_id'] = $validated['kth_id'];
            }

            $user->update($userData);

            if (array_key_exists('nip', $validated)) {
                $user->pegawai()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['nip' => $validated['nip']]
                );
            }

            return response()->json([
                'message' => 'User berhasil diperbarui',
                'code' => 200,
                'payload' => new UserResource($user->load('pegawai'))
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui user',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            $user->delete();

            return response()->json([
                'message' => 'User berhasil dihapus',
                'code' => 200,
                'payload' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menghapus user',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function assignRoleToUser(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User tidak ditemukan',
                    'code' => 404,
                    'payload' => null
                ], 404);
            }

            $validated = $request->validate([
                'peran' => 'required|array',
                'peran.*' => 'string|exists:roles,name',
                'kth_id' => 'nullable|exists:kths,id',
            ], [
                'peran.required' => 'Peran harus diisi',
                'peran.array' => 'Peran harus berupa array',
                'peran.*.exists' => 'Beberapa peran tidak ditemukan',
            ]);

            $user->syncRoles($validated['peran']);

            return response()->json([
                'message' => 'Role berhasil ditambahkan ke user',
                'code' => 200,
                'payload' => new UserResource($user->load('roles'))
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menambahkan role ke user',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }
}
