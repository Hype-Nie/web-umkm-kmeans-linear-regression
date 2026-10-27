# Sistem Pendukung Keputusan Strategis Stok dan Analisis Penjualan UMKM Berbasis Business Intelligence

## Ringkasan Proyek

Sistem Pendukung Keputusan (SPK) ini dirancang untuk mengoptimalkan manajemen persediaan barang dan analisis transaksi penjualan pada usaha mikro, kecil, dan menengah (UMKM), dengan studi kasus pada UD. Zufri (Padangsidimpuan).

Sistem ini membantu pemilik usaha dalam mengatasi dua tantangan utama persediaan:
1. Penumpukan Stok Berlebih (Overstock): Barang yang lambat laku membebani modal kerja dan meningkatkan risiko kerusakan barang.
2. Kekosongan Stok (Stockout): Barang yang sangat laris habis sebelum pengadaan kembali dilakukan, sehingga potensi pendapatan hilang.

Aplikasi ini menggabungkan dasbor Business Intelligence (BI) interaktif dengan dua algoritma sains data:
- K-Means Clustering: Mengelompokkan produk secara otomatis ke dalam tiga kategori perputaran: Sangat Laris (Fast-Moving), Cukup (Medium-Moving), dan Kurang (Slow-Moving) berdasarkan volume penjualan, frekuensi transaksi, dan total pendapatan.
- Linear Regression (Regresi Linear): Memodelkan tren deret waktu penjualan bulanan untuk meramalkan estimasi kuantitas kebutuhan stok pada bulan berikutnya (y = a + bx).
- Mesin Rekomendasi Keputusan: Memadukan hasil klasterisasi, proyeksi penjualan regresi, stok fisik aktif saat ini, dan ambang batas aman (safety stock) untuk menghasilkan instruksi operasional yang jelas, seperti jumlah unit yang disarankan untuk dibeli kembali (restock) serta saran promosi atau penyesuaian harga untuk produk yang perputarannya rendah.

---

## Fitur Utama Sistem

### 1. Dasbor Eksekutif dan Visualisasi Business Intelligence
- Indikator Kinerja Utama (KPI): Menampilkan ringkasan total pendapatan riil, estimasi nilai aset persediaan aktif, jumlah produk yang membutuhkan restok segera, serta estimasi kebutuhan unit bulan depan.
- Grafik Tren Penjualan: Visualisasi interaktif pergerakan pendapatan historis berdasarkan filter rentang tanggal dan agregasi harian, mingguan, atau bulanan.
- Diagram Sebar Klaster (Scatter Plot): Pemetaan sebaran produk hasil analisis K-Means berdasarkan dimensi jumlah terjual dan frekuensi pembelian.

### 2. Manajemen Data Barang dan Riwayat Stok
- Pengelolaan katalog produk lengkap: kode barang, nama produk, kategori, harga beli/modal, harga satuan jual, dan batas stok minimum (safety stock).
- Pencatatan snapshot mutasi persediaan: stok awal, penjualan operasional, penambahan barang (restock), dan penyesuaian fisik.
- Perhitungan stok aktual yang akurat berdasarkan snapshot fisik terakhir, bukan sekadar penjumlahan mutasi lama.

### 3. Pencatatan dan Impor Transaksi Penjualan
- Pencatatan penjualan kasir langsung yang memotong stok aktif secara atomik.
- Pencatatan transaksi historis untuk keperluan analisis tanpa merusak snapshot stok fisik yang sudah tercatat.
- Ingesti berkas batch spreadsheet Excel (.xlsx) dan CSV dengan fitur pemetaan kolom fleksibel, validasi integritas baris, dan proteksi duplikasi data berbasis fingerprint SHA-256.
- Fitur pengunduhan template kosong resmi (.xlsx dan .csv) yang dibuat secara dinamis oleh sistem.

### 4. Modul Pemrosesan Sains Data Terintegrasi
- Perhitungan analitik langsung menggunakan mesin inferensi PHP bawaan di server web atau melalui impor berkas hasil pengolahan RapidMiner Studio.
- Normalisasi fitur menggunakan Z-score (mean = 0, standar deviasi = 1) dan penetapan klaster menggunakan jarak Euclidean kuadrat.
- Evaluasi metrik kualitas yang transparan: Silhouette Coefficient, Davies-Bouldin Index, Mean Squared Error (MSE), Root Mean Squared Error (RMSE), Mean Absolute Error (MAE), dan koefisien determinasi (R-Squared).

### 5. Rekomendasi Keputusan Operasional
- Kartu tugas rekomendasi terstruktur berdasarkan skala prioritas: Tinggi, Sedang, dan Rendah.
- Formulasi kuantitas restok adaptif:
  Reorder Quantity = max(0, Ramalan Penjualan + Safety Stock - Stok Aktif Saat Ini)
- Alur kerja status operasional: Baru, Ditinjau, dan Selesai.

### 6. Ekspor Laporan Resmi
- Pencetakan dan pengunduhan laporan formal dalam format PDF (Dompdf), spreadsheet Excel .xlsx (PhpSpreadsheet), dan berkas teks CSV.
- Pilihan laporan mencakup riwayat transaksi penjualan, posisi persediaan stok, segmentasi klaster produk, dan ringkasan rekomendasi keputusan pengadaan.

---

## Arsitektur Teknologi

- Backend: PHP versi 8.2+ dengan Framework Laravel 12
- Arsitektur Aplikasi: Pola Model-View-Controller (MVC) yang diperkuat dengan Service Layer khusus untuk memisahkan logika matematika dan sains data dari controller.
- Frontend: Blade Templates, Bootstrap 5.3 untuk tata letak responsif, Chart.js 4.4 untuk visualisasi data interaktif, dan CSS modular. Aplikasi berjalan mandiri tanpa memerlukan Node.js atau proses kompilasi aset frontend.
- Basis Data: MySQL 8.0+ atau MariaDB 10.4+
- Pustaka Pendukung: PhpSpreadsheet untuk pemrosesan spreadsheet dan Dompdf untuk pembuatan berkas PDF.

