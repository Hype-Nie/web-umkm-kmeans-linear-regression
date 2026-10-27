<?php

namespace App\Services;

use App\Models\ProsesAnalisis;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class RapidMinerService
{
    public const HEADERS = ['kode_barang', 'tanggal_mulai_data', 'tanggal_akhir_data', 'nomor_klaster', 'fitur_jumlah_terjual', 'fitur_frekuensi_pembelian', 'centroid_jumlah_terjual', 'centroid_frekuensi_pembelian', 'jarak_ke_centroid', 'periode_prediksi', 'prediksi_penjualan', 'silhouette_coefficient', 'mse', 'rmse', 'mae', 'r_squared'];

    public function import(User $user, array $file, string $filename): ProsesAnalisis
    {
        $process = $user->proses()->create(['sumber_proses' => 'rapidminer', 'nama_file_hasil' => $filename, 'status' => 'diproses']);
        try {
            DB::transaction(function () use ($user, $file, $process) {
                $rows = [];
                $period = null;
                $centroids = [];
                $products = [];
                $seen = [];
                $metrics = [];
                foreach ($file['rows'] as $i => $raw) {
                    if (! array_filter($raw, fn ($v) => trim((string) $v) !== '')) {
                        continue;
                    }
                    $d = array_combine($file['headers'], array_pad(array_slice($raw, 0, count($file['headers'])), count($file['headers']), ''));
                    foreach (array_slice(self::HEADERS, 0, 8) as $required) {
                        if (! isset($d[$required]) || trim((string) $d[$required]) === '') {
                            throw new DomainException('Baris '.($i + 2).': kolom '.$required.' wajib diisi.');
                        }
                    }
                    $p = $user->barang()->where('kode_barang', $d['kode_barang'])->first();
                    if (! $p) {
                        throw new DomainException('Baris '.($i + 2).': produk tidak ditemukan pada akun ini.');
                    }
                    if (isset($seen[$p->id_barang])) {
                        throw new DomainException('Produk berulang pada hasil RapidMiner.');
                    }
                    $seen[$p->id_barang] = true;
                    foreach (['tanggal_mulai_data', 'tanggal_akhir_data'] as $f) {
                        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string) $d[$f]) || ! checkdate((int) substr($d[$f], 5, 2), (int) substr($d[$f], 8, 2), (int) substr($d[$f], 0, 4))) {
                            throw new DomainException('Periode harus YYYY-MM-DD.');
                        }
                    }
                    if ($d['tanggal_akhir_data'] < $d['tanggal_mulai_data'] || $d['tanggal_akhir_data'] > now()->toDateString()) {
                        throw new DomainException('Rentang data RapidMiner tidak valid.');
                    }
                    $current = [$d['tanggal_mulai_data'], $d['tanggal_akhir_data']];
                    if ($period !== null && $period !== $current) {
                        throw new DomainException('Semua baris harus memakai periode data yang sama.');
                    }
                    $period = $current;
                    foreach (['nomor_klaster', 'fitur_jumlah_terjual', 'fitur_frekuensi_pembelian'] as $f) {
                        if (! preg_match('/^\d+$/D', (string) $d[$f]) || $d[$f] > 2147483647) {
                            throw new DomainException($f.' harus bilangan bulat nonnegatif.');
                        }
                    }
                    $c = (int) $d['nomor_klaster'];
                    if ($c > 3) {
                        throw new DomainException('Nomor klaster harus 0–2 atau 1–3.');
                    }
                    foreach (['centroid_jumlah_terjual', 'centroid_frekuensi_pembelian'] as $f) {
                        if (! is_numeric($d[$f]) || ! is_finite((float) $d[$f]) || $d[$f] < 0) {
                            throw new DomainException('Centroid harus numerik nonnegatif.');
                        }
                    }
                    $center = [(float) $d['centroid_jumlah_terjual'], (float) $d['centroid_frekuensi_pembelian']];
                    if (isset($centroids[$c]) && $centroids[$c] !== $center) {
                        throw new DomainException('Centroid dalam satu klaster harus konsisten.');
                    }
                    $centroids[$c] = $center;
                    if (trim((string) ($d['prediksi_penjualan'] ?? '')) !== '') {
                        if (! is_numeric($d['prediksi_penjualan']) || ! is_finite((float) $d['prediksi_penjualan']) || abs((float) $d['prediksi_penjualan']) >= 100000000000 || ceil(max(0, (float) $d['prediksi_penjualan'])) > 2147483647) {
                            throw new DomainException('Prediksi tidak valid atau melebihi kapasitas.');
                        }
                        $expected = Carbon::parse($period[1])->addMonthNoOverflow()->startOfMonth()->toDateString();
                        if (($d['periode_prediksi'] ?? '') !== $expected) {
                            throw new DomainException('Periode prediksi harus tanggal 1 bulan setelah akhir data: '.$expected);
                        }
                    }
                    foreach (['silhouette_coefficient', 'mse', 'rmse', 'mae', 'r_squared'] as $metric) {
                        if (trim((string) ($d[$metric] ?? '')) !== '') {
                            $v = (float) $d[$metric];
                            if (! is_numeric($d[$metric]) || ! is_finite($v) || (in_array($metric, ['mse', 'rmse', 'mae']) && ($v < 0 || $v >= 1e14)) || ($metric === 'silhouette_coefficient' && abs($v) > 1) || ($metric === 'r_squared' && ($v > 1 || $v <= -100000))) {
                                throw new DomainException('Metrik '.$metric.' tidak valid.');
                            }
                            if (isset($metrics[$metric]) && $metrics[$metric] !== $v) {
                                throw new DomainException('Metrik tingkat proses harus konsisten pada semua baris.');
                            }
                            $metrics[$metric] = $v;
                        }
                    }
                    $rows[] = $d;
                    $products[] = $p;
                }
                if (count($rows) < 3 || count($centroids) !== 3 || ! (array_diff(array_keys($centroids), [0, 1, 2]) === [] || array_diff(array_keys($centroids), [1, 2, 3]) === [])) {
                    throw new DomainException('Hasil RapidMiner harus memuat tepat 3 klaster tidak kosong.');
                }
                if (count(array_unique(array_map('json_encode', $centroids))) !== 3) {
                    throw new DomainException('Centroid identik tidak mendukung tiga kelompok.');
                }
                $mean = [0, 0];
                $sd = [0, 0];
                foreach ([0, 1] as $j) {
                    $f = $j === 0 ? 'fitur_jumlah_terjual' : 'fitur_frekuensi_pembelian';
                    $values = array_map(fn ($r) => (float) $r[$f], $rows);
                    $mean[$j] = array_sum($values) / count($values);
                    $sd[$j] = sqrt(array_sum(array_map(fn ($v) => ($v - $mean[$j]) ** 2, $values)) / count($values)) ?: 1;
                }
                $order = array_keys($centroids);
                usort($order, fn ($a, $b) => (($centroids[$a][0] - $mean[0]) / $sd[0] + ($centroids[$a][1] - $mean[1]) / $sd[1]) <=> (($centroids[$b][0] - $mean[0]) / $sd[0] + ($centroids[$b][1] - $mean[1]) / $sd[1]));
                $labels = [$order[0] => 'Kurang', $order[1] => 'Cukup', $order[2] => 'Sangat Laris'];
                foreach ($rows as $i => $d) {
                    $p = $products[$i];
                    $c = (int) $d['nomor_klaster'];
                    $distance = sqrt((($d['fitur_jumlah_terjual'] - $centroids[$c][0]) / $sd[0]) ** 2 + (($d['fitur_frekuensi_pembelian'] - $centroids[$c][1]) / $sd[1]) ** 2);
                    $process->klaster()->create(['id_barang' => $p->id_barang, 'tanggal_analisis' => now(), 'nomor_klaster' => $c, 'label_klaster' => $labels[$c], 'centroid_jumlah_terjual' => $centroids[$c][0], 'centroid_frekuensi_pembelian' => $centroids[$c][1], 'fitur_jumlah_terjual' => $d['fitur_jumlah_terjual'], 'fitur_frekuensi_pembelian' => $d['fitur_frekuensi_pembelian'], 'jarak_ke_centroid' => $distance]);
                    if (trim((string) ($d['prediksi_penjualan'] ?? '')) !== '') {
                        $process->prediksi()->create(['id_barang' => $p->id_barang, 'tanggal_analisis' => now(), 'periode_prediksi' => $d['periode_prediksi'], 'prediksi_penjualan' => $d['prediksi_penjualan'], 'angka_ramalan_stok' => (int) ceil(max(0, (float) $d['prediksi_penjualan']))]);
                    }
                }
                $process->update($metrics + ['tanggal_mulai_data' => $period[0], 'tanggal_akhir_data' => $period[1], 'status' => 'berhasil', 'catatan' => json_encode(['asal' => 'Berkas hasil RapidMiner yang diunggah pengguna. Metrik diimpor sesuai berkas, pastikan evaluasi di RapidMiner memakai validasi temporal.', 'label' => 'Peringkat centroid fitur distandardisasi, bukan nomor klaster.', 'jarak' => 'Dihitung ulang dalam ruang z-score dari fitur yang diimpor.', 'data_signature' => app(AnalysisService::class)->signature($user)], JSON_UNESCAPED_UNICODE)]);
            });
        } catch (\Throwable $e) {
            $process->update(['status' => 'gagal', 'catatan' => $e instanceof DomainException ? $e->getMessage() : 'Berkas hasil tidak dapat diproses. Periksa format dan log aplikasi.']);
            if (! $e instanceof DomainException) {
                report($e);
            }
        }

        return $process->fresh();
    }
}
