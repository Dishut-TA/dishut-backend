# 📄 Product Requirements Document (PRD)
**Integrasi Frontend & Backend Service (Auth & RBAC)**

## 1. Latar Belakang & Tujuan
Saat ini, **Service Master** (`dishut-service-master`) telah memiliki fungsionalitas bisnis lengkap seperti validasi lapangan (CPI) dan manajemen donasi. Namun, aplikasi ini memerlukan mekanisme autentikasi dan **Role-Based Access Control (RBAC)** (Admin, Penyuluh, Kabid, dll) yang dikelola secara terpusat oleh **Service User**. 

Tujuan PRD ini adalah untuk memandu proses integrasi antara **Frontend**, **Service User (Auth)**, dan **Service Master** agar terhubung dengan mulus saat berada di _workspace_ lokal maupun saat di-_deploy_.

## 2. Arsitektur Sistem
- **Service User (Authentication Center):** Bertanggung jawab untuk validasi kredensial pengguna (Login), manajemen pengguna, dan penerbitan **JWT (JSON Web Token)** yang mencakup informasi *Role*.
- **Service Master (Business Logic):** Bertanggung jawab atas proses data inti. Service ini akan memverifikasi JWT dari Frontend (menggunakan *Secret Key* yang sama dengan Service User) dan menjalankan _middleware_ untuk membatasi akses berdasar _role_ (contoh: hanya Kabid yang bisa menyetujui Validasi Lapangan).
- **Frontend (Web/Admin):** Antarmuka pengguna yang bertanggung jawab menyimpan Token (di `localStorage` atau `HttpOnly Cookies`) dan menyematkan Token pada header `Authorization: Bearer <token>` untuk setiap _request_ API ke Service manapun.

## 3. Kebutuhan Fitur Utama (Requirements)

### A. Sisi Frontend (Aplikasi Klien)
1. **Halaman Autentikasi:** Form login yang mengirim permintaan ke endpoint `Service User`.
2. **State Management / Sesi:** Menyimpan informasi profil dan Role dari respons login untuk mengatur _conditional rendering_ (misal: Tombol "Verifikasi Kelayakan" hanya dirender jika role user = `kabid`).
3. **Axios Interceptor:** Secara otomatis menyematkan JWT di header HTTP di setiap panggilan API, baik ke Service User maupun Service Master.
4. **Pemisahan URL Base:** Memiliki konfigurasi environment (misal `.env.local`) yang memisahkan `VITE_API_USER_URL` dan `VITE_API_MASTER_URL`.

### B. Sisi Service User (Backend Auth)
1. **Endpoint Login:** Mengembalikan Token yang ter-enkripsi. Payload JWT wajib memuat:
   - `user_id`
   - `name`
   - `role` (contoh: `admin`, `penyuluh`, `kabid`)
2. **Manajemen RBAC:** Menyediakan pengelolaan pengguna untuk menambah Penyuluh atau Admin baru.

### C. Sisi Service Master (Backend Utama)
1. **Middleware Auth & RBAC:** Membaca header `Bearer Token` dan memvalidasinya. Jika valid, meneruskan request. Jika tidak/role tidak sesuai, kembalikan HTTP `401 Unauthorized` atau `403 Forbidden`.
2. **Integrasi Data (Opsional/Sinkronisasi):** Menggunakan `user_id` dari Token untuk mengisi *field* seperti `nama_penyuluh` atau mencatat log aktivitas (Audit Trail).
3. **Sinkronisasi Kunci (Secret Key):** Harus menggunakan algoritma JWT (seperti HS256 atau RS256) dengan *Secret/Public Key* yang sama dengan *Service User* agar dapat mendekode token tanpa harus melakukan request HTTP terus menerus ke Service User.

## 4. Alur Kerja (Workflows)

**Skenario: Penyuluh Menginput Validasi Lapangan**
1. **Frontend:** Penyuluh melakukan Login.
2. **Service User:** Memeriksa kredensial, mengembalikan JWT (payload: `role=penyuluh`).
3. **Frontend:** Menyimpan Token, lalu menavigasi ke halaman form CPI.
4. **Frontend:** Saat Penyuluh submit form, mengirim POST `/api/field-validations` ke **Service Master** dengan header `Authorization: Bearer <token>`.
5. **Service Master:** Menangkap request, _Middleware_ mendekode Token, memastikan role adalah `penyuluh` atau `admin`, lalu menyimpan data ke tabel `field_validations`.
6. **Frontend:** Menampilkan notifikasi "Berhasil Disimpan".

## 5. Rencana Eksekusi (Action Plan Berikutnya)
1. **Persiapan Workspace:** Pull / Clone repository **Frontend** dan **Service User** ke dalam mesin lokal (bisa di _root_ workspace yang sama atau berdampingan).
2. **Konfigurasi Environment:** 
   - Menyamakan `JWT_SECRET` pada `.env` Service User dan `.env` Service Master.
   - Mengatur URL API pada `.env` Frontend.
3. **Penerapan Middleware (Di Service Master):** 
   - Membuat/memodifikasi middleware JWT untuk melindungi endpoint yang ada di `api.php`.
4. **Penyesuaian Frontend:** Menghubungkan form dan tabel yang ada di UI dengan endpoint nyata dari Service Master.

---
*Dokumen ini siap dijadikan acuan saat Anda kembali atau sudah berpindah workspace.*
