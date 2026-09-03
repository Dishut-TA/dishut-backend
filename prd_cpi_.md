# Product Requirements Document (PRD)
**Nama Produk:** Sistem Analisis Lahan Kritis Berbasis AHP dan CPI  
**Fokus Modul:** Modul Analisis CPI (Conservation Performance Index)  
**Versi Dokumen:** 1.0  
**Tanggal:** 24 Juli 2026  
**Status:** Draft  

---

## 1. Latar Belakang & Ringkasan Eksekutif (Executive Summary)
Proses penentuan lahan kritis saat ini masih membutuhkan waktu yang lama dalam pemrosesan data spasial dan pembuatan laporan rehabilitasi. Modul Analisis CPI dibangun untuk mengotomatisasi pemrosesan data spasial dalam menentukan tingkat kekritisan lahan. Sistem ini menggunakan metode *Analytical Hierarchy Process* (AHP) untuk pembobotan indikator dan *Conservation Performance Index* (CPI) untuk penilaian akhir. Hasilnya divisualisasikan dalam bentuk peta interaktif dan tabel data tabular yang terintegrasi dengan data zonasi dan Kelompok Tani Hutan (KTH).

## 2. Tujuan & Metrik Keberhasilan (Objectives & OKRs)
**Tujuan (Objectives):**
1. Mengotomatisasi dan mempercepat proses perhitungan lahan kritis berdasarkan 5 parameter spasial.
2. Menyediakan visualisasi peta tingkat kekritisan lahan yang akurat dan terintegrasi dengan batas administratif (zonasi) serta kepemilikan (KTH).
3. Menghasilkan draf dokumen Rencana Umum Rehabilitasi Hutan dan Lahan (RURHL) secara otomatis untuk mempermudah pengambilan keputusan.

**Metrik Keberhasilan (Success Metrics / KPIs):**
* **Time-to-Analyze:** Berkurangnya waktu pemrosesan data peta dari manual (berhari-hari) menjadi di bawah 15 menit per batch data.
* **Accuracy:** 100% kesesuaian hasil perhitungan matematis AHP & CPI dengan matriks standar rumus.
* **User Adoption:** Digunakan secara aktif oleh Petugas PDAS dan Kepala Bidang untuk setiap periode pelaporan.

## 3. Target Pengguna (User Personas)
| Peran (Role) | Tanggung Jawab Utama di Modul Ini |
| :--- | :--- |
| **Petugas PDAS** | Mengunggah data indikator spasial, mengelola data master referensi, menjalankan proses analisis (preprocessing, AHP, CPI), dan meninjau hasil awal. |
| **Kepala Bidang PDAS** | Memverifikasi hasil (berdasarkan validasi lapangan), menentukan kelayakan prioritas wilayah, dan mengunduh laporan akhir resmi (RURHL). |

## 4. Ruang Lingkup (Scope)
**In-Scope (Masuk Cakupan Modul Ini):**
* Pengelolaan Master Data Zonasi dan Master Data KTH.
* Fitur *upload*, *clipping*, dan *preprocessing* data raster/spasial.
* Kalkulasi algoritma AHP dan CPI secara otomatis di *backend*.
* Visualisasi peta dengan *color grading* kekritisan lahan.
* Fitur *Rule-Based Recommendation* untuk intervensi lahan.
* Verifikasi kelayakan lahan prioritas oleh Kabid PDAS.
* *Generate* laporan RURHL dalam format PDF.

**Out-of-Scope (Di Luar Cakupan Modul Ini):**
* Aplikasi *mobile* atau modul khusus untuk pengumpulan data validasi langsung di lapangan. (Diasumsikan ini menggunakan modul/sistem lain, dan modul CPI hanya menerima hasil akhirnya).
* Pemrosesan citra satelit mentah (data yang diunggah harus sudah berupa indikator layer yang siap pakai/ekstrak).

## 5. Asumsi & Ketergantungan (Assumptions & Dependencies)
* **Ketergantungan Data:** Keakuratan hasil sangat bergantung pada kualitas file spasial (resolusi, kelengkapan) yang diunggah oleh Petugas.
* **Infrastruktur Server:** Membutuhkan *resource server* (RAM & CPU) yang cukup tinggi saat proses *geoprocessing* (preprocessing & overlay layer peta) agar tidak *timeout*.
* **Format File:** Data yang diunggah dipastikan mengikuti standar format yang ditentukan (misal: `.tif` untuk raster, zip yg berisi setidaknya(`.shp`, `.shx`, `.dbf`, dan `.prj`) atau `.geojson` untuk vektor).

