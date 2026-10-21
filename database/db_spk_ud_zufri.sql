-- ================================================================
-- DATABASE SPK BUSINESS INTELLIGENCE UD. ZUFRI
-- Berdasarkan proposal 'Perancangan Sistem Pendukung Keputusan
-- Strategis Stok dan Analisis Penjualan UMKM Berbasis BI
-- Menggunakan RapidMiner' (2026).
--
-- DBMS             : MySQL / MariaDB (phpMyAdmin / XAMPP)
-- Charset          : utf8mb4
-- CATATAN PENTING : Skema saja, tanpa data transaksi fiktif.
--                    Import file ini pada phpMyAdmin -> Import.
--                    Jika database dibuat lebih dulu, bisa
--                    menghapus dua baris CREATE DATABASE dan USE.
--                    Tidak memakai npm atau sistem build aset.
--
-- Penyesuaian proposal: class diagram menamai class prediksi
-- HasilPrediksiRandomForest, sedangkan metode penelitian berulang
-- kali menyatakan LINEAR REGRESSION. Tabel di bawah mengikuti
-- Linear Regression sesuai rumusan/batasan/tujuan penelitian.
-- ================================================================

CREATE DATABASE IF NOT EXISTS `db_spk_ud_zufri`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_spk_ud_zufri`;

-- 1) Akun pemilik/manajemen UMKM (class User)
CREATE TABLE IF NOT EXISTS `users` (
  `id_user` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'Simpan bcrypt/Argon2 hasil Hash::make; bukan plaintext',
  `nama_pemilik` VARCHAR(191) NOT NULL,
  `nama_toko` VARCHAR(191) NOT NULL,
  `remember_token` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Master barang (class Barang)
CREATE TABLE IF NOT EXISTS `barang` (
  `id_barang` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user` BIGINT UNSIGNED NOT NULL,
  `kode_barang` VARCHAR(60) NOT NULL,
  `nama_barang` VARCHAR(191) NOT NULL,
  `kategori` VARCHAR(120) DEFAULT NULL,
  `harga_satuan` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Harga jual default',
  `harga_beli` DECIMAL(15,2) DEFAULT NULL COMMENT 'Opsional, hanya untuk estimasi margin/ROI yang punya dasar data',
  `stok_minimum` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ambang stok kritis/safety stock yang dapat dikonfigurasi',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id_barang`),
  UNIQUE KEY `uq_barang_user_kode` (`id_user`, `kode_barang`),
  KEY `idx_barang_user_kategori` (`id_user`, `kategori`),
  CONSTRAINT `fk_barang_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Rekam jejak unggahan (fitur kelola data/upload Excel)
CREATE TABLE IF NOT EXISTS `riwayat_impor` (
  `id_impor` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user` BIGINT UNSIGNED NOT NULL,
  `nama_file_asli` VARCHAR(255) NOT NULL,
  `path_file` VARCHAR(500) DEFAULT NULL,
  `jenis_data` ENUM('transaksi','stok','gabungan') NOT NULL DEFAULT 'gabungan',
  `status` ENUM('diproses','berhasil','gagal','parsial') NOT NULL DEFAULT 'diproses',
  `jumlah_baris` INT UNSIGNED NOT NULL DEFAULT 0,
  `baris_berhasil` INT UNSIGNED NOT NULL DEFAULT 0,
  `baris_gagal` INT UNSIGNED NOT NULL DEFAULT 0,
  `rincian_error` LONGTEXT NULL COMMENT 'JSON/text laporan validasi per baris',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id_impor`),
  KEY `idx_riwayat_impor_user_waktu` (`id_user`, `created_at`),
  CONSTRAINT `fk_riwayat_impor_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Penjualan per baris produk (class TransaksiPenjualan)
