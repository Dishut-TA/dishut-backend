# 📋 PRD BACKEND: REVISI MODUL DONASI (FULL COMPLETE)

**Status:** Final Revision
**Tanggal Update:** 25 Juli 2026

---

## 🗄️ 1. SKEMA DATABASE & MIGRASI (DATABASE SCHEMA)

### A. Master Data Bibit & Spesifikasi (`master_bibit` & `bibit_spesifikasi`)
Menggunakan relasi **One-to-Many** agar 1 jenis bibit bisa punya banyak spesifikasi tinggi, SKU, stok, dan harga fixed tersendiri.

* **Tabel `master_bibit`**:
  * `id` (PK)
  * `nama_bibit` *(VARCHAR — Validasi regex: tidak boleh berisi angka ssaja)*
  * `kategori` *(ENUM: `'Kehutanan/Hias'`, `'Buah-Buahan'`)*
  * `status_sertifikasi` *(VARCHAR)*

* **Tabel `bibit_spesifikasi`** *(Pemisahan SKU & Stok per Tinggi)*:
  * `id` (PK)
  * `master_bibit_id` *(FK ke `master_bibit.id`)*
  * `sku_code` *(VARCHAR Unique — Auto-generated, contoh: `BBT-001`)*
  * `tinggi_bibit` *(VARCHAR — contoh: `'30–60 cm'`)*
  * `harga_satuan` *(BIGINT — Nominal fixed per batang, TIDAK BISA rentang harga)*
  * `stok_tersedia` *(INT — Jumlah stok fisik varian tersebut)*

### B. Tabel Program (`programs`)
* `id` (PK)
* `status_program` -> **`ENUM('Menunggu Proses', 'Aktif', 'Ditolak','Selesai')`**
* `tanggal_mulai` & `tanggal_selesai` *(DATE — Siklus periode program)*
* `kth_id` *(FK — Auto-fill ditarik otomatis berdasarkan lokasi CPI yang dipilih)*
* `target_bibit` -> **[DI-DROP / DIHAPUS TOTAL dari database]**

### C. Tabel Transaksi Donasi (`donations` & `donation_items`)
Sistem menggunakan **Dual Status System** untuk memisahkan alur uang dan alur fisik bibit:

* **Tabel `donations`**:
  * `id` (PK / `ID_Donasi`)
  * `program_id` *(FK ke `programs.id`)*
  * `donatur_name` *(VARCHAR)*
  * **`status_transaksi`** -> *Alur Keuangan* *(ENUM: `'Pending'`, `'Berhasil'`, `'Gagal'`)*
  * **`status_bibit`** -> *Alur Fisik Bibit* *(ENUM: `'Menunggu Verifikasi'`, `'Terkumpul'`, `'Disalurkan'`, `'Terealisasi'`)*
  * `file_bast_url` *(VARCHAR / Nullable — Path dokumen BAST)*
  * `disalurkan_at` *(TIMESTAMP / Nullable)*

* **Tabel `donation_items`** *(Price Snapshot Pattern)*:
  * `id` (PK)
  * `donation_id` *(FK)*
  * `bibit_spesifikasi_id` *(FK ke `bibit_spesifikasi.id`)*
  * `jumlah_bibit` *(INT)*
  * `harga_bibit` *(BIGINT — Mengunci harga per batang saat transaksi terjadi agar laporan historis tidak terpengaruh jika harga master naik)*

---

## ⚙️ 2. ATURAN BISNIS & VALIDASI BACKEND (BUSINESS LOGIC)

1. **Validasi Regex Nama Bibit:** String input `nama_bibit` wajib mengandung huruf/alfabet dan ditolak jika hanya diketik numerik/angka.
2. **Cascading Validation Tinggi Bibit (Enum Match):**
   * Jika `kategori` = `'Kehutanan/Hias'` -> Backend hanya meloloskan `tinggi_bibit`: `30–60 cm`, `61–100 cm`, `> 100 cm`.
   * Jika `kategori` = `'Buah-Buahan'` -> Backend hanya meloloskan `tinggi_bibit`: `70–100 cm`, `> 100 cm`.
3. **Penyimpanan Batch Multi-Varian:** API `POST /admin/bibit` menerima array spesifikasi tinggi + harga + stok, lalu menyimpannya dalam 1 *Database Transaction*.
4. **Validasi Stok Public:** REST API untuk Donatur memvalidasi agar `jumlah_bibit` yang di-order **tidak boleh melebihi** `stok_tersedia` di tabel `bibit_spesifikasi`.

