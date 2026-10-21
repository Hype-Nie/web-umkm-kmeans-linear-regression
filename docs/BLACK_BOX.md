# Daftar pengujian Black-Box

Gunakan akun uji terpisah, database `db_spk_ud_zufri_test`, dan berkas pengujian yang diberi label jelas. Jangan memasukkan data buatan ke database riset. Pengujian otomatis memakai data sementara dalam transaksi yang selalu di-rollback. Beberapa pemeriksaan browser/SMTP memerlukan pelaksanaan manual seperti ditandai.

| ID | Skenario / input | Hasil yang diharapkan |
| --- | --- | --- |
| A01 | Daftar dengan seluruh field, password valid, persetujuan | Akun tersimpan dengan id_user, password hash, masuk dashboard |
| A02 | Username/email sudah dipakai | Ditolak dengan pesan field, akun baru tidak tersimpan |
| A03 | Password pendek/tanpa huruf/angka, konfirmasi berbeda, syarat kosong | Ditolak server; field selain password tetap bisa diperbaiki |
| A04 | Login email atau username benar | Sesi diregenerasi, redirect dashboard/halaman tujuan |
| A05 | Password salah | Pesan umum, sesi tidak diautentikasi |
| A06 | Banyak percobaan login salah | HTTP 429 sesuai throttle per identitas/IP |
| A07 | Ingat saya lalu buka ulang browser | Cookie remember memungkinkan login kembali; verifikasi browser manual |
| A08 | Logout lalu akses dashboard | Sesi dibatalkan, route terlindungi redirect login |
| A09 | Lupa password tanpa SMTP | Informasi konfigurasi jujur, tidak mengklaim email terkirim |
| A10 | SMTP valid dan email terdaftar | Email berisi tautan token benar; uji pengiriman ke mailbox manual |
| A11 | Token reset valid, kedaluwarsa, salah, atau dipakai dua kali | Valid mengganti hash; lainnya ditolak |
| A12 | POST tanpa token CSRF pada HTTP normal | HTTP 419, data tidak berubah |
| B01 | Dashboard akun kosong | KPI dari database kosong, grafik empty state, panduan upload |
| B02 | Filter rentang / hari-minggu-bulan | KPI penjualan dan agregasi mengikuti transaksi pemilik |
| B03 | Rentang terbalik / tanggal salah | Pesan validasi; tidak ada query data salah |
| B04 | Setelah simpan transaksi/stok, refresh | KPI dan tanggal pembaruan mengikuti data tersimpan |
| B05 | Grafik dan tooltip / pencarian transaksi / pagination | Berfungsi; pemeriksaan tooltip dan responsif manual |
| B06 | Produk tanpa snapshot | Ditandai belum tercatat, tidak menjadi stok nol palsu |
| C01 | Tambah, edit, cari, sortir, filter, pagination barang | Perubahan/pencarian sesuai query pemilik |
| C02 | Kode barang duplikat pada akun sama | Ditolak; kode sama boleh pada akun berbeda |
| C03 | Harga negatif, terlalu presisi, jumlah tidak bulat | Ditolak server |
| C04 | Hapus barang tanpa riwayat | Berhasil setelah konfirmasi browser |
| C05 | Hapus barang dengan stok/transaksi/hasil model | Ditolak; riwayat tetap lengkap |
| C06 | Catat stok awal, restock, penyesuaian | Riwayat bertambah, posisi mengikuti snapshot terbaru |
| C07 | Snapshot dimasukkan tidak berurutan / tanggal sama | Posisi memakai tanggal_update DESC, id_stok DESC, bukan SUM |
| C08 | Stok awal kedua / restock tanpa stok awal / mutasi dibelakangkan | Ditolak dengan alasan |
| C09 | Transaksi historis dan total kiriman palsu | Total server jumlah×harga, stok tidak dikurangi |
| C10 | Penjualan operasional cukup stok | Transaksi dan pengurangan stok tersimpan atomik |
| C11 | Operasional stok kurang / bersamaan berebut stok | Stok tidak negatif; kegagalan membatalkan transaksi. Beban bersamaan manual |
| C12 | Edit/hapus historis | Transaksi diperbarui/dihapus tanpa mengubah snapshot |
| C13 | Edit/hapus transaksi operasional yang terhubung stok | Ditolak demi audit; koreksi melalui penyesuaian dan catatan historis |
| C14 | Memanipulasi id_barang/id_transaksi milik akun lain | HTTP 404, tidak membaca/mengubah data akun lain |
| D01 | CSV/XLSX valid: upload → mapping → preview → confirm | Baris tersimpan, status/jumlah benar |
| D02 | Header alias transaction_id / amount_value | Terpetakan ke kode_transaksi / total_pendapatan |
| D03 | Header berbeda lalu mapping manual | Validasi memakai kolom yang dipilih, bukan posisi asumsi |
| D04 | Baris kosong | Dilewati; tidak dianggap transaksi |
| D05 | Tanggal ISO, DD/MM/YYYY, serial Excel | Tanggal valid dinormalisasi; tanggal mustahil ditolak |
| D06 | Total tidak konsisten, angka negatif, jumlah pecahan | Error per baris; tidak tersimpan |
| D07 | Produk belum ada, nama+kode+kategori cukup | Produk baru dibuat milik pengunggah |
| D08 | Produk tidak ditemukan tanpa nama/kategori / nama ambigu | Error per baris, tidak memilih produk sembarang |
| D09 | File sama dengan nama lain / baris duplikat di file lain | Hash file / fingerprint baris mencegah transaksi ganda |
| D10 | Snapshot historis + transaksi pada baris gabungan | Stok akhir disimpan tepat nilainya, tanpa pengurangan kedua |
| D11 | Mode ketat dengan 1 baris invalid | Seluruh data tidak diimpor, proses gagal menyimpan error |
| D12 | Mode parsial dengan baris valid dan invalid | Valid tersimpan, invalid dilewati, status parsial dan detail error |
| D13 | Klik confirm dua kali / dua request bersamaan | Status dan lock mencegah impor ganda |
| D14 | Ekstensi lain, berkas rusak, >10 MB, >25.000 baris, XLSX terlalu besar saat diekstrak | Ditolak, tidak membocorkan stack trace |
| D15 | Unduh template semua jenis CSV/XLSX | File nyata dapat dibuka, header sesuai kontrak |
| D16 | Akses riwayat/preview milik orang lain | HTTP 404 |
| E01 | K<3 produk unik / semua fitur identik / tanpa transaksi | Proses gagal dengan alasan, tidak ada hasil model palsu |
| E02 | Data cukup dan variasi fitur | Tepat tiga kelompok nyata, setiap produk memiliki hasil |
| E03 | Urutan nomor klaster berbeda | Label tetap mengikuti centroid, bukan nomor |
| E04 | Scatter dan tabel centroid | Satu titik per produk, fitur/centroid/jumlah sesuai hasil tersimpan |
| E05 | N>K dengan singleton / jarak dapat diverifikasi | Silhouette matematis benar; singleton=0 |
| E06 | N=K / N>2.000 backend | Silhouette tidak diklaim tersedia |
| E07 | Seri bulanan [10,20,30,40,50] | a=0, b=10, prediksi 60; rolling MSE/RMSE/MAE=0, R²=1 |
| E08 | Seri [30,15,0] | Prediksi mentah −15, kebutuhan 0; R² belum tersedia bila hanya 1 titik validasi |
| E09 | Kurang dari tiga bulan lengkap / ada bulan kosong tak jelas | Data tidak cukup; tidak mengisi bulan nol diam-diam |
| E10 | Konfirmasi lengkap, bulan kosong | Kosong menjadi 0 berdasarkan asumsi eksplisit; tercatat dalam metadata |
| E11 | Bulan awal/akhir parsial atau bulan berjalan | Dikeluarkan dari regresi, periode prediksi jelas |
| E12 | Impor RapidMiner valid | Hasil nyata dari berkas tersimpan dengan sumber rapidminer |
| E13 | Impor centroid tidak konsisten, klaster kurang/lebih 3, produk asing | Semua hasil dibatalkan, proses gagal memberi alasan |
| E14 | Metrik opsional kosong | NULL / tidak tersedia; bukan akurasi palsu |
| E15 | Jalankan PHP | Sumber backend_php, tidak diberi label hasil RapidMiner |
| E16 | Edit transaksi/stok setelah analisis | Hasil ditandai kedaluwarsa; perlu proses baru |
| E17 | Buka proses akun lain | HTTP 404, hasil antar akun tidak bercampur |
| E18 | Hanya 1–2 produk tetapi seri bulanan cukup | Regresi tetap tersedia; K-Means memberi alasan belum cukup; tidak ada klaster palsu |
| F01 | Laris/kritis, stok tidak cukup | RESTOCK=max(0,ceil(prediksi+minimum−stok)), angka dan alasan ditampilkan |
| F02 | Kurang/kelebihan stok, stok>0 | PROMOSI dengan aturan transparan dan tanpa janji keuntungan |
| F03 | Kondisi prioritas Tinggi/Sedang/Rendah | Mengikuti aturan deterministik yang didokumentasikan |
| F04 | Tanpa snapshot/prediksi / tidak perlu tindakan | Tidak membuat rekomendasi palsu |
| F05 | Susun berulang untuk proses sama | Idempoten, jumlah tidak berlipat |
| F06 | Filter prioritas/produk/jenis, ubah status | Filter dan status tersimpan; stok tidak berubah otomatis |
| F07 | Biaya promosi tidak ada | ROI belum dapat dihitung |
| F08 | Manipulasi id_rekomendasi | HTTP 404 untuk pemilik berbeda |
| G01 | Preview semua jenis laporan/rentang | Nama toko, periode, tanggal WIB, tabel/total sesuai database |
| G02 | PDF | Byte awal %PDF-, dapat dibuka, isi sesuai preview |
| G03 | XLSX | Arsip Excel asli, sheet Ringkasan/Data, grafik penjualan bila ada |
| G04 | CSV | Kolom dan data dapat dibuka; string yang memicu formula dinetralkan |
| G05 | Cetak browser / zoom | Print preview menampilkan laporan; kontrol browser manual |
| G06 | Rentang prediksi | Filter tanggal pada periode_prediksi; bukan tanggal proses |
| G07 | Laporan stok historis | Snapshot terakhir sebelum/sama akhir periode, bukan jumlah semua snapshot |
| G08 | Tidak ada data | Laporan empty state, tanpa angka penelitian palsu |
| G09 | Ekspor akun A saat data akun B ada | Data B tidak muncul |

Suite otomatis berada di `tests/Unit` dan `tests/Feature`. Eksekusi dan hasil akhir dicatat pada `PENGUJIAN.md`. Checklist ini mencakup skenario manual tambahan; keberadaan baris dalam checklist bukan klaim telah menguji browser/SMTP/beban bersamaan secara otomatis.
