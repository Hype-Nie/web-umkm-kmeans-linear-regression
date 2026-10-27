<?php

namespace App\Services;

use App\Models\ProsesAnalisis;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DomainException;
use Illuminate\Support\Facades\DB;

class AnalysisService
{
    public function __construct(private KMeansService $kmeans, private RegressionService $regression) {}

    public function run(User $user, string $start, string $end, bool $complete): ProsesAnalisis
    {
        $process = $user->proses()->create(['tanggal_mulai_data' => $start, 'tanggal_akhir_data' => $end, 'sumber_proses' => 'backend_php', 'status' => 'diproses']);
        try {
            DB::transaction(function () use ($user, $start, $end, $complete, $process) {
                $products = $user->barang()->with(['transaksi' => fn ($q) => $q->periode($start, $end)])->orderBy('id_barang')->get();
                if (! $products->contains(fn ($p) => $p->transaksi->isNotEmpty())) {
                    throw new DomainException('Belum ada transaksi pada periode tersebut.');
                }
                $features = [];
                foreach ($products as $p) {
                    $trx = $p->transaksi;
                    // Distinct purchase events: receipt code when present; timestamp otherwise.
                    $frequency = $trx->map(fn ($t) => $t->tanggal_transaksi->format('Y-m-d').'|'.($t->kode_transaksi ?: $t->tanggal_transaksi->format('H:i:s')))->unique()->count();
                    $omset = (float) $trx->sum('total_pendapatan');
                    $totalUnits = (int) $trx->sum('jumlah_terjual');
                    $features[$p->id_barang] = [$totalUnits, $frequency, $omset];
                    if ($totalUnits > 4294967295) {
                        throw new DomainException('Total unit produk '.$p->kode_barang.' melampaui kapasitas fitur pada SQL. Gunakan periode lebih pendek.');
                    }
                }
                $notes = [];
                $cluster = null;
                try {
                    $cluster = $this->kmeans->calculate($features);
                } catch (DomainException $e) {
                    $notes[] = 'K-Means belum tersedia: '.$e->getMessage();
                }
                $evaluations = [];
                $monthStart = Carbon::parse($start)->startOfMonth();
                if (Carbon::parse($start)->day !== 1) {
                    $monthStart->addMonth();
                }
                $monthEnd = Carbon::parse($end)->startOfMonth();
                if (Carbon::parse($end)->isLastOfMonth() && Carbon::parse($end)->endOfDay()->lt(now())) {
                    $monthEnd->addMonth();
                }
                // Exclude the running calendar month, even if user supplies a future end date.
                if ($monthEnd->gt(now()->startOfMonth())) {
                    $monthEnd = now()->startOfMonth();
                }
                $months = [];
                if ($monthStart->lt($monthEnd)) {
                    foreach (CarbonPeriod::create($monthStart, '1 month', $monthEnd->copy()->subMonth()) as $month) {
                        $months[] = $month->format('Y-m');
                    }
                }
                foreach ($products as $i => $p) {
                    if ($cluster !== null) {
                        $c = $cluster['assignments'][$p->id_barang];
                        $process->klaster()->create(['id_barang' => $p->id_barang, 'tanggal_analisis' => now(), 'nomor_klaster' => $c, 'label_klaster' => $cluster['labels'][$c], 'centroid_jumlah_terjual' => $cluster['centroids'][$c][0], 'centroid_frekuensi_pembelian' => $cluster['centroids'][$c][1], 'fitur_jumlah_terjual' => $features[$p->id_barang][0], 'fitur_frekuensi_pembelian' => $features[$p->id_barang][1], 'jarak_ke_centroid' => $cluster['distances'][$p->id_barang]]);
                    }
                    $observed = $p->transaksi->groupBy(fn ($t) => $t->tanggal_transaksi->format('Y-m'))->map(fn ($g) => (int) $g->sum('jumlah_terjual'))->all();
                    $series = [];
                    foreach ($months as $month) {
                        if (! array_key_exists($month, $observed) && ! $complete) {
                            $series = [];
                            break;
                        }
                        $series[$month] = $observed[$month] ?? 0;
                    }
                    if (count($series) < 3) {
                        $notes[] = $p->kode_barang.': Data tidak cukup (minimal 3 bulan lengkap, bulan kosong belum dikonfirmasi nol).';

                        continue;
                    }
                    $forecast = $this->regression->forecast($series);
                    if ($forecast['units'] > 2147483647 || abs($forecast['raw']) >= 100000000000) {
                        throw new DomainException('Prediksi melampaui batas numerik database, periksa data masukan.');
                    }
                    $process->prediksi()->create(['id_barang' => $p->id_barang, 'tanggal_analisis' => now(), 'periode_prediksi' => $monthEnd->toDateString(), 'prediksi_penjualan' => number_format($forecast['raw'], 4, '.', ''), 'angka_ramalan_stok' => $forecast['units']]);
                    $evaluations[$p->kode_barang] = array_merge($forecast, ['months' => array_keys($series), 'historical' => array_values($series)]);
                }
                if ($cluster === null && ! $evaluations) {
                    throw new DomainException(implode(' ', $notes));
                }
                // Per-product validation metrics are retained; no misleading pooled R²/accuracy.
                $process->update(['status' => 'berhasil', 'silhouette_coefficient' => $cluster['silhouette'] ?? null, 'catatan' => json_encode(['status_kmeans' => $cluster !== null ? 'berhasil' : 'data_tidak_cukup', 'status_regresi' => $evaluations ? 'berhasil' : 'data_tidak_cukup', 'standardisasi' => 'z-score populasi', 'inisialisasi' => 'deterministic farthest-first', 'iterasi' => $cluster['iterations'] ?? null, 'means' => $cluster['mean'] ?? null, 'std' => $cluster['sd'] ?? null, 'bulan_kosong_nol' => $complete, 'evaluasi_per_produk' => $evaluations, 'peringatan' => $notes, 'data_signature' => $this->signature($user)], JSON_UNESCAPED_UNICODE)]);
                app(RecommendationService::class)->generate($process);
            });
        } catch (\Throwable $e) {
            $process->update(['status' => 'gagal', 'catatan' => $e instanceof DomainException ? $e->getMessage() : 'Pemrosesan gagal. Periksa data atau log aplikasi.']);
            if (! $e instanceof DomainException) {
                report($e);
            }
        }

        return $process->fresh();
    }

    public function signature(User $user): string
    {
        $hash = hash_init('sha256');
        foreach ($user->barang()->orderBy('id_barang')->cursor() as $p) {
            hash_update($hash, json_encode($p->getAttributes()));
        }
        foreach (DB::table('transaksi_penjualan as t')->join('barang as b', 'b.id_barang', '=', 't.id_barang')->where('b.id_user', $user->id_user)->select('t.*')->orderBy('t.id_transaksi')->cursor() as $t) {
            hash_update($hash, json_encode($t));
        }
        foreach (DB::table('stok_barang as s')->join('barang as b', 'b.id_barang', '=', 's.id_barang')->where('b.id_user', $user->id_user)->select('s.*')->orderBy('s.id_stok')->cursor() as $s) {
            hash_update($hash, json_encode($s));
        }

        return hash_final($hash);
    }
}
