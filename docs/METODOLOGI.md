# Metodologi dan batas interpretasi

Sistem: **Sistem Pendukung Keputusan Strategis Stok dan Analisis Penjualan UMKM Berbasis Business Intelligence**. Dasar implementasi adalah ketentuan penelitian pada permintaan dan `database/db_spk_ud_zufri.sql`. Berkas proposal penelitian terpisah belum tersedia di workspace pada saat implementasi; tidak ada klaim telah memverifikasi dokumen yang tidak tersedia.

## Kepemilikan dan asal data

Semua produk melalui `users.id_user → barang.id_user`. Transaksi dan stok dibatasi melalui relasi produk; proses dan hasil dibatasi melalui pemilik proses. ID yang dimanipulasi untuk mengakses akun lain mendapat 404. Instalasi tanpa seeding tetap kosong. Atas permintaan penambahan migration/seeder, data sintetis opsional disediakan khusus akun bertanda [DATA UJI], bukan dianggap data penelitian asli. Hasil model atas data uji dihitung memakai service yang sama. Suite otomatis membuat fixture dalam transaksi pada database `db_spk_ud_zufri_test`, lalu di-rollback.

## Snapshot stok dan transaksi

Stok terkini adalah satu snapshot dengan `tanggal_update` paling baru, diikuti `id_stok` terbesar. Produk tanpa snapshot menampilkan **Belum tercatat**, bukan diasumsikan nol. KPI total stok dan kritis hanya menggunakan produk yang sudah memiliki snapshot. Filter dashboard berlaku pada penjualan; stok dashboard adalah posisi terkini.

Stok awal/penyesuaian adalah nilai akhir absolut. Restock menambah nilai snapshot aktif. Transaksi operasional opsional mengurangi stok di dalam transaksi database dengan penguncian produk (`lockForUpdate`). Semua mutasi yang menyebabkan stok negatif dibatalkan bersama transaksinya. Mutasi operasional sebelum snapshot terakhir ditolak.

Transaksi historis manual dan impor tidak mengurangi snapshot. Penjualan operasional yang sudah membuat riwayat stok dikunci dari edit/hapus untuk menjaga audit; koreksi stok dicatat sebagai penyesuaian dan koreksi transaksi historis dicatat terpisah. Barang yang memiliki riwayat tidak dapat dihapus, meskipun SQL aslinya mendefinisikan cascade. Snapshot dipertahankan sebagai audit, tidak diedit/dihapus melalui UI.

Uang disimpan sesuai DECIMAL SQL dan dihitung menggunakan BCMath. Harga masukan memakai desimal titik tanpa pemisah ribuan. Total server = jumlah × harga; nilai total kiriman browser tidak dipercaya. Nilai hasil query yang diplot dikonversi ke angka hanya untuk presentasi grafik, bukan penghitungan uang.

## K-Means (backend_php)

1. Ambil seluruh produk pemilik dan transaksi di rentang yang dipilih. Produk tanpa penjualan dalam periode mempunyai fitur (0,0); tidak ada transaksi buatan.
2. Fitur pertama: `SUM(jumlah_terjual)`. Fitur kedua: frekuensi kejadian pembelian unik per produk. Identitas kejadian adalah tanggal + kode transaksi, atau tanggal + jam transaksi bila kode tidak tersedia. Dua baris produk pada struk yang sama dihitung satu kejadian pembelian.
3. Standardisasi z-score populasi: `(x − mean)/std`. Jika std sebuah fitur nol, gunakan skala 1; fitur tersebut menjadi nol pada seluruh produk.
4. Inisialisasi deterministik farthest-first: titik dengan jumlah koordinat terstandar terendah, kemudian titik terjauh dari centroid terdekat sampai ada 3 centroid.
5. Assignment dengan jarak Euclidean, lalu pembaruan centroid sebagai rata-rata titik anggota. Konvergen bila assignment tidak berubah. Batas 300 iterasi; kegagalan konvergensi atau klaster kosong dicatat gagal.
6. K selalu 3. Minimal 3 produk dengan 3 kombinasi fitur berbeda. Tidak ada klaster yang dibuat untuk melengkapi jumlah kelompok secara palsu.
7. Peringkat centroid berdasarkan jumlah koordinat z-score: terendah **Kurang**, tengah **Cukup**, tertinggi **Sangat Laris**. Label tidak bergantung nomor klaster. Centroid yang disimpan ditransformasikan kembali ke unit asli; jarak disimpan dalam ruang z-score.
8. Silhouette dihitung dari jarak antartitik terstandardisasi: `s=(b−a)/max(a,b)`, a = rata-rata jarak ke anggota klaster sendiri, b = rata-rata jarak terkecil ke klaster lain. Singleton memiliki s=0. Rata-rata hanya tersedia untuk N>K. Penghitungan PHP dibatasi N≤2.000 karena kompleksitas kuadratik; klaster tetap dihitung untuk N yang lebih besar, silhouette dibiarkan NULL.

Tidak ditampilkan grafik elbow karena K sudah ditetapkan penelitian menjadi 3 dan tidak ada eksperimen nilai K lain. Grafik scatter, centroid, tabel dan silhouette menggantikan placeholder tanpa mengarang hasil.

