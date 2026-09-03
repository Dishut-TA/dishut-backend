<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Penyuluh
        $penyuluh = User::updateOrCreate(
            ['email' => 'penyuluh1@dinhut.com'],
            [
                'username' => 'penyuluh_jabar',
                'password' => Hash::make('password123'),
            ]
        );
        // Pastikan role ada, lalu assign
        $rolePenyuluh = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'penyuluh', 'guard_name' => 'web']);
        $penyuluh->assignRole($rolePenyuluh);

        // 2. Akun Kepala Bidang PDAS
        $kabid = User::updateOrCreate(
            ['email' => 'kabidpdas@dinhut.com'],
            [
                'username' => 'kabid_pdas',
                'password' => Hash::make('password123'),
            ]
        );
        $roleKabid = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Kepala Bidang PDAS', 'guard_name' => 'web']);
        $kabid->assignRole($roleKabid);

        // 3. Akun Staff PDAS
        $staff = User::updateOrCreate(
            ['email' => 'staffpdas@dinhut.com'],
            [
                'username' => 'staff_pdas',
                'password' => Hash::make('password123'),
            ]
        );
        $roleStaff = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Staff PDAS', 'guard_name' => 'web']);
        $staff->assignRole($roleStaff);

        // 4. Akun KTH Pelaksanaan
        $kth = User::updateOrCreate(
            ['email' => 'kthpelaksanaan@dinhut.com'],
            [
                'username' => 'kth_pelaksanaan',
                'password' => Hash::make('password123'),
            ]
        );
        // Gunakan nama role sesuai dengan frontend/backend, asumsi: kth_pelaksanaan
        $roleKth = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'KTH Pelaksanaan', 'guard_name' => 'web']);
        $kth->assignRole($roleKth);
    }
}