---

## Struktur Basis Data

Basis data dirancang menggunakan penamaan penuh Bahasa Indonesia yang selaras dengan dokumen rancangan UML Class Diagram dan skema sumber (db_spk_ud_zufri.sql):

1. users: Akun pemilik atau pengelola UMKM (id_user, username, email, password, nama_pemilik, nama_toko).
2. password_reset_tokens: Token pemulihan kata sandi akun (email, token, created_at).
3. barang: Master data katalog produk dagangan (id_barang, id_user, kode_barang, nama_barang, kategori, harga_satuan, harga_beli, stok_minimum).
4. stok_barang: Snapshot riwayat posisi fisik stok (id_stok, id_barang, tanggal_update, stok_sebelum, perubahan_stok, sisa_stok, sumber_data, keterangan).
5. riwayat_impor: Log riwayat pengunggahan berkas spreadsheet (id_impor, id_user, nama_file_asli, path_file, jenis_data, status, jumlah_baris, baris_berhasil, baris_gagal).
6. transaksi_penjualan: Catatan riwayat transaksi penjualan riil (id_transaksi, id_barang, kode_transaksi, tanggal_transaksi, jumlah_terjual, harga_satuan_jual, total_pendapatan, fingerprint_baris).
7. proses_analisis: Metadata riwayat eksekusi pemodelan data (id_proses, id_user, tanggal_mulai_data, tanggal_akhir_data, sumber_proses, jumlah_klaster, silhouette_coefficient, rmse, r_squared, status).
8. hasil_analisis_kmeans: Hasil segmentasi perputaran produk K-Means (id_klaster, id_proses, id_barang, nomor_klaster, label_klaster, jarak_ke_centroid).
9. hasil_prediksi_linear_regression: Hasil ramalan kuantitas kebutuhan unit masa depan (id_prediksi, id_proses, id_barang, periode_prediksi, prediksi_penjualan, angka_ramalan_stok).
10. rekomendasi_keputusan: Rekomendasi tindakan restok dan promosi (id_rekomendasi, id_proses, id_barang, jenis_rekomendasi, prioritas, judul, deskripsi, jumlah_restock_disarankan, status).

---

## Kebutuhan Sistem

- PHP versi 8.2, 8.3, atau 8.4
- Composer versi 2.x
- MySQL 8.0+ atau MariaDB 10.4+ (misalnya melalui Laragon atau XAMPP)
- Ekstensi PHP yang wajib aktif: pdo_mysql, bcmath, mbstring, openssl, fileinfo, gd, zip, xml, dom, intl, curl.
- Konfigurasi PHP (php.ini) yang disarankan:
  - upload_max_filesize = 10M
  - post_max_size = 12M
  - memory_limit = 512M

---

## Panduan Instalasi dan Menjalankan Aplikasi

### 1. Klon Repositori
```bash
git clone https://github.com/Hype-Nie/web-umkm-kmeans-linear-regression.git
cd web-umkm-kmeans-linear-regression
```

### 2. Instalasi Dependensi PHP
```bash
composer install
```

### 3. Konfigurasi Lingkungan (.env)
Salin berkas konfigurasi contoh:
```bash
cp .env.example .env
```
Pada terminal Windows PowerShell, gunakan perintah:
```powershell
Copy-Item .env.example .env
```

Buka berkas .env dan sesuaikan parameter koneksi basis data:
```dotenv
APP_NAME="SPK UMKM UD Zufri"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_spk_ud_zufri
DB_USERNAME=root
DB_PASSWORD=
```

Buat kunci enkripsi aplikasi:
```bash
php artisan key:generate
```

### 4. Setup Basis Data dan Migrasi
Buat database baru bernama db_spk_ud_zufri pada MySQL/phpMyAdmin Anda.

Jalankan perintah migrasi skema tabel:
```bash
php artisan migrate
```

Untuk mengisi data simulasi pengujian (opsional, untuk mencoba sistem secara instan dengan 9 produk uji, data transaksi 24 bulan, dan snapshot stok):
```bash
php artisan db:seed
```
Seeder data asli membaca berkas `resources/transaksi_gabungan.csv` dari root project menggunakan path Laravel, sehingga project dapat dipindahkan ke komputer atau server lain tanpa mengubah path di kode. Pastikan berkas tersebut sudah tersedia sebelum menjalankan `php artisan migrate:fresh --seed`.

Kredensial login akun pengujian:
- Username: admin_uji (atau email: admin.uji@example.test)
- Password: UjiUmkm123!

### 5. Menjalankan Server Lokal
Jalankan server pengembangan:
```bash
php artisan serve
```
Buka peramban (browser) dan akses aplikasi pada alamat: http://127.0.0.1:8000

---

## Panduan Pengujian Sistem

Proyek ini telah melalui pengujian otomatis menyeluruh (54 kasus uji dan 335 asersi pengujian) yang mencakup pengujian unit algoritma, integritas transaksi, isolasi antarpemilik data, impor berkas, dan ekspor dokumen.

Untuk menjalankan suite pengujian otomatis:
```bash
php artisan test
```

---

## Lisensi dan Hak Cipta

Dikembangkan sebagai implementasi sistem pendukung keputusan strategis stok dan analisis penjualan UMKM UD. Zufri (Padangsidimpuan).

Kode sumber dirilis di bawah Lisensi MIT.
