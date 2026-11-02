<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kths', function (Blueprint $table) {
            if (!Schema::hasColumn('kths', 'nomor_sk')) {
                $table->string('nomor_sk')->nullable()->after('desa_kelurahan');
            }
            if (!Schema::hasColumn('kths', 'no_wa_ketua')) {
                $table->string('no_wa_ketua', 50)->nullable()->after('ketua');
            }
            if (!Schema::hasColumn('kths', 'jumlah_anggota')) {
                $table->string('jumlah_anggota', 50)->nullable()->after('no_wa_ketua');
            }
            if (!Schema::hasColumn('kths', 'kelas_kth')) {
                $table->string('kelas_kth', 100)->nullable()->after('jenis_usaha');
            }
            if (!Schema::hasColumn('kths', 'alamat_sekretariat')) {
                $table->text('alamat_sekretariat')->nullable()->after('kelas_kth');
            }
            if (!Schema::hasColumn('kths', 'nama_bank')) {
                $table->string('nama_bank', 100)->nullable()->after('alamat_sekretariat');
            }
            if (!Schema::hasColumn('kths', 'no_rekening')) {
                $table->string('no_rekening', 50)->nullable()->after('nama_bank');
            }
            if (!Schema::hasColumn('kths', 'nama_pemilik_rekening')) {
                $table->string('nama_pemilik_rekening', 255)->nullable()->after('no_rekening');
            }
            if (!Schema::hasColumn('kths', 'foto_profil')) {
                $table->string('foto_profil')->nullable()->after('nama_pemilik_rekening');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kths', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'nomor_sk',
                'no_wa_ketua',
                'jumlah_anggota',
                'kelas_kth',
                'alamat_sekretariat',
                'nama_bank',
                'nama_pemilik_rekening',
                'foto_profil',
            ], fn($col) => Schema::hasColumn('kths', $col));

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};

