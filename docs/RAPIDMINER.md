# Alur pengolahan dan impor hasil RapidMiner

Integrasi memakai berkas CSV/XLSX; tidak memerlukan API RapidMiner. Pengguna menjalankan proses penelitiannya di RapidMiner dan mengekspor hasil sesuai kontrak. Implementasi tidak mengklaim proses RapidMiner telah dijalankan pada mesin ini.

## Menyiapkan data

1. Impor transaksi asli sekitar 24 bulan dan snapshot stok ke aplikasi. Rentang tanggal bebas mengikuti data, bukan tahun yang dikunci.
2. Di Hasil Analisis, buat proses dengan rentang data yang akan dipakai. Jika K-Means belum dapat berjalan, proses gagal tetap mencatat periode dan tombol **Ekspor Dataset ke RapidMiner** tersedia.
3. Unduh CSV transaksi milik akun dan periode proses tersebut. Kolomnya: kode_barang, nama_barang, kode_transaksi, tanggal_transaksi, jumlah_terjual, harga_satuan_jual, total_pendapatan.
4. Buka CSV di RapidMiner. Pastikan kode_barang adalah identitas nominal, waktu diparse sesuai `yyyy-MM-dd HH:mm:ss`, jumlah dan harga numerik. Dataset tidak berisi snapshot stok; stok aktual tetap diambil dari database saat rekomendasi dibuat.

## Cabang K-Means

Agregasikan per kode_barang: jumlah unit terjual dan frekuensi kejadian pembelian unik. Gunakan definisi frekuensi yang sama dengan aplikasi (tanggal+kode struk, atau tanggal+waktu jika kode kosong). Jika memasukkan seluruh master produk, nilai (0,0) hanya diberikan pada produk yang benar-benar tidak memiliki transaksi di periode yang dianggap lengkap.

Standardisasi dua fitur dengan z-score dan jalankan K-Means dengan K=3, jarak Euclidean, konfigurasi inisialisasi/seed serta batas iterasi yang didokumentasikan di catatan penelitian. Hasil inisialisasi RapidMiner dapat berbeda dari farthest-first PHP. Perbedaan assignment perlu dibandingkan, bukan ditutupi. Silhouette, jika dihitung, harus menggunakan titik dan ruang fitur yang sama. Ekspor centroid kembali ke **unit asli** memakai mean/std dari transformasi normalisasi.

## Cabang Linear Regression

Agregasikan jumlah terjual per produk dan per bulan kalender lengkap. Tentukan arti bulan kosong (nol atau data hilang), keluarkan bulan parsial/berjalan, dan gunakan minimal 3 bulan lengkap. Bentuk `time_index=1..n` sebagai satu prediktor X. Y adalah jumlah unit. Jalankan Linear Regression, lalu terapkan pada `time_index=n+1`. Jangan mengganti algoritma menjadi Random Forest atau moving average.

Untuk perbandingan yang setara dengan PHP, gunakan validasi rolling-origin: prediksi bulan ke-3 dari bulan 1..2 dan lanjutkan maju tanpa data masa depan. MSE/RMSE/MAE dihitung dari error prediksi kontinu yang dibatasi minimal 0; R² perlu minimal dua nilai aktual dengan variasi. Jangan mengekspor metrik pelatihan in-sample dengan keterangan validasi temporal.

## Menggabungkan hasil dan kontrak unggahan

Unduh **Template XLSX/CSV** di kartu Impor Hasil RapidMiner. Join hasil klaster dan prediksi pada kode_barang sebelum ekspor agar satu baris mewakili satu produk dalam satu periode analisis. Tidak perlu menyalin angka secara manual bila menggunakan operator join/export di RapidMiner.

| Kolom | Isi |
| --- | --- |
| kode_barang | Wajib; harus sudah ada pada akun yang mengunggah |
| tanggal_mulai_data | Wajib, YYYY-MM-DD, sama untuk semua produk |
| tanggal_akhir_data | Wajib, YYYY-MM-DD, sama untuk semua produk |
| nomor_klaster | Wajib; tepat tiga indeks unik 0,1,2 atau 1,2,3, semua tidak kosong |
| fitur_jumlah_terjual | Wajib, integer nonnegatif, total per produk dalam periode |
| fitur_frekuensi_pembelian | Wajib, integer nonnegatif, frekuensi per produk |
| centroid_jumlah_terjual | Wajib, centroid klaster dalam unit asli |
| centroid_frekuensi_pembelian | Wajib, centroid klaster dalam unit asli |
| jarak_ke_centroid | Opsional; aplikasi menghitung ulang jarak z-score dari fitur/centroid |
| periode_prediksi | Wajib bila prediksi diisi; tanggal 1 bulan setelah tanggal_akhir_data |
| prediksi_penjualan | Opsional; nilai mentah Linear Regression sebelum pembulatan; boleh negatif |
| silhouette_coefficient | Opsional, −1..1, metrik proses; kosong bila tidak dievaluasi |
| mse, rmse, mae | Opsional, nonnegatif, metrik proses dengan definisi evaluasi yang sama |
| r_squared | Opsional, ≤1; nilai negatif dapat valid; kosong bila tak terdefinisi |

Centroid pada anggota klaster yang sama harus konsisten. File dapat memuat hasil klaster saja; produk yang tidak disertai prediksi ditampilkan **hasil tidak disertakan**, dan tidak diberi rekomendasi. Untuk menambahkan prediksi, unggah ulang berkas gabungan sebagai proses baru, agar hasil K-Means dan regresi terkait satu proses dan periode yang sama.

Metrik opsional pada template ini berada pada tingkat **proses**, sehingga nilainya harus konsisten jika diulang pada beberapa baris. Untuk perbandingan ilmiah per produk, simpan evaluasi per produk pada berkas penelitian terpisah; jangan menaruh metrik berbeda tiap produk ke kolom tingkat proses. Aplikasi tidak merata-ratakan R² per produk.

## Mengimpor dan memeriksa

1. Pilih file pada kartu Impor Hasil RapidMiner. Tinjau isinya sebelum mengunggah, lalu centang konfirmasi metode dan periode.
2. Klik **Validasi & Impor Hasil**. Penyimpanan satu proses bersifat atomik: satu kesalahan membatalkan semua hasil, dan proses gagal menyimpan alasan.
3. Tinjau identitas file, `rapidminer`, periode, label/centroid, prediksi mentah dan kebutuhan unit pada halaman hasil. Nilai negatif menjadi kebutuhan 0; nilai positif dibulatkan ke atas.
4. File bukti disimpan di penyimpanan privat `storage/app/private/rapidminer/<id_user>/`, bukan URL publik. Jangan menjadikan direktori privat sebagai document root.
5. Buka Rekomendasi, pilih **Susun Rekomendasi**. Pastikan snapshot stok tersedia. Untuk laporan prediksi, filter memakai bulan prediksi; untuk laporan klaster, filter memakai tanggal pemrosesan.

Setelah transaksi atau stok berubah, hasil lama ditandai kedaluwarsa. Jalankan proses RapidMiner lagi dengan data terbaru, ekspor berkas gabungan, dan impor sebagai proses baru; atau gunakan Hitung Langsung dengan sumber `backend_php`.