Ketersediaan dua model dievaluasi secara terpisah. Jika produk belum mendukung K=3 tetapi seri bulanan sudah cukup, hasil regresi tetap disimpan, panel K-Means memberi alasan tidak tersedia, dan status masing-masing model dicatat. Proses berhasil jika sedikitnya satu model menghasilkan hasil nyata; jika keduanya belum dapat menghasilkan hasil, proses gagal. Rekomendasi tetap memerlukan hasil kedua model untuk produk yang sama.

## Linear Regression (backend_php)

Gunakan bulan kalender lengkap di dalam rentang data; bulan pertama parsial, bulan terakhir parsial, dan bulan berjalan tidak dipakai. Titik X=1..n dan Y=jumlah unit per produk per bulan.

`b = Σ((X−X̄)(Y−Ȳ))/Σ((X−X̄)²)`

`a = Ȳ − bX̄`

`prediksi_mentah = a + b(n+1)`

`kebutuhan_unit = ceil(max(0, prediksi_mentah))`

Nilai mentah disimpan terpisah dalam DECIMAL(15,4). Tanggal prediksi adalah tanggal 1 bulan sesudah bulan lengkap terakhir yang digunakan, **bukan otomatis bulan setelah hari ini** jika rentang data historis dipilih. UI menampilkan periode tersebut secara eksplisit.

Minimal 3 bulan lengkap untuk mendukung model dan evaluasi bergulir. Jika suatu produk tidak memiliki transaksi pada salah satu bulan, artinya belum dianggap nol. Produk diberi status Data tidak cukup, kecuali pemilik menandai konfirmasi bahwa semua bulan pada seluruh produk lengkap dan ketiadaan transaksi berarti nol. Pilihan itu menyatakan asumsi pengguna; aplikasi tidak bisa membuktikan kelengkapan file hanya dari ketiadaan transaksi. Toko/produk yang belum beroperasi selama seluruh rentang sebaiknya dianalisis dengan rentang lebih sempit; jangan mengonfirmasi kelengkapan secara keliru.

Evaluasi rolling-origin: latih bulan 1..2, prediksi bulan 3; latih 1..3, prediksi 4; dan seterusnya. Evaluasi memakai prediksi kontinu dibatasi minimal nol sebelum pembulatan kebutuhan. MSE=rata-rata kuadrat error, RMSE=akar MSE, MAE=rata-rata absolut error. R² hanya bila ada minimal 2 titik validasi dan variasi aktual >0. Tidak ada persentase akurasi yang diperoleh dari `100−error`. Metrik, koefisien, bulan, dan data historis per produk disimpan pada JSON `proses_analisis.catatan` agar tidak mengubah SQL. Kolom metrik proses dibiarkan NULL untuk hasil PHP karena hasil model berbeda tiap produk; tidak dipaksakan R² gabungan.

## RapidMiner

Hasil unggahan disimpan sebagai `sumber_proses=rapidminer`, hasil hitung langsung sebagai `backend_php`. File bukti disimpan privat dan lokasi dicatat pada catatan proses. Lihat `RAPIDMINER.md` untuk kontrak kolom. Aplikasi memvalidasi kepemilikan produk, jumlah klaster, konsistensi centroid/periode, tipe dan batas angka, serta periode prediksi. Nilai prediksi/fitur/centroid berasal dari berkas pengguna; aplikasi tidak menyatakan telah menjalankan RapidMiner secara langsung atau mengaudit seluruh operator di dalam proyek RapidMiner.

Label klaster diurut ulang menurut centroid fitur dalam unit asli setelah standardisasi menggunakan populasi produk yang diimpor. Jangan mengunggah centroid z-score ke kolom centroid unit asli. Jarak ke centroid dihitung ulang dari fitur tersebut; metrik evaluasi opsional hanya diisi bila disertakan pada berkas. Metrik proses yang berbeda pada dua baris ditolak, bukan dirata-ratakan tanpa definisi.

## Rekomendasi

Gunakan hasil klaster + prediksi dari proses yang sama, dan snapshot stok terbaru saat rekomendasi dibuat. Tanpa prediksi atau snapshot, tidak ada rekomendasi untuk produk tersebut.

RESTOCK jika `max(0, ceil(prediksi + stok_minimum − stok)) > 0` dan (klaster Sangat Laris atau stok≤minimum). Prioritas Tinggi bila stok kritis dan stok<prediksi; Sedang bila stok<prediksi; lainnya Rendah.

Jika aturan RESTOCK tidak terpenuhi, PROMOSI dipertimbangkan saat stok>0 dan (klaster Kurang atau stok>2×(prediksi+minimum)). Prioritas Tinggi untuk kondisi stok>2×(prediksi+minimum); Sedang untuk klaster Kurang; lainnya Rendah. Tindakan yang disarankan: tinjau bundling/promosi terbatas dengan evaluasi margin, tanpa jaminan keuntungan.

Rekomendasi merupakan catatan keputusan, bukan perintah pembelian/penjualan. Menandai selesai tidak mengubah stok. Satu proses menghasilkan satu set idempoten. Hash isi produk, transaksi, dan riwayat stok mendeteksi perubahan sejak analisis; pembuatan rekomendasi dari analisis kedaluwarsa ditolak. Jalankan proses baru untuk memperbarui hasil. Riwayat hasil lama tetap dipertahankan.

Harga beli opsional belum cukup mendefinisikan ROI promosi, karena biaya promosi serta skenario pembanding belum tersedia. UI selalu menyatakan **ROI belum dapat dihitung**. Tidak ada nilai ROI/kepercayaan palsu.
