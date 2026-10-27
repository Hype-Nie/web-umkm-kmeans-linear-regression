<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\HasilAnalisisKMeans;
use App\Models\HasilPrediksiLinearRegression;
use App\Models\ProsesAnalisis;
use App\Models\StokBarang;
use App\Models\TransaksiPenjualan;
use App\Models\User;
use App\Services\AnalysisService;
use App\Services\RecommendationService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UdZufriRealDataSeeder extends Seeder
{
    public const EMAIL = 'admin@admin.com';
    public const USERNAME = 'admin_zufri';
    public const PASSWORD = 'password';
    public const OWNER = 'Halijah Yuhyi Pardede';
    public const STORE = 'UD. Zufri';

    public function run(): void
    {
        $csvPath = resource_path('transaksi_gabungan.csv');
        if (! is_readable($csvPath)) {
            throw new RuntimeException(
                'Berkas data transaksi tidak ditemukan atau tidak dapat dibaca: '.$csvPath
            );
        }

        $clusterCsvPath = base_path('finals/hasil_klaster_produk.csv');

        $this->command?->info('Menyiapkan akun pemilik UD. Zufri...');
        $user = User::firstOrCreate(
            ['email' => self::EMAIL],
            [
                'username' => self::USERNAME,
                'password' => Hash::make(self::PASSWORD),
                'nama_pemilik' => self::OWNER,
                'nama_toko' => self::STORE,
            ]
        );

        $this->command?->info('Membaca data RapidMiner klaster produk...');
        $clusterMap = [];
        if (is_readable($clusterCsvPath)) {
            $cfp = fopen($clusterCsvPath, 'r');
            fgetcsv($cfp, 0, ';'); // skip header
            while (($cRow = fgetcsv($cfp, 0, ';')) !== false) {
                if (count($cRow) >= 5) {
                    $clusterMap[trim($cRow[3])] = [
                        'z_terjual' => (float) $cRow[0],
                        'z_frekuensi' => (float) $cRow[1],
                        'z_omset' => (float) $cRow[2],
                        'cluster' => trim($cRow[4]),
                    ];
                }
            }
            fclose($cfp);
        }

        $this->command?->info('Membaca dan memproses 9.037 baris transaksi asli...');
        $fp = fopen($csvPath, 'r');
        if ($fp === false) {
            throw new RuntimeException('Berkas data transaksi tidak dapat dibuka: '.$csvPath);
        }
        $headers = fgetcsv($fp);
        // Clean BOM if present
        $headers[0] = preg_replace('/[\x{FEFF}]/u', '', $headers[0]);
        $headerMap = array_flip($headers);

        $productsData = [];
        $rawTransactions = [];

        while (($row = fgetcsv($fp)) !== false) {
            if (count($row) < count($headers)) {
                continue;
            }
            $kodeProd = trim($row[$headerMap['id_produk']]);
            $namaProd = trim($row[$headerMap['nama_produk']]);
            $kategori = trim($row[$headerMap['kategori']]);
            $hargaJual = (float) $row[$headerMap['harga_jual']];
            $qty = (int) $row[$headerMap['jumlah_terjual']];
            $nilaiPenjualan = (float) $row[$headerMap['nilai_penjualan']];
            $idTrx = trim($row[$headerMap['id_transaksi']]);
            $tglRaw = trim($row[$headerMap['tanggal']]);

            if (! isset($productsData[$kodeProd])) {
                $productsData[$kodeProd] = [
                    'kode_barang' => $kodeProd,
                    'nama_barang' => $namaProd,
                    'kategori' => $kategori,
                    'harga_satuan' => $hargaJual,
                    'harga_beli' => round($hargaJual * 0.85, 2),
                    'total_terjual' => 0,
                    'frekuensi' => 0,
                    'total_omset' => 0,
                ];
            }
            if ($hargaJual > $productsData[$kodeProd]['harga_satuan']) {
                $productsData[$kodeProd]['harga_satuan'] = $hargaJual;
                $productsData[$kodeProd]['harga_beli'] = round($hargaJual * 0.85, 2);
            }
            $productsData[$kodeProd]['total_terjual'] += $qty;
            $productsData[$kodeProd]['frekuensi'] += 1;
            $productsData[$kodeProd]['total_omset'] += $nilaiPenjualan;

            $rawTransactions[] = [
                'kode_barang' => $kodeProd,
                'kode_transaksi' => $idTrx,
                'tanggal_raw' => $tglRaw,
                'jumlah_terjual' => $qty,
                'harga_satuan_jual' => $hargaJual,
                'total_pendapatan' => $nilaiPenjualan,
            ];
        }
        fclose($fp);

        $this->command?->info("Menyimpan 52 produk master...");
        $dbProducts = [];
        foreach ($productsData as $kode => $pInfo) {
            $cl = $clusterMap[$pInfo['nama_barang']]['cluster'] ?? 'cluster_2';
            $stokMin = match ($cl) {
                'cluster_0' => 100,
                'cluster_1' => 50,
                default => 15,
            };

            $product = Barang::firstOrCreate(
                ['id_user' => $user->id_user, 'kode_barang' => $kode],
                [
                    'nama_barang' => $pInfo['nama_barang'],
                    'kategori' => $pInfo['kategori'],
                    'harga_satuan' => $pInfo['harga_satuan'],
                    'harga_beli' => $pInfo['harga_beli'],
                    'stok_minimum' => $stokMin,
                ]
            );
            $dbProducts[$kode] = $product;

            // Snapshot stok terkini
            $sisaStok = match ($cl) {
                'cluster_0' => 45,  // Fast moving kritis -> butuh restok tinggi
                'cluster_1' => 55,  // Medium moving pas
                default => 120,     // Slow moving berlebih -> rekomendasi promo
            };

            StokBarang::firstOrCreate(
                [
                    'id_barang' => $product->id_barang,
                    'tanggal_update' => '2026-06-30 23:59:59',
                ],
                [
                    'stok_sebelum' => null,
                    'perubahan_stok' => null,
                    'sisa_stok' => $sisaStok,
                    'sumber_data' => 'penyesuaian',
                    'keterangan' => 'Snapshot posisi stok akhir penelitian UD. Zufri (Juni 2026)',
                ]
            );
        }

        $this->command?->info("Menyimpan " . count($rawTransactions) . " transaksi penjualan...");
        $trxChunks = array_chunk($rawTransactions, 500);
        foreach ($trxChunks as $chunkIndex => $chunk) {
            $insertBatch = [];
            foreach ($chunk as $t) {
                $p = $dbProducts[$t['kode_barang']] ?? null;
                if (! $p) {
                    continue;
                }
                $tglParsed = Carbon::parse($t['tanggal_raw'])->format('Y-m-d H:i:s');
                $fingerprint = hash('sha256', "zufri|{$t['kode_transaksi']}|{$t['kode_barang']}|{$tglParsed}|{$t['jumlah_terjual']}|{$t['total_pendapatan']}");

                $insertBatch[] = [
                    'id_barang' => $p->id_barang,
                    'kode_transaksi' => $t['kode_transaksi'],
                    'tanggal_transaksi' => $tglParsed,
                    'jumlah_terjual' => $t['jumlah_terjual'],
                    'harga_satuan_jual' => $t['harga_satuan_jual'],
                    'total_pendapatan' => $t['total_pendapatan'],
                    'sumber_data' => 'impor',
                    'fingerprint_baris' => $fingerprint,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            TransaksiPenjualan::insertOrIgnore($insertBatch);
        }

        $this->command?->info("Menghapus riwayat analisis lama UD. Zufri...");
        $user->proses()->delete();

        $this->command?->info("Menjalankan pipeline terintegrasi SPK (K-Means & Linear Regression)...");
        $analysisService = app(AnalysisService::class);
        $process = $analysisService->run($user, '2024-10-01', '2026-06-30', true);

        if ($process->status === 'berhasil') {
            $recCount = $process->rekomendasi()->count();
            $this->command?->info("Pipeline SPK sukses! {$process->klaster()->count()} produk terklaster (Silhouette: {$process->silhouette_coefficient}), {$process->prediksi()->count()} peramalan, dan {$recCount} rekomendasi keputusan dibuat.");
        } else {
            $this->command?->warn("Catatan proses: " . $process->catatan);
        }

        $this->command?->info("SELESAI! Seluruh alur data UD. Zufri telah siap dengan alur tunggal terpadu.");
    }
}
