# PRODUCT REQUIREMENT DOCUMENT (PRD)

**Nama Aplikasi:** SIGAP JABAR (Sistem Informasi Gerakan Rehabilitasi Lahan Jawa Barat)  
**Modul:** Modul 2 — Realisasi Bibit dan Donasi  
**Status:** FINAL (Approved for Implementation & Database Migration)  
**Target Pengguna:** Tim Pengembang (Frontend, Backend, Database Administrator) & Stakeholders  

---

## 1. PENDAHULUAN
Modul 2 (Realisasi Bibit dan Donasi) pada platform SIGAP JABAR merupakan ujung tombak dalam memfasilitasi partisipasi masyarakat maupun korporasi (CSR) untuk berkontribusi dalam aksi nyata rehabilitasi lahan kritis di Jawa Barat. Modul ini menjembatani hasil analisis lahan kritis, kesiapan kelompok tani hutan (KTH), inventarisasi stok pembibitan, transaksi pendanaan transparan, hingga pembuktian penanaman pohon secara real-time di lapangan.

Dokumen PRD ini dirancang untuk memastikan seluruh fungsionalitas, alur kerja sistem (*system workflows*), dan skema database relasional (11 entitas lengkap) terintegrasi tanpa celah, menghindari redundansi yang merusak logika, serta mengoptimalkan performa transaksi yang aman dan dinamis.

---

## 2. AKTOR DAN PERAN SISTEM (USER PERSONAS)
*   **Donatur:** Masyarakat umum, instansi, atau korporasi yang ingin menyalurkan dana bantuan berupa pembelian bibit pohon. Donatur dapat berdonasi secara terdaftar (menggunakan akun) atau secara anonim (Hamba Allah). Donatur berhak memantau progres logistik bibit, perkembangan fisik penanaman, serta berhak mengunduh sertifikat digital sebagai bukti kontribusi.
*   **Staff PDAS:** Operator internal Dinas Kehutanan Jawa Barat yang bertugas penuh dalam mengelola master data bibit & harga (`BIBIT` & `BIBIT_SPESIFIKASI`), mengajukan kampanye program donasi baru (`PROGRAM_DONASI`), melakukan verifikasi manual terhadap transaksi donatur, mengunggah tanda terima serah terima fisik bibit, serta mengesahkan hasil laporan realisasi penanaman pohon di lapangan.
*   **Kepala Bidang (Kabid) PDAS:** Pimpinan struktural Dinas Kehutanan yang memegang otoritas penuh untuk meninjau dan memberikan keputusan (Persetujuan / Penolakan) terhadap program donasi baru yang diajukan Staff. Kabid juga bertugas memantau efektivitas modul melalui menu Pelaporan Dampak dan mengekspor rekapitulasi data menjadi dokumen fisik/arsip transparansi.
*   **Sistem (Backend & DB Automated Agent):** Komponen otomatisasi platform yang melakukan validasi limit stok bibit saat donatur melakukan order, menghitung nominal tagihan transfer (Jumlah Bibit x Harga Spesifikasi), memperbarui counter akumulasi donasi program, melakukan *auto-decrement* (pemotongan stok) di master data saat transaksi sukses, dan meng-*generate* file sertifikat donatur secara otomatis.

---

## 3. FITUR UTAMA & ATURAN BISNIS

### 3.1 Manajemen Master Data Bibit & Spesifikasi Harga
Fitur krusial bagi Staff PDAS untuk mengelola aset pembibitan di persemaian. Berbeda dengan katalog sederhana, satu jenis spesies bibit (misalnya Akasia Mangium) dapat dipecah menjadi beberapa spesifikasi tinggi (misalnya 30-60 cm, 61-100 cm, atau >100 cm). Setiap spesifikasi memiliki batas rentang tinggi, harga per batang yang berbeda, dan pelacakan jumlah stok fisik riil yang independen. Fitur ini mencegah pembuatan program donasi fiktif yang tidak didukung oleh pasokan bibit nyata.