---

## 6. Kebutuhan Fungsional & Alur Pengguna (User Flow)

### 6.1. Tahap Input & Pra-Pemrosesan (Role: Petugas PDAS)
* **Story:** Sebagai Petugas PDAS, saya ingin dapat mengunggah file spasial dan data referensi sehingga sistem dapat memprosesnya.
* **Acceptance Criteria (AC):**
  * Sistem menyediakan antarmuka form untuk input Data Zonasi manual di web atau up file (zip yg berisi setidaknya `.shp`, `.shx`, `.dbf`, dan `.prj`/tif) yang memiliki atribut(kode_provinsi,nama_provinsi, kode_kab_kota, nama_kab_kota ,kode_kecamatan ,nama_kecamatan ,kode_desa_kelurahan,nama_desa_kelurahan ,jenis_administrasi, luas_ha, geometry(longitude langitude)) dan Data KTH(xls) yang memiliki atribut(Cabang Dinas Kehutanan; CDK I - CDK IX), Kab/Kota, Kecamatan, Desa/Kelurahan, Nama Kelompok, Ketua Kelompok, Jenis Usaha, Aksi View Edit Delete .
  * Sistem memiliki form *upload* khusus untuk 5 layer indikator utama.
  * Sistem melakukan *auto-clipping* menggunakan batas DAS saat tombol *submit* ditekan.
  * Sistem menolak file dengan ekstensi atau proyeksi koordinat yang tidak didukung dan memunculkan *error message*.

### 6.2. Tahap Analisis AHP & CPI (Role: Sistem)
* **Story:** Sebagai Sistem, saya harus menghitung bobot dan skor setiap piksel/area.
* **Acceptance Criteria (AC):**
  * Modul otomatis menjalankan algoritma AHP untuk menentukan bobot: Tutupan Lahan, Curah Hujan, Jenis Tanah, Kemiringan Lereng (dari DEM).
  * Menghitung nilai CPI per poligon/wilayah berdasarkan skor indikator × bobot AHP.

### 6.3. Tahap Pemetaan & Klasifikasi (Role: Sistem & Petugas)
* **Story:** Sebagai Petugas PDAS, saya ingin melihat hasil perhitungan dalam bentuk peta dan tabel agar mudah dipahami.
* **Acceptance Criteria (AC):**
  * Peta menampilkan area dengan dua klasifikasi warna utama: **Oranye** (Kritis) dan **Merah** (Sangat Kritis).
  * Sistem melakukan *spatial join* antara hasil CPI dengan atribut Master Zonasi dan Master KTH.
  * Tabel data interaktif muncul menampilkan Provinsi Kota/Kabupaten Kecamatan Desa/Kelurahan CDK (cabang dinas kehutanan) CDK I - CDK IX, Nama kelompok kth, Nama ketua kelompok, Status lahan (kritis/samgat kritis), Skor CPI, Rekomendasi intervensi, status validasi(sudah/belum), "Tombol aksi lihat detail (role kepala bidang) tombol detail ini berisi dua tabel yaitu seluruh data lokasi yg ada di halaman analisis cpi dan data daftar hasil validasi penyuluh dari modul monitoring dan pelaksanaan dengan atribut; no, nama lokasi, sumber lokasi (analisis cpi/proposal csr), nama penyuluh, tanggal dibuat, status(Ditolak/Diterima), aksi detail(kondisi lahan, kondisi vegetasi, kendala lapangam, titik koordinat GPS, Foto lokasi, catatan hasil peninjauan), verifikasi (tolak/terima)."
  * Sistem secara otomatis mengisi kolom "Rekomendasi Intervensi" berdasarkan *ruleset* baku (misal: jika kemiringan > 40% dan kritis = "Terasering & Agroforestri").

