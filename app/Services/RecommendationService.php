<?php

namespace App\Services;

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
            if ($process->rekomendasi()->exists()) {
                return $process->rekomendasi()->count();
            }
            $clusters = $process->klaster()->with('barang.stokTerakhir')->get();
            $forecasts = $process->prediksi()->get()->keyBy('id_barang');
            $count = 0;
            foreach ($clusters as $c) {
                $p = $c->barang;
                $stock = $p->stokTerakhir?->sisa_stok;
                $prediction = $forecasts->get($p->id_barang)?->angka_ramalan_stok;
                if ($stock === null || $prediction === null) {
                    continue;
                }
                $restock = max(0, $prediction + $p->stok_minimum - $stock);
                $base = ['id_barang' => $p->id_barang, 'tanggal_rekomendasi' => now(), 'stok_saat_analisis' => $stock, 'ramalan_kebutuhan' => $prediction];
                if ($restock > 0 && ($c->label_klaster === 'Sangat Laris' || $stock <= $p->stok_minimum)) {
                    $priority = $stock <= $p->stok_minimum && $stock < $prediction ? 'Tinggi' : ($stock < $prediction ? 'Sedang' : 'Rendah');
                    $process->rekomendasi()->create($base + ['jenis_rekomendasi' => 'restock', 'prioritas' => $priority, 'judul' => 'Restock '.$p->nama_barang, 'deskripsi' => "Klaster {$c->label_klaster}. max(0, ceil(prediksi {$prediction} + stok minimum {$p->stok_minimum} - stok terkini {$stock})) = {$restock} unit. Prioritas Tinggi: stok kritis dan di bawah prediksi; Sedang: stok di bawah prediksi; selainnya Rendah. Periode kebutuhan ".$forecasts[$p->id_barang]->periode_prediksi->format('m/Y').'.', 'jumlah_restock_disarankan' => $restock]);
                    $count++;
                } elseif ($stock > 0 && ($c->label_klaster === 'Kurang' || $stock > 2 * ($prediction + $p->stok_minimum))) {
                    $excess = max(0, $stock - $prediction - $p->stok_minimum);
                    $priority = $stock > 2 * ($prediction + $p->stok_minimum) ? 'Tinggi' : ($c->label_klaster === 'Kurang' ? 'Sedang' : 'Rendah');
                    $process->rekomendasi()->create($base + ['jenis_rekomendasi' => 'promosi', 'prioritas' => $priority, 'judul' => 'Evaluasi promosi '.$p->nama_barang, 'deskripsi' => "Klaster {$c->label_klaster}, stok {$stock}, kebutuhan {$prediction}, stok minimum {$p->stok_minimum}; kelebihan relatif {$excess} unit. Tinjau bundling atau promosi terbatas setelah memeriksa margin. Tinggi bila stok > 2 × (prediksi + minimum); Sedang untuk klaster Kurang; lainnya Rendah. ROI belum dapat dihitung: biaya dan asumsi promosi belum tersedia.", 'jumlah_restock_disarankan' => 0]);
                    $count++;
                }
            }

            return $count;
        });
    }
}