### 3.2 Pengelolaan Program Kontribusi Bibit
Modul untuk meluncurkan program donasi ke masyarakat luas. Pembuatan program ini didasarkan pada data rekomendasi lahan kritis (`HASIL_ANALISIS`) serta bermitra dengan Kelompok Tani Hutan (KTH) lokal selaku pihak yang akan melakukan penanaman. Ketika program diajukan oleh Staff, statusnya akan masuk dalam antrean validasi Kabid PDAS. Setelah Kabid menyetujui, program akan tampil sebagai program 'Aktif' di halaman depan.

### 3.3 Checkout & Pembayaran Donasi Bibit
Sistem kasir digital di mana donatur dapat menyumbang dengan nominal dinamis berdasarkan jumlah bibit yang dibeli. Total tagihan dihitung secara otomatis oleh backend menggunakan rumus: 

Nominal = Jumlah Bibit x Harga Satuan Spesifikasi Bibit

Donatur juga dapat memilih mode identitas 'Anonim' untuk menyembunyikan identitasnya di publik. Pembayaran divalidasi secara manual atau melalui integrasi, didukung dengan pengunggahan foto bukti transaksi.

### 3.4 Pelaksanaan Realisasi & Penanaman Lapangan (Modul Monitoring)
Setelah donasi program terpenuhi, program berstatus 'Terkumpul'. Staff PDAS melakukan pengiriman bibit ke KTH penerima manfaat dan mengunggah berita acara/berkas 'Tanda Terima Bibit'. Status donasi berubah menjadi 'Disalurkan' dan sistem mengirimkan penugasan ke Modul Pelaksanaan & Monitoring. Setelah KTH melakukan penanaman, Staff mengesahkan hasil lapangan (mencatat jumlah berhasil tanam dan mengunggah foto penanaman), kemudian mengubah status program menjadi 'Terealisasi'.

### 3.5 Sistem Pelaporan Dampak & Transparansi Program
Fitur audit internal untuk Kabid PDAS. Sistem dapat menyaring dan merangkum seluruh jalannya program donasi (jumlah donatur, total bibit terkumpul, status penyerahan fisik, hingga total bibit yang berhasil tertanam di lahan kritis). Kabid dapat mengekspor laporan transparan ini ke format PDF/Excel sebagai arsip transparansi program.

---

## 4. ALUR KERJA SISTEM (SYSTEM WORKFLOWS)

### A. Alur Registrasi & Login Pengguna
1. Pengguna mengakses platform SIGAP JABAR.
2. Pengguna memilih menu Registrasi, mengisi form (username, email, password).
3. Sistem memvalidasi input. Jika valid, sistem mengirimkan email verifikasi / OTP.
4. Pengguna memverifikasi akun melalui OTP.
5. Akun aktif. Pengguna melakukan login.
6. Sistem memverifikasi kredensial dan hak akses (*role*) di database, lalu mengarahkan ke dashboard yang sesuai (Donatur, Staff PDAS, atau Kabid PDAS).

### B. Alur Manajemen Data Master Bibit
1. Staff PDAS membuka menu "Realisasi Bibit dan Donasi" -> "Master Data Bibit".
2. Staff menekan tombol "Tambah Bibit" untuk membuat jenis spesies baru.
3. Staff mengisi form data dasar bibit (Nama spesies, kategori dropdown, status sertifikasi).
4. Staff memasukkan spesifikasi berlapis: tinggi minimum, tinggi maksimum, harga per batang, dan jumlah stok awal di gudang.
5. Sistem memvalidasi data (nilai numerik harus positif) dan menyimpannya ke tabel `BIBIT` dan `BIBIT_SPESIFIKASI`.

### C. Alur Pengelolaan Program Kontribusi Bibit
1. Staff PDAS masuk ke menu "Kelola Program Kontribusi Bibit" -> "Buat Program".
2. Staff memilih rekomendasi lokasi yang bersumber dari tabel `HASIL_ANALISIS`.
3. Staff menentukan Kelompok Tani Hutan (KTH) pelaksana lapangan.
4. Staff memilih jenis `BIBIT_SPESIFIKASI` yang akan ditanam (Harga & stok terikat otomatis dari master data).
5. Staff menginput nama program, target jumlah bibit, periode donasi, deskripsi program -> klik "Ajukan Program".
6. Sistem memvalidasi kelayakan, lalu mengubah status program menjadi "Menunggu Verifikasi Kabid PDAS".
7. Kabid PDAS meninjau pengajuan di dashboard pimpinan.
8. Jika Kabid menolak, Kabid wajib memasukkan alasan penolakan (Status program menjadi "Ditolak").
9. Jika Kabid menyetujui, status program berubah menjadi "Aktif" dan otomatis terpublikasi ke masyarakat umum.

