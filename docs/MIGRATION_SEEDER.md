# Migration dan seeder untuk pengujian

Migration: `database/migrations/2026_10_18_000000_create_spk_tables.php`.
Seeder utama: `database/seeders/DatabaseSeeder.php`, memanggil `TestingDataSeeder`.

Migration memakai definisi CREATE TABLE SQL asli sebagai satu sumber struktur: primary key nonstandar, DECIMAL, enum, indeks, nama foreign key dan cascade dipertahankan. CREATE DATABASE/USE tidak dijalankan oleh migration: buat database terlebih dahulu dan atur DB_DATABASE. Satu tabel tambahan `migrations` dikelola Laravel untuk melacak migration. Session/cache/queue tetap berbasis file/sync.

| Kebutuhan | Perintah |
| --- | --- |
| Buat tabel / catat versi database hasil impor SQL | `php artisan migrate` |
| Isi akun dan data pengujian | `php artisan db:seed` |
| Buat tabel sekaligus data uji | `php artisan migrate --seed` |
| Reset database terpilih lalu isi data uji | `php artisan migrate:fresh --seed` |
| Reset database terpilih menjadi kosong | `php artisan migrate:fresh` |
| Batalkan migration (menghapus sepuluh tabel SPK) | `php artisan migrate:rollback` |

`migrate:fresh` menghapus SEMUA tabel database yang dikonfigurasi, bukan hanya akun uji. `rollback` juga menghapus tabel yang sebelumnya diimpor dari SQL setelah migration dicatat. Gunakan database pengujian atau cadangkan data dahulu. Reset tidak menghapus file unggahan/session pada disk. Tidak ada reset terhadap database pengguna yang dilakukan saat implementasi fitur ini.

## Data dan login

- Username `admin_uji`, email `admin.uji@example.test`, password awal `UjiUmkm123!`.
- Nama pemilik, toko dan sembilan produk ditandai `[DATA UJI]`.
- Akun bertanda tersebut menampilkan banner data uji di halaman aplikasi.
- Transaksi deterministik selama 24 bulan lengkap ditambah bulan berjalan; tanggal mengikuti waktu saat seeder dijalankan dan tidak masuk masa depan.
- Snapshot stok historis untuk variasi stok kritis dan berlebih. Tidak ada pengurangan stok kedua untuk transaksi historis.
- Analisis memakai service K-Means dan Linear Regression PHP yang sama dengan aplikasi. Rekomendasi dihitung dari hasil nyata service tersebut atas masukan sintetis, dengan metadata yang menyatakan data uji.
- Riwayat impor, bukti RapidMiner dan token reset dibiarkan kosong karena tidak ada upload/pengiriman yang benar-benar dilakukan oleh seeder.
- Pemanggilan ulang tidak menggandakan data yang sama dan tidak mengubah akun lain. Password akun uji yang sudah diganti tidak ditimpa. Konflik username/email dengan akun yang bukan akun uji membatalkan seeder.
- Seeder menolak APP_ENV di luar local/testing, termasuk production.

## Verifikasi perubahan

Siklus database kosong → migrate --seed → seed ulang → rollback → migrate --seed → migrate:fresh --seed diuji pada database khusus `db_spk_migration_check`. Data/database aplikasi yang digunakan pengguna tidak di-reset. Suite feature juga memeriksa idempotensi, isolasi akun lain, sumber hasil analisis serta penolakan seeder pada production.

Untuk uji manual berisi seeder gunakan database sandbox terpisah. Database `db_spk_ud_zufri_test` untuk suite otomatis perlu kosong sebelum suite berjalan; test seeder mengisi datanya di dalam transaksi dan membersihkannya melalui rollback.

Jangan menyimpan data uji sebagai hasil penelitian asli. Untuk riset gunakan akun sendiri dan unggah data bisnis yang benar.
