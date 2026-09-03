# Changelog & Update Log: Backend (Dishut-Service-Master)
Dokumen ini merangkum seluruh perubahan sistem *Backend* (Laravel) pada repositori `dishut-service-master`. 
*Catatan: Dokumen ini mengabaikan integrasi `user-service-master` (seperti Auth, Roles, Permissions) agar Anda dapat fokus memigrasikan pembaruan modul spasial ke workspace backend khusus.*

---

## 1. Modul Analisis Lahan Kritis (CPI Engine)
### A. Perbaikan Crash & Parsing Error Python
- **File Modifikasi**: `app/Services/CpiEngineService.php`
- **Masalah**: Saat Engine Python mengembalikan HTTP 500 dengan *body* berbentuk JSON (*array* bertumpuk), Laravel *crash* saat mencoba menyimpannya dengan *error* `Array to string conversion`.
- **Solusi**: Memperbaiki blok `catch` agar secara rekursif mengonversi balasan *error* ke dalam bentuk *string* (`json_encode`) sebelum menyimpannya ke *database*.

### B. Penyempurnaan Sinkronisasi Wilayah (Zonasi) & Fix Deadlock
- **File Modifikasi**: `app/Http/Controllers/AnalysisProjectController.php`
- **Masalah**: Sebelumnya, saat menggunakan `$zoneApiUrl = url('/api/zones')`, aplikasi mengalami *Deadlock* / `Read timed out` (HTTP 502) karena `php artisan serve` hanya bersifat *single-threaded*, sehingga ia tidak bisa merespons permintaan Python saat sedang menunggu Python selesai.
- **Solusi**: Mengganti mekanisme HTTP Callback menjadi sinkronisasi file lokal. Laravel kini mengekstrak tabel `villages` menjadi file `zones_master_local.geojson` secara *on-the-fly* sebelum memanggil Python, dan langsung mengirimkan path file tersebut via parameter `adminPath`. Hasilnya: *Deadlock* terselesaikan sepenuhnya dan nama-nama wilayah dapat disisipkan secara instan.

### C. Seeder Data Uji Coba Geospasial (Gunung Halu)
- **File Baru**: `database/seeders/GunungHaluSeeder.php`
- **Tujuan**: Mengisi *database* dengan poligon geometri (*GeoJSON*) fiktif di dalam radius Kecamatan Gunung Halu agar Engine Python dapat sukses memetakan dan menyuntikkan nama desa ("Desa Celak", "Desa Sirnajaya", dsb) ke dalam tabel hasil CPI.
- **Isi Seeder**: 
  1. Membuat entri Kabupaten Bandung Barat.
  2. Membuat entri Kecamatan Gunung Halu.
  3. Meng- *generate* 4 titik kuadran Bounding Box (BBOX) menjadi geometri Poligon untuk 4 Desa, dan memasukannya ke tabel `villages`.
- *Catatan Migrasi*: Anda cukup menyalin file seeder ini ke *workspace backend* Anda dan menjalankannya dengan `php artisan db:seed --class=GunungHaluSeeder`.

---

## 2. Modul Pelaksanaan & Monitoring
### A. Endpoint Dashboard API
- **File Modifikasi**: `routes/api.php`
- **Perubahan**: Mendaftarkan *route* baru `GET /monitoring/dashboard`.
- **File Baru**: `app/Http/Controllers/Api/MonitoringController.php`
- **Fungsi**: 
  1. Method `dashboard()` diciptakan untuk merespons permintaan agregat (Statistik jumlah kegiatan Selesai, Berjalan, Bermasalah) dari tabel `field_validations`.
  2. Mengonversi teks *string* koordinat (contoh: "-6.92, 107.60") menjadi *array* Latitude & Longitude agar dapat digambar (*render*) sebagai *marker* di peta Leaflet. (Dengan *fallback* ke 3 lokasi *dummy* apabila tabel tersebut kosong).