### D. Alur Pembukaan Donasi Bibit (Transaksi & Verifikasi)
1. Donatur memilih program aktif di katalog platform.
2. Donatur memilih tipe identitas donasi: menggunakan akun donatur terdaftar atau berdonasi sebagai "Anonim".
3. Donatur menginput jumlah bibit yang ingin dikontribusikan.
4. Sistem membandingkan jumlah bibit yang diinput dengan sisa kebutuhan program serta sisa ketersediaan stok fisik di gudang pembibitan.
5. Jika jumlah bibit yang diinput melebihi stok atau target kebutuhan, sistem menampilkan pesan error.
6. Jika sesuai, sistem menghitung total nominal pembayaran secara otomatis: Jumlah Bibit x Harga Spesifikasi Bibit.
7. Donatur melakukan transfer dana dan mengunggah berkas foto "Bukti Transaksi", lalu mengklik "Ajukan Donasi".
8. Sistem membuat baris data di tabel `DONASI` dan `TRANSAKSI` dengan status awal "Menunggu Verifikasi".
9. Staff PDAS meninjau pengajuan donasi dan memvalidasi keabsahan bukti transfer.
10. Jika bukti pembayaran tidak valid, Staff mengubah status menjadi "Dibatalkan".
11. Jika bukti pembayaran valid, Staff menyetujui donasi. Status donasi berubah menjadi "Terkumpul", status transaksi menjadi "Success", sistem otomatis melakukan pemotongan angka stok di tabel `BIBIT_SPESIFIKASI`, memperbarui `total_bibit_terkumpul` di program, dan meng-*generate* sertifikat kontribusi digital untuk donatur.

### E. Alur Pelaksanaan Realisasi & Penanaman
1. Saat target bibit program terpenuhi, Staff PDAS memeriksa kesiapan penyaluran bibit fisik dari persemaian.
2. Staff mengonfirmasi pengiriman bibit ke lokasi KTH dan mengunggah dokumen berita acara "Tanda Terima Bibit".
3. Sistem mengubah status donasi terkait menjadi "Disalurkan" (Donatur mendapatkan notifikasi bahwa bibit mereka sudah di lapangan).
4. Sistem mengirimkan permintaan pelaksanaan kerja ke Modul Pelaksanaan dan Monitoring, status program berubah menjadi "Dalam Penanaman".
5. Kelompok Tani Hutan (KTH) menanam bibit di area target.
6. Staff PDAS meninjau progres dan hasil akhir penanaman di lapangan.
7. Setelah penanaman 100% selesai, Staff PDAS menginput realisasi tanam (jumlah bibit ditanam dan foto dokumentasi), lalu mengklik "Mengesahkan Data Hasil Penanaman".
8. Sistem menyimpan data ke tabel `BIBIT_DITANAM`, mengubah status program menjadi "Terealisasi", dan mengirim notifikasi dampak kepada Donatur.

### F. Alur Pelaporan Dampak & Transparansi Program
1. Kabid PDAS membuka menu "Pelaporan Dampak dan Transparansi Program".
2. Kabid memfilter program donasi berdasarkan nama program dan batas periode tanggal laporan.
3. Sistem merangkum data realisasi penanaman (identitas program, KTH pelaksana, total donasi, jumlah bibit disalurkan, jumlah berhasil ditanam, status akhir).
4. Kabid meninjau rekap data. Jika sesuai, Kabid menekan tombol "Export Laporan".
5. Sistem memproses kompilasi dokumen PDF/Excel laporan, menyimpannya di tabel `LAPORAN` sebagai arsip transparansi, lalu menampilkan link unduhan fisik untuk diunduh Kabid.

---

## 5. STRUKTUR DATA (DATABASE SCHEMA) — 11 ENTITAS LENGKAP
Berikut adalah spesifikasi lengkap 11 tabel database relational sesuai dengan cetak biru diagram ERD [FIX] SIGAP JABAR. Struktur ini dirancang dengan pengindeksan kunci primer (PK) dan kunci tamu (FK) yang tepat untuk memastikan integritas data terjamin dan bebas dari *circular dependency*.

