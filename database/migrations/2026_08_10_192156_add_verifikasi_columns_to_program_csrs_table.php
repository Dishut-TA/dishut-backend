<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('program_csrs', function (Blueprint $table) {
            $table->text('catatan_staff')->nullable()->after('status');
            $table->string('rekomendasi_mitra')->nullable()->after('catatan_staff');
            $table->string('rekomendasi_intervensi')->nullable()->after('rekomendasi_mitra');
        });
    }
    public function down(): void {
        Schema::table('program_csrs', function (Blueprint $table) {
            $table->dropColumn(['catatan_staff', 'rekomendasi_mitra', 'rekomendasi_intervensi']);
        });
    }
};