--    Satu nomor/kode transaksi dapat berisi lebih dari satu item.
CREATE TABLE IF NOT EXISTS `transaksi_penjualan` (
  `id_transaksi` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_barang` BIGINT UNSIGNED NOT NULL,
  `id_impor` BIGINT UNSIGNED DEFAULT NULL,
  `kode_transaksi` VARCHAR(100) DEFAULT NULL,
  `tanggal_transaksi` DATETIME NOT NULL,
  `jumlah_terjual` INT UNSIGNED NOT NULL,
  `harga_satuan_jual` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_pendapatan` DECIMAL(17,2) NOT NULL DEFAULT 0.00,
  `sumber_data` ENUM('manual','impor') NOT NULL DEFAULT 'manual',
  `fingerprint_baris` CHAR(64) DEFAULT NULL COMMENT 'SHA256 stabil untuk mencegah impor duplikat',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id_transaksi`),
  UNIQUE KEY `uq_transaksi_fingerprint` (`fingerprint_baris`),
  KEY `idx_transaksi_barang_tanggal` (`id_barang`, `tanggal_transaksi`),
  KEY `idx_transaksi_kode_tanggal` (`kode_transaksi`, `tanggal_transaksi`),
  KEY `idx_transaksi_tanggal` (`tanggal_transaksi`),
  CONSTRAINT `fk_transaksi_barang` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id_barang`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_transaksi_impor` FOREIGN KEY (`id_impor`) REFERENCES `riwayat_impor` (`id_impor`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Riwayat snapshot persediaan (class StokBarang)
--    Stok terkini = snapshot TERAKHIR menurut tanggal_update,
--    lalu id_stok (untuk memecahkan tanggal yang sama).
CREATE TABLE IF NOT EXISTS `stok_barang` (
  `id_stok` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_barang` BIGINT UNSIGNED NOT NULL,
  `id_impor` BIGINT UNSIGNED DEFAULT NULL,
  `tanggal_update` DATETIME NOT NULL,
  `stok_sebelum` INT UNSIGNED DEFAULT NULL,
  `perubahan_stok` INT DEFAULT NULL,
  `sisa_stok` INT UNSIGNED NOT NULL,
  `sumber_data` ENUM('stok_awal','penjualan','restock','penyesuaian','impor') NOT NULL DEFAULT 'penyesuaian',
  `keterangan` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id_stok`),
  KEY `idx_stok_barang_tanggal` (`id_barang`, `tanggal_update`, `id_stok`),
  CONSTRAINT `fk_stok_barang` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id_barang`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_stok_impor` FOREIGN KEY (`id_impor`) REFERENCES `riwayat_impor` (`id_impor`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) Satu kali pemrosesan model BI / bukti asal hasil analisis
CREATE TABLE IF NOT EXISTS `proses_analisis` (
  `id_proses` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user` BIGINT UNSIGNED NOT NULL,
  `tanggal_mulai_data` DATE DEFAULT NULL,
  `tanggal_akhir_data` DATE DEFAULT NULL,
  `sumber_proses` ENUM('rapidminer','backend_php') NOT NULL DEFAULT 'rapidminer',
  `nama_file_hasil` VARCHAR(255) DEFAULT NULL COMMENT 'Bukti hasil ekspor RapidMiner, bila digunakan',
  `jumlah_klaster` TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `algoritma_klaster` VARCHAR(60) NOT NULL DEFAULT 'K-Means',
  `algoritma_prediksi` VARCHAR(60) NOT NULL DEFAULT 'Linear Regression',
  `silhouette_coefficient` DECIMAL(10,6) DEFAULT NULL,
  `mse` DECIMAL(20,6) DEFAULT NULL,
  `rmse` DECIMAL(20,6) DEFAULT NULL,
  `mae` DECIMAL(20,6) DEFAULT NULL,
  `r_squared` DECIMAL(12,6) DEFAULT NULL,
  `status` ENUM('diproses','berhasil','gagal') NOT NULL DEFAULT 'diproses',
  `catatan` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id_proses`),
  KEY `idx_proses_user_waktu` (`id_user`, `created_at`),
  CONSTRAINT `fk_proses_analisis_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7) Hasil K-Means per produk (class HasilAnalisisKMeans)
CREATE TABLE IF NOT EXISTS `hasil_analisis_kmeans` (
  `id_klaster` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_proses` BIGINT UNSIGNED NOT NULL,
  `id_barang` BIGINT UNSIGNED NOT NULL,
  `tanggal_analisis` DATETIME NOT NULL,
  `nomor_klaster` TINYINT UNSIGNED NOT NULL COMMENT 'Indeks klaster 0/1/2 atau 1/2/3 dari pemodelan',
  `label_klaster` ENUM('Sangat Laris','Cukup','Kurang') NOT NULL,
  `nilai_centroid` DOUBLE DEFAULT NULL COMMENT 'Atribut kompatibilitas class diagram; bila centroid multidimensi gunakan kolom per fitur',
  `centroid_jumlah_terjual` DOUBLE DEFAULT NULL,
  `centroid_frekuensi_pembelian` DOUBLE DEFAULT NULL,
  `fitur_jumlah_terjual` INT UNSIGNED NOT NULL DEFAULT 0,
  `fitur_frekuensi_pembelian` INT UNSIGNED NOT NULL DEFAULT 0,
  `jarak_ke_centroid` DOUBLE DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id_klaster`),
  UNIQUE KEY `uq_kmeans_proses_barang` (`id_proses`, `id_barang`),
  KEY `idx_kmeans_barang_tanggal` (`id_barang`, `tanggal_analisis`),
  CONSTRAINT `fk_kmeans_proses` FOREIGN KEY (`id_proses`) REFERENCES `proses_analisis` (`id_proses`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_kmeans_barang` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id_barang`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8) Hasil ramalan Linear Regression per produk/periode