### 1. Tabel: `USERS` (Penyimpanan akun pengguna platform)
| Nama Kolom | Tipe Data | Keterangan / Integritas Relasi |
| :--- | :--- | :--- |
| **id** | INT / UUID (PK) | Primary Key unik untuk identifikasi pengguna. |
| **username** | VARCHAR(50) | Nama pengguna unik untuk proses login. |
| **email** | VARCHAR(100) | Email pengguna untuk pengiriman OTP / bukti verifikasi. |
| **password** | VARCHAR(255) | Hash password pengaman akun. |
| **role** | ENUM('Donatur', 'Staff PDAS', 'Kabid PDAS') | Hak akses pengguna dalam sistem. |

### 2. Tabel: `DONATUR` (Detail profil donatur terdaftar)
| Nama Kolom | Tipe Data | Keterangan / Integritas Relasi |
| :--- | :--- | :--- |
| **id** | INT / UUID (PK) | Primary Key unik profil donatur. |
| *id_user* | INT / UUID (FK) | Foreign Key merujuk ke `USERS.id` untuk relasi login. |
| **nama_donatur** | VARCHAR(100) | Nama lengkap donatur untuk pencetakan sertifikat. |
| **alamat** | TEXT | Alamat lengkap instansi/individu donatur. |

### 3. Tabel: `BIBIT` (Induk katalog jenis spesies)
| Nama Kolom | Tipe Data | Keterangan / Integritas Relasi |
| :--- | :--- | :--- |
| **id** | INT / UUID (PK) | Primary Key unik jenis bibit. |
| **jenis_bibit** | VARCHAR(100) | Nama spesies/tanaman (misal: Durian, Akasia Mangium). |

### 4. Tabel: `BIBIT_SPESIFIKASI` (Master harga & stok per ukuran)
| Nama Kolom | Tipe Data | Keterangan / Integritas Relasi |
| :--- | :--- | :--- |
| **id** | INT / UUID (PK) | Primary Key unik spesifikasi bibit. |
| *id_bibit* | INT / UUID (FK) | Foreign Key merujuk ke `BIBIT.id`. |
| **tinggi_min** | INT | Tinggi minimum batang bibit (dalam centimeter). |
| **tinggi_maks** | INT | Tinggi maksimum batang bibit (nullable jika tidak terhingga). |
| **stok_bibit** | INT | Jumlah inventaris stok fisik riil di persemaian. |
| **harga_bibit** | DECIMAL(15,2) | Harga per batang untuk kalkulasi donasi dinamis. |

### 5. Tabel: `KTH` (Data Kelompok Tani Hutan mitra pelaksana)
| Nama Kolom | Tipe Data | Keterangan / Integritas Relasi |
| :--- | :--- | :--- |
| **id** | INT / UUID (PK) | Primary Key unik kelompok tani. |
| **nama** | VARCHAR(150) | Nama resmi Kelompok Tani Hutan. |
| **koordinator** | VARCHAR(100) | Nama ketua/penanggung jawab lapangan KTH. |
| **jumlah_anggota** | INT | Jumlah petani aktif dalam kelompok. |
| **cdk** | VARCHAR(100) | Wilayah kerja Cabang Dinas Kehutanan (CDK) terkait. |

### 6. Tabel: `HASIL_ANALISIS` (Data rekomendasi lahan kritis)
| Nama Kolom | Tipe Data | Keterangan / Integritas Relasi |
| :--- | :--- | :--- |
| **id** | INT / UUID (PK) | Primary Key analisis lokasi penanaman. |
| *id_wilayah* | INT / UUID (FK) | Foreign Key ke pemetaan batas spasial wilayah. |
| *id_analisis_cpi* | INT / UUID (FK) | Foreign Key ke pengindeksan data CPI. |
| *id_rekomendasi_intervensi* | INT / UUID (FK) | Foreign Key ke hasil rekomendasi aksi rehabilitasi. |
| **status** | VARCHAR(50) | Status kelayakan wilayah untuk penanaman. |
| **skor_cpi** | DECIMAL(5,2) | Nilai indeks prioritas rehabilitasi. |