---

## 🔄 3. TRIGER OTOMATISASI & INTEGRASI MODUL MONITORING

```text
[ ADMIN ACC VERIFIKASI ] ──► status_bibit: 'Terkumpul' ──► Potong Stok & +Total Terkumpul
                                     │
[ STAFF UPLOAD BAST ]    ──► status_bibit: 'Disalurkan' ──► Dispatch Event ke Monitoring
                                     │
[ MONITORING DONE ]      ──► status_bibit: 'Terealisasi' ─► Auto-Sync +Total Terealisasi
```

### 1️⃣ Fase 1: Admin Verifikasi/ACC Donasi (`status_bibit` -> `'Terkumpul'`)
* Backend menjalankan **DB Transaction (`SELECT FOR UPDATE`)** untuk memotong `stok_tersedia` pada `bibit_spesifikasi` secara aman (*atomic/pessimistic locking*).
* Memicu penambahan counter `total_bibit_terkumpul` pada program terkait.

### 2️⃣ Fase 2: Upload BAST (`status_bibit` -> `'Disalurkan'`)
* Staff PDAS meng-upload file BAST -> Backend menyimpan file ke cloud/server & mengubah `status_bibit` menjadi `'Disalurkan'`.
* **Inter-Module Dispatch (Push to Monitoring):** Backend Donasi secara otomatis menembak API/event ke **Modul Monitoring** untuk membuat draft penugasan penanaman dengan payload wajib:

```json
{
  "id_program_donasi": "PRG-2026-001",
  "id_donasi": "DNS-88219",
  "file_bast_url": "https://storage.pdas.go.id/bast/bast-001.pdf",
  "kth_id": "KTH-012",
  "lokasi_penanaman": "Kec. Lembang, Bandung Barat",
  "detail_bibit": [
    {
      "sku_code": "BBT-001",
      "nama_spesies": "Sengon",
      "tinggi_bibit": "30–60 cm",
      "jumlah_bibit": 100
    }
  ]
}
```

### 3️⃣ Fase 3: Callback Monitoring (`status_bibit` -> `'Terealisasi'`)
* Saat tim lapangan menyelesaikan penanaman via Modul Monitoring -> Modul Monitoring mengirim sinyal *callback* ke Modul Donasi.
* Status otomatis berubah jadi `'Terealisasi'`, dan counter `total_bibit_terealisasi` bertambah di Dashboard Admin & Executive.

---

## 📡 4. PENYESUAIAN ENDPOINT API & PAYLOAD CLEANUP

### A. Modul Admin Staff PDAS
* `GET /api/v1/admin/bibit`:
  * Mendukung query param filter: `?tinggi_bibit=30–60 cm` dan `?kategori=Kehutanan/Hias`.
  * Response menyertakan kolom `tinggi_bibit` di sebelah `nama_spesies`.
* `POST /api/v1/admin/donations/{id}/upload-bast`: Multiparts upload BAST -> Mengubah `status_bibit` ke `'Disalurkan'` -> Pemicu dispatch ke Monitoring.

### B. Modul Executive Kabid PDAS
* `GET /api/v1/kabid/dashboard`:
  * Payload **HANYA** mengembalikan 2 Indikator: `total_donasi_diterima` dan `total_bibit_terealisasi` + Data Grafik Realisasi.
  * Component `laporan_terbaru` dan field `target_bibit` **dihapus/di-exclude dari JSON response**.

### C. Public Website & Donatur
* `GET /api/v1/public/programs/{id}`:
  * Field `target_bibit` **dihapus** dari response.
  * Mengembalikan daftar `bibit_spesifikasi` dengan info `stok_tersedia` *real-time*.

*   **Update Output API Program (`GET /api/v1/public/programs/{id}`):** Memastikan *response* API memuat *array* data varian spesifikasi bibit secara utuh, termasuk mengekspos field `tinggi_bibit`, `harga_satuan`, dan `stok_tersedia` per varian agar dapat dirender oleh Frontend.
*   **Validasi Endpoint Checkout (`POST /api/v1/public/donations`):** 
    *   Memastikan endpoint memproses payload pesanan (*array items*) berdasarkan `bibit_spesifikasi_id`.
    *   Melakukan validasi ketersediaan stok ketat berdasarkan spesifikasi varian tersebut (memastikan jumlah donasi per varian tidak melebihi angka `stok_tersedia` pada tabel `bibit_spesifikasi`).