--    Mengganti penamaan 'RandomForest' yang keliru di class diagram.
CREATE TABLE IF NOT EXISTS `hasil_prediksi_linear_regression` (
  `id_prediksi` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_proses` BIGINT UNSIGNED NOT NULL,
  `id_barang` BIGINT UNSIGNED NOT NULL,
  `tanggal_analisis` DATETIME NOT NULL,
  `periode_prediksi` DATE NOT NULL COMMENT 'Tanggal 1 pada bulan yang diprediksi',
  `prediksi_penjualan` DECIMAL(15,4) DEFAULT NULL COMMENT 'Output mentah regresi sebelum pembulatan',
  `angka_ramalan_stok` INT UNSIGNED NOT NULL COMMENT 'Kebutuhan unit dibulatkan ke atas, minimal nol',
  `nilai_akurasi` DECIMAL(10,4) DEFAULT NULL COMMENT 'Opsional sesuai class diagram; hanya isi bila definisi metrik jelas',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id_prediksi`),
  UNIQUE KEY `uq_prediksi_proses_barang_bulan` (`id_proses`, `id_barang`, `periode_prediksi`),
  KEY `idx_prediksi_barang_periode` (`id_barang`, `periode_prediksi`),
  CONSTRAINT `fk_prediksi_proses` FOREIGN KEY (`id_proses`) REFERENCES `proses_analisis` (`id_proses`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_prediksi_barang` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id_barang`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9) Rekomendasi tindak lanjut (restock / promosi) berbasis data
CREATE TABLE IF NOT EXISTS `rekomendasi_keputusan` (
  `id_rekomendasi` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_proses` BIGINT UNSIGNED NOT NULL,
  `id_barang` BIGINT UNSIGNED NOT NULL,
  `tanggal_rekomendasi` DATETIME NOT NULL,
  `jenis_rekomendasi` ENUM('restock','promosi') NOT NULL,
  `prioritas` ENUM('Tinggi','Sedang','Rendah') NOT NULL,
  `judul` VARCHAR(255) NOT NULL,
  `deskripsi` TEXT NOT NULL,
  `stok_saat_analisis` INT UNSIGNED DEFAULT NULL,
  `ramalan_kebutuhan` INT UNSIGNED DEFAULT NULL,
  `jumlah_restock_disarankan` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('baru','ditinjau','selesai') NOT NULL DEFAULT 'baru',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id_rekomendasi`),
  KEY `idx_rekomendasi_proses_prioritas` (`id_proses`, `prioritas`),
  KEY `idx_rekomendasi_barang_waktu` (`id_barang`, `tanggal_rekomendasi`),
  CONSTRAINT `fk_rekomendasi_proses` FOREIGN KEY (`id_proses`) REFERENCES `proses_analisis` (`id_proses`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_rekomendasi_barang` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id_barang`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10) Token pemulihan password (tautan 'Lupa kata sandi' pada login)
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` VARCHAR(191) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- PETUNJUK INTEGRASI LARAVEL
-- 1. Set DB_DATABASE=db_spk_ud_zufri di .env Laravel.
--    Set SESSION_DRIVER=file, CACHE_STORE=file,
--    QUEUE_CONNECTION=sync, agar tidak memerlukan tabel Laravel lain.
-- 2. Atur key utama kustom ('id_user', 'id_barang', dll.) di Eloquent.
-- 3. Skema sudah dibuat manual: jangan jalankan migration Laravel
--    bawaan yang membuat ulang tabel users dengan struktur berbeda.
-- 4. Hash password dengan Laravel Hash::make() saat registrasi.
-- 5. Semua query wajib dibatasi berdasarkan users.id_user.
-- 6. Stok aktual dihitung dari snapshot TERAKHIR; bukan SUM(sisa_stok).
-- 7. Impor stok historis yang sudah mencerminkan penjualan TIDAK
--    otomatis dikurangi lagi saat data penjualan diimpor.
-- 8. Output K-Means/Linear Regression harus berasal dari perhitungan
--    nyata atau berkas hasil RapidMiner, tidak dibuat acak.
-- 9. Tanpa seed fiktif; gunakan data asli/hasil impor pengguna.
-- ================================================================
