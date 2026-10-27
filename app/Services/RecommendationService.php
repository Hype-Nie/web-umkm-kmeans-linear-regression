<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\HasilAnalisisKMeans;
use App\Models\ProsesAnalisis;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    public function generate(ProsesAnalisis $process): int
    {
        return DB::transaction(function () use ($process) {
            $process = ProsesAnalisis::lockForUpdate()->findOrFail($process->id_proses);
            if ($process->status !== 'berhasil') {
                return 0;
            }

            $existing = $process->rekomendasi()->get()->keyBy('id_barang');
            $clusters = $process->klaster()->with('barang.stokTerakhir')->get();
            $forecasts = $process->prediksi()->get()->keyBy('id_barang');
            $meta = json_decode($process->catatan ?? '', true) ?: [];
            $evaluations = $meta['evaluasi_per_produk'] ?? [];

            $count = 0;
            foreach ($clusters as $c) {
                $p = $c->barang;
                $stock = $p->stokTerakhir?->sisa_stok;
                $forecast = $forecasts->get($p->id_barang);
                $prediction = $forecast?->angka_ramalan_stok;
                if ($stock === null || $prediction === null) {
                    continue;
                }

                $periode = $forecast->periode_prediksi->translatedFormat('F Y');
                $slope = (float) ($evaluations[$p->kode_barang]['b'] ?? 0.0);
                $restock = max(0, $prediction + $p->stok_minimum - $stock);
                $base = [
                    'id_barang' => $p->id_barang,
                    'tanggal_rekomendasi' => now(),
                    'stok_saat_analisis' => $stock,
                    'ramalan_kebutuhan' => $prediction,
                ];

                if ($restock > 0 && ($c->label_klaster === 'Sangat Laris' || $stock <= $p->stok_minimum)) {
                    $priority = $stock <= $p->stok_minimum && $stock < $prediction ? 'Tinggi' : ($stock < $prediction ? 'Sedang' : 'Rendah');
                    $deskripsi = $this->buildRestockDescription($p, $c, (int) $stock, (int) $prediction, (int) $restock, $priority, $periode, $slope);
                    $payload = $base + [
                        'jenis_rekomendasi' => 'restock',
                        'prioritas' => $priority,
                        'judul' => 'Restock '.$p->nama_barang,
                        'deskripsi' => $deskripsi,
                        'jumlah_restock_disarankan' => $restock,
                    ];

                    if ($existing->has($p->id_barang)) {
                        $existing->get($p->id_barang)->update(['deskripsi' => $deskripsi, 'prioritas' => $priority, 'jumlah_restock_disarankan' => $restock]);
                    } else {
                        $process->rekomendasi()->create($payload);
                    }
                    $count++;
                } elseif ($stock > 0 && ($c->label_klaster === 'Kurang' || $stock > 2 * ($prediction + $p->stok_minimum))) {
                    $excess = max(0, $stock - $prediction - $p->stok_minimum);
                    $priority = $stock > 2 * ($prediction + $p->stok_minimum) ? 'Tinggi' : ($c->label_klaster === 'Kurang' ? 'Sedang' : 'Rendah');
                    $deskripsi = $this->buildPromoDescription($p, $c, (int) $stock, (int) $prediction, (int) $excess, $priority, $periode, $slope);
                    $payload = $base + [
                        'jenis_rekomendasi' => 'promosi',
                        'prioritas' => $priority,
                        'judul' => 'Evaluasi promosi '.$p->nama_barang,
                        'deskripsi' => $deskripsi,
                        'jumlah_restock_disarankan' => 0,
                    ];

                    if ($existing->has($p->id_barang)) {
                        $existing->get($p->id_barang)->update(['deskripsi' => $deskripsi, 'prioritas' => $priority, 'jumlah_restock_disarankan' => 0]);
                    } else {
                        $process->rekomendasi()->create($payload);
                    }
                    $count++;
                }
            }

            return $existing->isNotEmpty() ? $existing->count() : $count;
        });
    }

    private function buildRestockDescription(
        Barang $p,
        HasilAnalisisKMeans $c,
        int $stock,
        int $prediction,
        int $restock,
        string $priority,
        string $periode,
        float $slope
    ): string {
        $terjual = number_format((int) $c->fitur_jumlah_terjual, 0, ',', '.');
        $frek = number_format((int) $c->fitur_frekuensi_pembelian, 0, ',', '.');
        $trendText = $slope > 0.3
            ? 'tren permintaan naik (+'.number_format($slope, 1, ',', '.').' unit/bln)'
            : ($slope < -0.3 ? 'tren permintaan melandai ('.number_format($slope, 1, ',', '.').' unit/bln)' : 'pola permintaan stabil');

        if ($priority === 'Tinggi') {
            if ($stock === 0) {
                return "Stok {$p->nama_barang} saat ini habis (0 unit), sedangkan proyeksi permintaan {$periode} mencapai {$prediction} unit dengan {$trendText}. Segera lakukan pengadaan darurat sebanyak {$restock} unit ke pemasok utama untuk memenuhi kebutuhan pelanggan dan memulihkan stok pengaman ({$p->stok_minimum} unit).";
            }

            return "Posisi stok {$p->nama_barang} berada di bawah batas aman ({$stock} unit dari minimum {$p->stok_minimum} unit) dengan perputaran klaster {$c->label_klaster} ({$terjual} unit terjual dalam {$frek} transaksi, {$trendText}). Segera lakukan pemesanan prioritas sebanyak {$restock} unit guna menutup proyeksi kebutuhan {$prediction} unit pada {$periode} dan mencegah kekosongan barang.";
        }

        if ($priority === 'Sedang') {
            return "Persediaan {$p->nama_barang} saat ini ({$stock} unit) masih di atas ambang minimum ({$p->stok_minimum} unit), namun belum mencukupi proyeksi penjualan {$periode} sebesar {$prediction} unit ({$trendText}). Jadwalkan pengadaan tambahan sebanyak {$restock} unit pada siklus belanja terdekat agar stok pengaman toko tidak tergerus.";
        }

        return "Stok {$p->nama_barang} ({$stock} unit) masih mencukupi estimasi permintaan {$periode} ({$prediction} unit), namun disarankan menambah {$restock} unit pada pemesanan reguler berikutnya untuk menjaga buffer stok minimum ({$p->stok_minimum} unit) tetap optimal.";
    }

    private function buildPromoDescription(
        Barang $p,
        HasilAnalisisKMeans $c,
        int $stock,
        int $prediction,
        int $excess,
        string $priority,
        string $periode,
        float $slope
    ): string {
        $terjual = number_format((int) $c->fitur_jumlah_terjual, 0, ',', '.');
        $frek = number_format((int) $c->fitur_frekuensi_pembelian, 0, ',', '.');
        $kat = mb_strtolower($p->kategori ?? '');
        $nama = mb_strtolower($p->nama_barang);

        if (str_contains($kat, 'minum') || str_contains($nama, 'susu') || str_contains($nama, 'kopi') || str_contains($nama, 'teh') || str_contains($nama, 'sirup')) {
            $tactic = 'terapkan promo beli 2 lebih hemat (multi-buy) atau paket kombo bersama camilan, serta pindahkan pajangan ke dekat area kasir';
        } elseif (str_contains($kat, 'sembako') || str_contains($nama, 'beras') || str_contains($nama, 'minyak') || str_contains($nama, 'gula') || str_contains($nama, 'telur') || str_contains($nama, 'tepung')) {
            $tactic = 'susun paket sembako hemat (bundling) bersama komoditas Sangat Laris atau berikan potongan harga grosir untuk pembelian jumlah banyak';
        } elseif (str_contains($kat, 'makan') || str_contains($kat, 'instan') || str_contains($kat, 'snack') || str_contains($nama, 'mi ') || str_contains($nama, 'indomie') || str_contains($nama, 'biskuit') || str_contains($nama, 'oreo') || str_contains($nama, 'saus') || str_contains($nama, 'garam')) {
            $tactic = 'jalankan program tebus murah dengan syarat belanja minimum, prioritaskan pengeluaran barang sesuai urutan kedaluwarsa (FEFO), dan gabungkan dalam paket hemat keluarga';
        } elseif (str_contains($kat, 'rumah') || str_contains($nama, 'sabun') || str_contains($nama, 'shampoo') || str_contains($nama, 'deterjen') || str_contains($nama, 'rinso')) {
            $tactic = 'tawarkan diskon paket kebutuhan rumah tangga (family pack) atau promo silang (cross-selling) saat pelanggan membeli kebutuhan pokok';
        } else {
            $tactic = 'terapkan promosi diskon terbatas atau penjualan paket bundling bersama produk utama yang memiliki frekuensi transaksi tinggi';
        }

        if ($priority === 'Tinggi') {
            if ($prediction === 0) {
                return "Persediaan {$p->nama_barang} menumpuk sebanyak {$stock} unit (kelebihan {$excess} unit di atas stok aman {$p->stok_minimum} unit) dengan proyeksi permintaan {$periode} yang stagnan (historis {$terjual} unit dari {$frek} transaksi). Hentikan sementara pemesanan ulang (freeze order) ke pemasok dan {$tactic} untuk mencairkan modal kerja yang tertahan.";
            }

            return "Stok {$p->nama_barang} saat ini ({$stock} unit) mengalami kelebihan persediaan sebesar {$excess} unit dibandingkan proyeksi serapan {$periode} ({$prediction} unit) dan cadangan minimum ({$p->stok_minimum} unit). Tunda jadwal pengadaan baru pada bulan ini dan {$tactic} agar perputaran stok kembali sehat.";
        }

        if ($priority === 'Sedang') {
            return "Produk {$p->nama_barang} tergolong perputaran lambat (klaster {$c->label_klaster}, {$terjual} unit terjual dalam {$frek} transaksi) dengan sisa stok {$stock} unit dan estimasi kebutuhan {$periode} sebesar {$prediction} unit. Evaluasi margin lalu {$tactic}; batasi pula kuota pembelian berikutnya maksimal sesuai serapan bulanan.";
        }

        return "Stok {$p->nama_barang} ({$stock} unit) memiliki kelebihan ringan sebesar {$excess} unit di atas estimasi kebutuhan {$periode} ({$prediction} unit) dan stok minimum ({$p->stok_minimum} unit). Pertahankan harga jual normal sambil mengoptimalkan posisi display produk, dan sesuaikan volume pesanan periode depan bila tren penjualan menurun.";
    }
}
