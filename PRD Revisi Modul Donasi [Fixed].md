# 📋 DOKUMEN REVISI SPESIFIKASI MODUL DONASI

---

## 👨‍💻 1. AKSES STAFF PDAS (Admin Dashboard)

### 📦 Modul Manajemen Bibit (Master Data Bibit)
1. **Harga Bibit Fixed:** Harga diinput per 1 unit bibit (nominal tunggal/pasti). Strictly **TIDAK BOLEH** menggunakan rentang harga (*price range*). ✅
2. **Manajemen Stok Bibit:** Terdapat penginputan data jumlah **Stok Awal Bibit** yang tersedia di sistem per varian. ✅
3. **Pilihan Tinggi Bibit Dinamis (Dinamis Berdasarkan Kategori):** Pemilihan opsi pada dropdown **Tinggi Bibit** bersifat *cascading* (otomatis menyesuaikan dengan **Kategori Bibit** yang dipilih): 
   * **a. Kategori Tanaman Kehutanan dan Hias:** Opsi dropdown tinggi = `30–60 cm`, `61–100 cm`, `> 100 cm`.
   * **b. Kategori Buah-Buahan:** Opsi dropdown tinggi = `70–100 cm`, `> 100 cm`.
4. **Multi-Varian Input (Harga & Stok Manual):** Penginputan form menggunakan tabel dinamis (*dynamic repeater*), di mana Staff menginput **Harga Per Batang** dan **Stok Awal** secara manual per varian ukuran tinggi bibit yang dipilih dalam 1 kali simpan. ✅
. **Struktur & Fitur Tabel Master Bibit:**
   * **Penambahan Kolom Baru:** Terdapat kolom **`Tinggi Bibit`** yang disisipkan tepat di sebelah kanan kolom `Nama Spesies / Bibit`. 
   * **Fitur Filter Tinggi Bibit:** Terdapat dropdown **Filter Tinggi** pada toolbar atas tabel yang menyesuaikan dengan daftar rentang tinggi yang berlaku (`30–60 cm`, `61–100 cm`, `70–100 cm`, `> 100 cm`) untuk mempermudah pencarian. 
5.  * **Kode Varian Unik (SKU):** Setiap varian ukuran tinggi memiliki **Kode Bibit unik** tersendiri (misal: `BBT-001`, `BBT-002`) agar riwayat stok dan transaksi antar-varian tidak tertukar di database. ✅ 

### 📝 Modul Pengelolaan Program
1. **Periode Program:** Saat input program, terdapat field **`Tanggal Mulai`** dan **`Tanggal Selesai`** (berlaku siklus periode per minggu). ✅ 
2. **Auto-Fill KTH:** Field **KTH** terisi otomatis menarik data dari modul **CPI** berdasarkan lokasi yang dipilih (tidak diketik manual). ✅
3. **Pembersihan Field:** Field **Target Bibit dihapus** dari form penginputan maupun detail program. ✅
4. **Status Program Baru:** Opsi status program ditambahkan pilihan status **`Ditolak`** (jika ada revisi/penolakan dari Kabid). ✅

### 🚚 Modul Pelaksanaan
1. **Pemisahan Kolom Tabel:** Tampilan **Nama Donatur** dan **Program** dipisahkan ke dalam kolom tersendiri pada tabel data. ✅

---

## 👔 2. AKSES KABID PDAS (Executive Dashboard)

### 📊 Dashboard Eksekutif
1. **Indikator Ringkas (Cards):** Dashboard cukup menampilkan **2 Indikator Utama**: ✅
   * **Total Donasi Diterima** ✅
   * **Total Bibit Terealisasi** ✅
   * *Catatan Grafik:* Grafik khusus diset **hanya menampilkan data Bibit Terealisasi**. ✅
2. **Pembersihan Widget:** Bagian/widget **"Laporan Terbaru" dihapus** total dari halaman Dashboard. ✅

### 📈 Modul Program & Pelaporan
1. **Metrik Realisasi:** Menampilkan indikator **Bibit Terealisasi** (disesuaikan dengan tampilan pada Admin Staff). ✅
2. **Pembersihan Detail:** Komponen **Target Bibit dihapus** dari tampilan detail program/laporan. ✅

---

## 🌐 3. AKSES DONATUR & WEBSITE PUBLIC

1. **Sinkronisasi Stok Real-Time:** Pilihan bibit yang dapat dipilih oleh Donatur **otomatis menyesuaikan dengan ketersediaan Stok Bibit** yang ada di sistem. ✅
2. **Penyesuaian Spesifikasi Tinggi Bibit:** Menambahkan informasi/keterangan tinggi bibit (dari field `tinggi_bibit`) pada setiap card varian bibit agar donatur mengetahui spesifikasi pasti dari bibit yang dipilih. 
    *   *Catatan UI:* Dapat digabung dengan nama bibit (contoh: `Sengon (30–60 cm)`) atau diletakkan pada baris keterangan (contoh: `Tinggi: 30-60 cm`).
3. **Update "Ringkasan Transaksi":** Menambahkan keterangan tinggi bibit pada *list* struk ringkasan transaksi.
    *   *Format UI:* Mengubah `222x Bibit Sengon` menjadi `222x Bibit Sengon (30–60 cm)`.
4. **Penyesuaian Payload Checkout (State Management):** Memastikan mekanisme penambahan jumlah bibit (tombol `+` / `-`) dan pengiriman data ke API *Checkout* di-mapping menggunakan **ID Varian (`bibit_spesifikasi_id`)**, bukan menggunakan ID Master Bibit.
5. **Pembersihan Tampilan UI Public:** Komponen **Target Bibit dihilangkan** dari tampilan *Progress Bar* maupun *Card Program*. ✅
6. **Restriksi Input Jumlah Bibit:** Field **Jumlah Bibit** belum/tidak bisa diketik manual via keyboard (harus menggunakan tombol counter `+` / `-`). ✅