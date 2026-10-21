# Hasil verifikasi implementasi

Tanggal: **18 Oktober 2026**. Lingkungan: Windows, PHP 8.2.30, Laravel 12, MySQL 8.0.30. Aplikasi memakai database `db_spk_ud_zufri`; pengujian memakai **db_spk_ud_zufri_test** terpisah dengan struktur dari SQL asli. Seeder sintetis opsional kini tersedia khusus local/testing, sesuai permintaan penambahan migration/seeder; instalasi tanpa seeding tetap kosong.

## Pengujian unit dan feature

Perintah:

```powershell
php artisan test --compact
```

Hasil setelah penambahan migration/seeder: **45 test lulus, 294 assertion**.

Tambahan verifikasi: seeder mengisi sembilan produk dan riwayat sintetis, menjalankan model PHP dan rekomendasi, tidak menggandakan data ketika dipanggil ulang, mempertahankan akun lain, serta menolak production. Siklus migration kosong, rollback, fresh dengan seeder, dan adopsi database hasil impor SQL diuji terpisah pada `db_spk_migration_check`. Sembilan produk dan satu proses analisis hasil impor tetap ada setelah migration dicatat. Rincian dan perintah reset ada di `MIGRATION_SEEDER.md`.

Cakupan: perhitungan uang BCMath; OLS regresi; pembatasan prediksi negatif; validasi rolling-origin; ketersediaan regresi independen dari kecukupan data K-Means; label centroid K-Means; silhouette termasuk singleton; penolakan fitur identik; registrasi dan hash; username/email duplikat; login email/username; logout; token reset sekali pakai; informasi SMTP belum siap; middleware auth; halaman kosong; CRUD; snapshot terbaru berdasarkan tanggal/ID; mutasi atomik dan penolakan stok negatif; proteksi riwayat; isolasi pemilik; CSV dan XLSX dengan tanggal serial; mapping/alias; validasi parsial/ketat; fingerprint; impor produk baru; pencatatan sumber RapidMiner/PHP; rollback hasil RapidMiner invalid; deteksi perubahan data meski total sama; rekomendasi dan status; filter; PDF/XLSX/CSV; template; dan sanitasi formula pada ekspor.

PDF diverifikasi memiliki header `%PDF-`. XLSX diverifikasi memiliki arsip ZIP asli, dapat dibuka kembali oleh PHPSpreadsheet, memuat sheet Ringkasan/Data dan grafik penjualan. Semua data test feature di-rollback. Uji request menggunakan middleware CSRF diaktifkan secara eksplisit pada satu test (test Laravel biasanya mengecualikan CSRF).

## Smoke test HTTP nyata

Menjalankan server khusus pada database uji (terminal terpisah):

```powershell
$env:DB_DATABASE='db_spk_ud_zufri_test'
$env:APP_URL='http://127.0.0.1:8001'
php artisan serve --host=127.0.0.1 --port=8001
```

Pada terminal lain:

```powershell
$env:DB_DATABASE='db_spk_ud_zufri_test'
php tests/http-smoke.php
Remove-Item Env:DB_DATABASE
```

Jangan menjalankan smoke test bersamaan dengan suite feature karena keduanya berbagi database uji. Script menolak database lain. Akun HTTP sementara dan data bisnis miliknya dihapus pada blok finally; file uji serta cookie sementara dibersihkan. Tidak ada akun uji yang dibuat pada database aplikasi.

Hasil: **22 pemeriksaan HTTP lulus**. Termasuk halaman login nyata, POST tanpa CSRF menghasilkan 419, registrasi, hash tersimpan pada MySQL, seluruh halaman terlindungi, tambah barang dan stok awal, multipart upload XLSX, preview total 0.30, konfirmasi impor, transaksi tersimpan, stok tidak dikurangi ulang, download PDF/XLSX, logout dan proteksi sesi sesudah logout.

## Pemeriksaan struktur

- SQL asli berhasil diimpor. Sepuluh tabel aplikasi dipertahankan; penggunaan migration menambahkan tabel pelacakan `migrations` Laravel. Tabel session/cache/jobs tidak diperlukan.
- Database aplikasi kosong: tidak ada akun, transaksi, stok, analisis atau rekomendasi palsu.
- Semua route aplikasi terdaftar melalui `php artisan route:list --except-vendor`.
- Semua Blade berhasil dikompilasi dengan `php artisan view:cache`.
- Sintaks PHP dan format diperiksa; kode PHP dirapikan dengan Laravel Pint.
- Composer manifest/lock konsisten dan seluruh dependensi PHP terpasang.
- Tidak ada referensi build frontend atau directive aset build pada Blade aplikasi. CSS/JS aplikasi langsung dari `public/assets`; Bootstrap/Chart.js dari CDN.

## Batas verifikasi yang dinyatakan

- SMTP ke mailbox nyata belum diuji karena tidak disediakan kredensial. Broker token dan perubahan password diuji; mailer yang belum siap memberikan pesan jujur.
- Proses RapidMiner eksternal tidak dijalankan. Kontrak impor dan penyimpanan hasil, sumber, validasi serta rollback diuji memakai fixture berlabel data uji.
- Chrome headless tidak dapat diluncurkan karena izin proses/IPC lingkungan (`Access is denied`), sehingga kecocokan visual piksel, tooltip/chart interaktif, perilaku print browser dan viewport perlu pemeriksaan browser manual. Render HTML, konfigurasi JSON grafik, route/form, dan unduhan sudah diuji di sisi aplikasi/HTTP.
- Lock database, transaksi atomik, rollback dan idempotensi diuji secara fungsional; simulasi beban banyak request serentak belum dilakukan.
- Checklist manual lengkap berada pada `BLACK_BOX.md`; daftar skenario bukan klaim semua pengujian manual telah dijalankan.
