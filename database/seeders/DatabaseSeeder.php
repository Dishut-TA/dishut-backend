<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Position;
use App\Models\Rank;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. SEED PERMISSIONS
        $permissions = [
            'kelola-user',
            'kelola-role',
            'kelola-permission',
            'kelola-jabatan',
            'kelola-pangkat',
            'lihat-data'
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web'
            ]);
        }

        // 2. SEED ROLES & SYNC PERMISSIONS
        $roleNames = [
            'super_admin',
            'admin',
            'pegawai',
            'Kepala Bidang PDAS',
            'Staff PDAS',
            'staff_bupm',
            'kepala_bupm',
            'csr',
            'kth',
            'penyuluh'
        ];

        $roles = [];
        foreach ($roleNames as $roleName) {
            $roles[$roleName] = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web'
            ]);
            $roles[$roleName]->syncPermissions(Permission::all());
        }

        // 3. SEED USERS & ASSIGN ROLES
        
        // Super Admin (Bawaan)
        $superAdmin = User::firstOrCreate(
            ['username' => 'superadmin'],
            [
                'email' => 'superadmin@example.com',
                'password' => Hash::make('password'),
            ]
        );
        $superAdmin->assignRole($roles['super_admin']);
        $superAdmin->pegawai()->firstOrCreate([
            'nip' => '197107101990032000',
        ], [
            'no_telp' => '081234567890',
            'tanggal_lahir' => '1971-07-10',
            'alamat' => 'Jl. Kehutanan No. 1, Bandung',
            'foto_profile' => 'default.png',
        ]);

        // Daftar User Kustom
        $customUsers = [
            ['username' => 'lasmawati', 'email' => 'lasmawati@gmail.com', 'role' => 'Kepala Bidang PDAS', 'nip' => '1001'],
            ['username' => 'algiffari', 'email' => 'algiffari@gmail.com', 'role' => 'Staff PDAS', 'nip' => '1002'],
            ['username' => 'ervan', 'email' => 'ervan@gmail.com', 'role' => 'staff_bupm', 'nip' => '1003'],
            ['username' => 'irshal', 'email' => 'irshal@gmail.com', 'role' => 'kepala_bupm', 'nip' => '1004'],
            ['username' => 'csr_bjb', 'email' => 'csr@bjb.co.id', 'role' => 'csr', 'nip' => '1005'],
            ['username' => 'raisha', 'email' => 'raisha@gmail.com', 'role' => 'kth', 'nip' => '1006'],
            ['username' => 'rizkya', 'email' => 'rizkya@gmail.com', 'role' => 'penyuluh', 'nip' => '1007'],
        ];

        foreach ($customUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'username' => $userData['username'],
                    'password' => Hash::make('apasilukepo'),
                ]
            );
            $user->assignRole($roles[$userData['role']]);
            $user->pegawai()->firstOrCreate([
                'nip' => $userData['nip'],
            ], [
                'no_telp' => '080000000000',
                'tanggal_lahir' => '1990-01-01',
                'alamat' => 'Alamat ' . $userData['username'],
                'foto_profile' => 'default.png',
            ]);

            // Profil mitra CSR. Wajib ada karena kepemilikan program di Modul
            // Investasi CSR dibaca lewat csrs -> transaksi_csrs.
            if ($userData['role'] === 'csr') {
                $user->csr()->firstOrCreate([], [
                    'nama_perusahaan' => 'PT Bank BJB',
                    'no_telepon' => '022123456',
                    'alamat' => 'Jl. Naripan No. 12-14, Bandung',
                ]);
            }
        }

        // 4. SEED POSITIONS (JABATAN)
        $positions = [
            [
                'name' => 'Kepala Dinas Kehutanan',
                'description' => 'Kepala Dinas Kehutanan Provinsi'
            ],
            [
                'name' => 'Sekretaris Dinas',
                'description' => 'Sekretaris Dinas Kehutanan'
            ],
            [
                'name' => 'Kepala Bidang Perlindungan Hutan',
                'description' => 'Bidang Perlindungan dan Konservasi Sumber Daya Alam'
            ],
            [
                'name' => 'Kepala Bidang Penyuluhan Kehutanan',
                'description' => 'Bidang Pemberdayaan Masyarakat dan Penyuluhan'
            ],
            [
                'name' => 'Polisi Kehutanan Ahli Madya',
                'description' => 'Pejabat Fungsional Polisi Kehutanan'
            ],
            [
                'name' => 'Penyuluh Kehutanan Ahli Pratama',
                'description' => 'Pejabat Fungsional Penyuluh Kehutanan'
            ],
            [
                'name' => 'Staf Administrasi Umum',
                'description' => 'Staf Pelaksana Urusan Administrasi Kepegawaian dan Umum'
            ]
        ];

        foreach ($positions as $pos) {
            Position::firstOrCreate(
                ['name' => $pos['name']],
                ['description' => $pos['description']]
            );
        }

        // 5. SEED RANKS (PANGKAT & GOLONGAN)
        $ranks = [
            [
                'name' => 'Pembina Utama Muda',
                'group' => 'IV/c',
                'grade' => 'c'
            ],
            [
                'name' => 'Pembina Tingkat I',
                'group' => 'IV/b',
                'grade' => 'b'
            ],
            [
                'name' => 'Pembina',
                'group' => 'IV/a',
                'grade' => 'a'
            ],
            [
                'name' => 'Penata Tingkat I',
                'group' => 'III/d',
                'grade' => 'd'
            ],
            [
                'name' => 'Penata',
                'group' => 'III/c',
                'grade' => 'c'
            ],
            [
                'name' => 'Penata Muda Tingkat I',
                'group' => 'III/b',
                'grade' => 'b'
            ],
            [
                'name' => 'Penata Muda',
                'group' => 'III/a',
                'grade' => 'a'
            ]
        ];

        foreach ($ranks as $rank) {
            Rank::firstOrCreate(
                ['name' => $rank['name']],
                [
                    'group' => $rank['group'],
                    'grade' => $rank['grade']
                ]
            );
        }

        // Merged from DINHUT
        $this->call([
            CitySeeder::class,
            DistrictSeeder::class,
            VillageSeeder::class,
            LandSeeder::class,
            SeedSeeder::class,
            InterventionTypeSeeder::class,
            InterventionRecommendationSeeder::class,
        ]);
    }
}
