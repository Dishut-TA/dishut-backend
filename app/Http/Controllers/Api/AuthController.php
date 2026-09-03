<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'nama_pengguna' => 'required|string|max:255|unique:users,username',
                'email' => 'required|string|email|max:255|unique:users,email',
                'nip' => 'nullable|string|min:18|max:18|unique:pegawais,nip',
                'kata_sandi' => 'required|string|min:6',
            ], [
                'nama_pengguna.required' => 'Nama pengguna harus diisi',
                'nama_pengguna.unique' => 'Nama pengguna sudah terdaftar',
                'email.required' => 'Email harus diisi',
                'email.unique' => 'Email sudah terdaftar',
                'nip.unique' => 'NIP sudah terdaftar',
                'kata_sandi.required' => 'Kata sandi harus diisi',
                'kata_sandi.min' => 'Kata sandi minimal 6 karakter',
            ]);

            $user = User::create([
                'username' => $validated['nama_pengguna'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['kata_sandi']),
            ]);

            $user->pegawai()->create([
                'nip' => $validated['nip'] ?? null,
            ]);

            $user->load('pegawai');


            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Register berhasil',
                'code' => 201,
                'payload' => [
                    'token' => $token,
                    'user' => new UserResource($user)
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat register',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'login' => 'required|string',
                'kata_sandi' => 'required|string',
            ], [
                'login.required' => 'Email/NIP harus diisi',
                'kata_sandi.required' => 'Kata sandi harus diisi',
            ]);

            $user = User::where('email', $validated['login'])
                ->orWhere('username', $validated['login'])
                ->orWhereHas('pegawai', function ($query) use ($validated) {
                    $query->where('nip', $validated['login']);
                })
                ->first();

            if (!$user || !Hash::check($validated['kata_sandi'], $user->password)) {
                return response()->json([
                    'message' => 'Email/NIP atau kata sandi salah',
                    'code' => 401,
                    'payload' => null
                ], 401);
            }

            $user->load('pegawai');

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login berhasil',
                'code' => 200,
                'payload' => [
                    'token' => $token,
                    'user' => new UserResource($user)
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first(),
                'code' => 422,
                'payload' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat login',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function me(Request $request)
    {
        try {
            return response()->json([
                'message' => 'Data user berhasil diambil',
                'code' => 200,
                'payload' => new UserResource($request->user()->load(['roles.permissions', 'pegawai']))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data user',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Logout berhasil',
                'code' => 200,
                'payload' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat logout',
                'code' => 500,
                'payload' => null
            ], 500);
        }
    }
}