### 6.4. Tahap Verifikasi & Pelaporan (Role: Kepala Bidang PDAS)
* **Story:** Sebagai Kabid PDAS, saya ingin menyetujui hasil perhitungan lokasi awal dan mengunduh laporan akhir untuk diteruskan ke Dinas.
* **Acceptance Criteria (AC):**
  * Kabid memiliki tombol "Validasi" pada setiap baris data daftar hasil validasi penyuluh
  * Data yang divalidasi ("Terima" / "Tolak") akan mengubah statusnya di *database* yang tadinya belom ada status sama sekali menjadi ada statu kelayakan; Terima == status lahan layak, Tolak == status lahan tidak layak.
  * Data peta akan terupdate dan menambahkan atribut status Layak/Tidak Layak pada Desc peta saat hover dan juga pada tabel di menu analisis CPI, yang mana nantinya lahan lahan layak ini akan menjadi dasar dari lokasi lokasi rehabilitasi di modul lain.
  * Tersedia tombol "Generate Laporan (RURHL)" yang menghasilkan PDF sesuai format pada lokasi yg sudah di validasi

---

## 7. Kebutuhan Data (Data Requirements)
**A. Data Master Indikator (Layer Spasial)**
1. Tutupan lahan
2. Curah hujan
3. Jenis tanah
4. DEM (*Digital Elevation Model*) – untuk diekstrak menjadi kemiringan lereng.
5. Data batas wilayah DAS.

**B. Data Master Referensi (Tabular/Vektor)**
1. **Data Zonasi:** Provinsi, Kabupaten/Kota, Kecamatan, Desa, Koordinat polygon.
2. **Data KTH:** Nama kelompok, Nama Ketua, area wilayah kelola.

---

## 8. Struktur Antarmuka (UI/UX)
Sistem memiliki menu utama berikut (setelah *login*):
1. **Dashboard Analisis:** Menampilkan *card* (Total luas analisis, Total lahan kritis, total sangat kritis, total prioritas,total luas prioritas, periode analisis), dan Peta Prioritas *overview*. Dilengkapi *Filter* periode/tahun.
2. **Analisis CPI:** Ruang kerja utama. Terdiri dari *Map Viewer* di bagian atas, dan *Data Table* di bagian bawah. Terdapat tombol *Upload Layer* dan *Run Analysis*. dan juga *verifikasi* dan *preview* untuk role kepala pdas
3. **Master Referensi:** 
   * *Sub-menu Data Zonasi:* Tabel CRUD untuk wilayah administrasi.
   * *Sub-menu Data KTH:* Tabel CRUD untuk data entitas kelompok tani.

---

## 9. Kebutuhan Non-Fungsional (Non-Functional Requirements)
* **Performance:** *Geoprocessing* untuk file di bawah 500MB harus selesai dalam waktu kurang dari 10 menit. Harus menggunakan *background job / queueing system* (seperti Celery/Redis) agar tidak memblokir antarmuka pengguna.
* **Security & Akses:** Akses fitur "Verifikasi" dan "Unduh RURHL Resmi" dibatasi secara ketat hanya untuk *Role* Kabid PDAS (*Role-Based Access Control*).
* **Usability:** Peta harus interaktif (bisa di-*zoom*, *pan*, *click-to-identify*) menggunakan *library* yang responsif (misal: Leaflet.js atau Mapbox).

---

## 10. Spesifikasi Laporan RURHL (Output Dokumen PDF)
Laporan akhir yang diunduh harus mencakup elemen berikut:
1. **Cover & Informasi Umum:** Nomor dokumen, periode analisis, wilayah kerja, metodologi (AHP & CPI).
2. **Ringkasan Eksekutif (Summary):** Total lahan, jumlah lokasi prioritas, dan metrik kekritisan.
3. **Peta Visual:** Peta Prioritas Rehabilitasi (RURHL) dengan resolusi cetak yang tinggi disertai Legenda dan Skala.
4. **Tabel Lokasi Prioritas:** Menampilkan detail administratif (Prov, Kab, Kec, Desa, CDK), Luas Lahan, Nilai CPI, Kategori Kritis, dan Rekomendasi Intervensi.
5. **Metadata & Pengesahan:** Versi dataset yang digunakan, daftar bobot AHP, serta kolom Tanda Tangan elektronik / nama lengkap & NIP Kepala Bidang PDAS.