### 7. Tabel: `PROGRAM_DONASI` (Kategori program penggalangan)
| Nama Kolom | Tipe Data | Keterangan / Integritas Relasi |
| :--- | :--- | :--- |
| **id** | INT / UUID (PK) | Primary Key unik program donasi. |
| *id_hasil_analisis* | INT / UUID (FK) | Foreign Key ke `HASIL_ANALISIS.id` sebagai rujukan lokasi kritis. |
| *id_kth* | INT / UUID (FK) | Foreign Key ke `KTH.id` sebagai pelaksana penanaman. |
| *id_bibit_spesifikasi* | INT / UUID (FK) | Foreign Key ke `BIBIT_SPESIFIKASI.id` untuk detail tinggi & harga. |
| **nama_program** | VARCHAR(150) | Judul program donasi yang dipublikasikan. |
| **lokasi** | VARCHAR(150) | Detil administrasi lokasi (Desa/Kecamatan/Kabupaten). |
| **total_bibit_terkumpul** | INT | Akumulasi jumlah bibit yang sukses didonasikan. |
| **total_bibit_terealisasi** | INT | Akumulasi jumlah bibit yang telah sukses ditanam. |
| **tanggal_diubah** | TIMESTAMP | Log waktu pembaruan data program. |
| **tanggal_dibuat** | TIMESTAMP | Log waktu pembuatan awal program. |
| **status_program** | VARCHAR(50) | Status program: `Menunggu Verifikasi Kabid`, `Aktif`, `Ditolak`, `Dalam Penanaman`, `Terealisasi`. |

### 8. Tabel: `DONASI` (Data komitmen pembelian bibit donatur)
| Nama Kolom | Tipe Data | Keterangan / Integritas Relasi |
| :--- | :--- | :--- |
| **id** | INT / UUID (PK) | Primary Key unik transaksi komitmen donasi. |
| *id_program_donasi* | INT / UUID (FK) | Foreign Key ke `PROGRAM_DONASI.id`. |
| *id_donatur* | INT / UUID (FK) | Foreign Key ke `DONATUR.id` (nullable jika anonim). |
| *id_bibit* | INT / UUID (FK) | FK rujukan langsung ke `BIBIT.id` (Shortcut query). |
| **jumlah_bibit** | INT | Jumlah bibit yang dikomitmenkan untuk dibeli. |
| **status_bibit** | VARCHAR(50) | Status logistik bibit: `Menunggu Verifikasi`, `Terkumpul`, `Disalurkan`. |
| **tanda_terima_bibit** | VARCHAR(255) | Path file bukti berkas serah terima bibit fisik. |
| **sertifikat** | VARCHAR(255) | Path file sertifikat kontribusi digital. |

### 9. Tabel: `TRANSAKSI` (Data tagihan / invoice keuangan)
| Nama Kolom | Tipe Data | Keterangan / Integritas Relasi |
| :--- | :--- | :--- |
| **id** | INT / UUID (PK) | Primary Key unik invoice keuangan. |
| *id_donasi* | INT / UUID (FK) | Foreign Key ke `DONASI.id` (**Pemutus circular dependency**). |
| *id_donatur* | INT / UUID (FK) | Foreign Key ke `DONATUR.id` (nullable jika anonim). |
| **nominal** | DECIMAL(15,2) | Total nominal pembayaran (Jumlah Bibit x Harga Spesifikasi). |
| **tanggal_transaksi** | TIMESTAMP | Waktu donatur melakukan checkout. |
| **bukti_transaksi** | VARCHAR(255) | Path file gambar bukti transfer donatur. |
| **metode_transaksi** | VARCHAR(50) | Nama bank/rekening/metode pembayaran. |
| **status_transaksi** | VARCHAR(50) | Status validasi uang: `Pending`, `Success`, `Rejected`. |

