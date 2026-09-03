# 📖 Catatan Progres Pengerjaan (Project Progress Log)

Catatan ini dibuat khusus sebagai "ingatan" sistem (*memory bank*) agar saya tidak lupa tentang semua hal yang telah kita kerjakan dan selesaikan di *repository* `dishut-service-master` ini.

## ✅ Modul Donasi (Selesai)
- **Tujuan:** Membuat alur donasi dengan fitur otomatisasi kode bibit dan integrasi antar model (Banyak ke Banyak).
- **Apa yang sudah dikerjakan:**
  1. Perbaikan relasi `DonationProgram` dengan `Seed` menggunakan tabel perantara (`donation_program_seed`).
  2. Modifikasi Controller `DonationProgramController` untuk secara otomatis mengisi status `'Menunggu Verifikasi'`.
  3. Modifikasi Controller `SeedController` untuk secara otomatis membuat kode unik (misal: `B-001`) pada bibit yang diinput.
  4. Penyeragaman pengambilan detail menggunakan rute `getById` di semua Service (termasuk Kota, Kecamatan, Desa, dsb).
  5. Pembuatan `AdminDashboardController` untuk *dashboard* statistik.
  6. Memastikan `cors.php` mengakomodasi akses dari Vercel Admin dan Public.

## ✅ Modul CPI & Rekomendasi Intervensi (Selesai)
- **Tujuan:** Menambahkan alur validasi lapangan dan verifikasi kelayakan lahan berlapis agar data bisa dipertanggungjawabkan (sesuai dokumen `prd_cpi_.md`).
- **Apa yang sudah dikerjakan:**
  1. Penambahan field Master Referensi (`cdk`, `nama_kelompok`, `ketua_kelompok`, `status_validasi_penyuluh`, `status_kelayakan`) secara bersih dan siap produksi (tanpa alter) pada tabel zona analisis asli.
  2. Pembuatan Model & Migrasi baru bernama `FieldValidation` untuk menampung formulir, catatan, dan foto lapangan dari Penyuluh.
  3. Pembuatan `FieldValidationController` dengan alur:
     - `POST /api/field-validations` (Penyuluh men-submit data survei, mengubah status zona jadi divalidasi).
     - `PUT /api/field-validations/{id}/verify` (Kabid menentukan 'Terima' / 'Tolak', mengubah status zona jadi 'Layak' / 'Tidak Layak').
     - `GET /api/projects/{id}/report-rurhl` (Rute API untuk *generate* laporan area yang berstatus 'Layak').
  4. Sinkronisasi *endpoint* internal antara PHP dengan AI (Python).

## 🧪 Pengujian (Testing) API
- **Automated Testing:** Telah menjalankan `php artisan test` dan fitur lolos unit/feature test bawaan Laravel tanpa error.
- **Manual Testing (Postman):** Telah memperbarui file `postman.txt` dengan 18 endpoint komprehensif. Berkas ini berfungsi sebagai instruksi (*prompt*) lengkap untuk AI Postman agar dapat men-*generate* keseluruhan *collection* pengujian secara otomatis (mengakomodasi modul CRUD, Donasi, hingga Validasi Lapangan).

## ☁️ Diskusi Deployment (Python CPI Engine)
- **Problem:** Terjadi *error Connection Refused* (`Tidak dapat terhubung ke Python CPI Engine di http://127.0.0.1:8001...`) saat testing karena *service* Python tidak berjalan atau salah port.
- **Solusi Lokal:** Menjalankan perintah `uvicorn main:app --host 127.0.0.1 --port 8001` saat testing di lokal.
- **Solusi VPS/Production:** Disarankan tidak menggunakan *command* `uvicorn` manual di terminal. Pilihan yang dianjurkan:
  1. **PM2:** Paling gampang untuk *keep-alive* di background (contoh: `pm2 start "uvicorn main:app --port 8001" --name cpi-engine`).
  2. **Docker / Docker Compose:** Standar industri, bersih, portabel.
  3. **Systemd Service:** Bawaan Linux yang *native* dan ringan.
  4. **Gunicorn + Uvicorn Workers:** Untuk menangani trafik atau skala besar.

## 🚀 Status Kode
- Seluruh pengerjaan fitur di atas telah lulus *syntax check* dan telah **dikirim (*push*) ke branch `main` GitHub (Dishut-TA/dishut-service-master)**.
- **Tugas Tersisa (Kewajiban Pengguna):** Mengaktifkan database MySQL dan menjalankan `php artisan migrate:fresh` sebelum API dapat dipakai oleh Frontend.

---
*Catatan ini akan terus saya simpan dan baca di penyimpanan otak (artifacts) saya, sehingga jika Anda kembali bertanya di masa mendatang atau jika sesi ini terpotong, saya akan selalu ingat progres terakhir kita!*