### 10. Tabel: `BIBIT_DITANAM` (Data realisasi fisik penanaman)
| Nama Kolom | Tipe Data | Keterangan / Integritas Relasi |
| :--- | :--- | :--- |
| **id** | INT / UUID (PK) | Primary Key data realisasi penanaman. |
| *id_program_donasi* | INT / UUID (FK) | Foreign Key rujukan ke `PROGRAM_DONASI.id`. |
| *id_bibit* | INT / UUID (FK) | Foreign Key rujukan ke `BIBIT.id`. |
| **jumlah_bibit_ditanam** | INT | Jumlah bibit riil yang berhasil ditanam oleh KTH. |
| **bukti_penanaman** | VARCHAR(255) | Path file foto dokumentasi realisasi di lahan. |

### 11. Tabel: `LAPORAN` (Arsip pelaporan transparansi Kabid)
| Nama Kolom | Tipe Data | Keterangan / Integritas Relasi |
| :--- | :--- | :--- |
| **id** | INT / UUID (PK) | Primary Key arsip laporan. |
| *id_program_donasi* | INT / UUID (FK) | Foreign Key ke `PROGRAM_DONASI.id`. |
| *id_users* | INT / UUID (FK) | Foreign Key ke `USERS.id` (aktor pengeksport, Kabid). |
| **tanggal_awal** | DATE | Filter batas awal tanggal rekapitulasi data. |
| **tanggal_akhir** | DATE | Filter batas akhir tanggal rekapitulasi data. |
| **status** | VARCHAR(50) | Status proses pembuatan dokumen: `Success`, `Failed`. |
| **file_laporan** | VARCHAR(255) | Path file dokumen PDF/Excel yang diunduh. |

---

## 6. KRITERIA PENERIMAAN (ACCEPTANCE CRITERIA)

### Fungsionalitas Master Bibit
1. Sistem harus menolak input jika Staff memasukkan nilai negatif pada kolom tinggi, harga, atau stok.
2. Sistem harus mengizinkan satu jenis `BIBIT` memiliki lebih dari satu spesifikasi tinggi di tabel `BIBIT_SPESIFIKASI`.

### Fungsionalitas Program Donasi
1. Pembuatan program donasi baru wajib mengikat secara valid: `id_hasil_analisis`, `id_kth`, dan `id_bibit_spesifikasi`.
2. Status program tidak boleh berubah menjadi "Aktif" di publik sebelum mendapatkan validasi persetujuan oleh Kabid PDAS.

### Validasi Checkout & Pembatasan Stok (Krusial)
1. Sistem wajib membandingkan jumlah bibit yang diorder donatur dengan sisa target kebutuhan program **DAN** sisa stok fisik di tabel `BIBIT_SPESIFIKASI`.
2. Jika donatur memesan bibit melebihi salah satu dari limit tersebut, sistem harus memblokir form checkout dan memunculkan pesan error: *"Jumlah bibit melebihi ketersediaan stok atau kebutuhan program!"*
3. Sistem wajib menghitung total nominal tagihan secara otomatis di server backend dengan rumus: `Jumlah Bibit` x `Harga Bibit` dari spesifikasi yang terpilih.

### Validasi Transaksi & Pengurangan Stok
1. Ketika Staff PDAS menyetujui bukti pembayaran dan mengubah `status_transaksi` di tabel `TRANSAKSI` menjadi `"Success"`:
   * a. Sistem harus melakukan pemotongan `stok_bibit` di tabel `BIBIT_SPESIFIKASI` secara otomatis dan *real-time*.
   * b. Sistem harus memperbarui nilai `total_bibit_terkumpul` di tabel `PROGRAM_DONASI`.
   * c. Sistem harus secara otomatis meng-*generate* PDF sertifikat kontribusi berisi nama donatur (atau nama samaran jika anonim), jumlah bibit, dan nama program.

### Integritas Arsitektur Database
1. Demi menghindari *circular reference error* (*deadlock insert data*), foreign key rujukan antara donasi dan transaksi hanya diletakkan di tabel `TRANSAKSI` (`id_donasi`). Tidak boleh ada kolom `id_transaksi` di tabel `DONASI`.

### Fungsionalitas Pelaporan Dampak
1. Sistem wajib menampilkan rangkuman data kontribusi dan realisasi penanaman program secara presisi sesuai periode filter tanggal yang dipilih Kabid.
2. File laporan harus terbuat dengan status `"Success"`, terarsip di tabel `LAPORAN`, dan dapat diunduh tanpa merusak format visual dokumen.