<?php

namespace App\Services;

use App\Models\HasilAnalisisKMeans;
use App\Models\HasilPrediksiLinearRegression;
use App\Models\RekomendasiKeputusan;
use App\Models\TransaksiPenjualan;
use App\Models\User;
use App\Support\Money;

class ReportService
{
    public const TYPES = ['penjualan' => 'Ringkasan Penjualan', 'stok' => 'Posisi Stok', 'kmeans' => 'Analisis K-Means', 'prediksi' => 'Prediksi Linear Regression', 'rekomendasi' => 'Rekomendasi Keputusan'];

    public function build(User $user, string $type, string $start, string $end, bool $summary = true, bool $detail = true, bool $raw = false): array
    {
        $range = [$start.' 00:00:00', $end.' 23:59:59'];
        $owner = fn ($q) => $q->where('id_user', $user->id_user);
        $rows = [];
        $total = '0.00';
        $chart = [];
        if ($type === 'penjualan') {
            $headers = ['Kode Transaksi', 'Tanggal (WIB)', 'Produk', 'Jumlah', 'Harga Satuan', 'Pendapatan'];
            $items = TransaksiPenjualan::whereHas('barang', $owner)->periode($start, $end)->with('barang')->orderBy('tanggal_transaksi')->get();
            $diffDays = abs(\Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)));
            $aggregateMonthly = $diffDays > 60;
            foreach ($items as $i) {
                $rows[] = [
                    $i->kode_transaksi ?: '#'.$i->id_transaksi,
                    $i->tanggal_transaksi->format('d/m/Y H:i'),
                    $i->barang->nama_barang,
                    (int) $i->jumlah_terjual,
                    $i->harga_satuan_jual,
                    $i->total_pendapatan,
                ];
                $total = bcadd($total, $i->total_pendapatan, 2);
                $periodKey = $aggregateMonthly ? $i->tanggal_transaksi->format('m/Y') : $i->tanggal_transaksi->format('d/m');
                $chart[$periodKey] = bcadd($chart[$periodKey] ?? '0', $i->total_pendapatan, 2);
            }
            $total = Money::format($total);
        } elseif ($type === 'stok') {
            $headers = ['Kode', 'Produk', 'Kategori', 'Stok Minimum', 'Sisa Stok', 'Snapshot Terakhir (WIB)'];
            $products = $user->barang()->orderBy('nama_barang')->get();
            foreach ($products as $p) {
                $s = $p->stok()->where('tanggal_update', '<=', $range[1])->orderByDesc('tanggal_update')->orderByDesc('id_stok')->first();
                $rows[] = [$p->kode_barang, $p->nama_barang, $p->kategori ?: '—', (int) $p->stok_minimum, $s?->sisa_stok ?? 'Belum tercatat', $s?->tanggal_update->format('d/m/Y H:i') ?? '—'];
            }
            $total = 'Posisi stok produk terdaftar per ' . \Carbon\Carbon::parse($end)->translatedFormat('d F Y') . '.';
        } elseif ($type === 'kmeans') {
            $headers = ['Kode Barang', 'Nama Produk', 'Klaster', 'Total Unit', 'Frekuensi', 'Centroid Unit', 'Centroid Frekuensi', 'Silhouette'];
            $process = $user->proses()->where('status', 'berhasil')
                ->where(fn ($q) => $q->whereBetween('tanggal_mulai_data', [$start, $end])->orWhereBetween('tanggal_akhir_data', [$start, $end])->orWhereBetween('created_at', $range))
                ->latest('id_proses')
                ->first() ?? $user->proses()->where('status', 'berhasil')->latest('id_proses')->first();

            $items = $process ? $process->klaster()->with('barang')->orderBy('nomor_klaster')->get() : collect();
            if ($items->isEmpty()) {
                $items = HasilAnalisisKMeans::whereHas('proses', $owner)->with(['barang', 'proses'])->get();
            }
            foreach ($items as $i) {
                $rows[] = [
                    $i->barang->kode_barang,
                    $i->barang->nama_barang,
                    $i->label_klaster,
                    (int) $i->fitur_jumlah_terjual,
                    (int) $i->fitur_frekuensi_pembelian,
                    round((float) $i->centroid_jumlah_terjual, 2),
                    round((float) $i->centroid_frekuensi_pembelian, 2),
                    number_format((float) ($i->proses?->silhouette_coefficient ?? 0), 4),
                ];
            }
            $sil = $process?->silhouette_coefficient ? ' · Silhouette: ' . number_format($process->silhouette_coefficient, 4) : '';
            $total = count($rows) . ' produk terklaster (K=3)' . $sil . '.';
        } elseif ($type === 'prediksi') {
            $headers = ['Kode Barang', 'Nama Produk', 'Bulan Prediksi', 'Prediksi Linear Regression', 'Kebutuhan Stok Unit', 'Waktu Analisis (WIB)'];
            $process = $user->proses()->where('status', 'berhasil')
                ->where(fn ($q) => $q->whereBetween('tanggal_mulai_data', [$start, $end])->orWhereBetween('tanggal_akhir_data', [$start, $end])->orWhereBetween('created_at', $range))
                ->latest('id_proses')
                ->first() ?? $user->proses()->where('status', 'berhasil')->latest('id_proses')->first();

            $items = $process ? $process->prediksi()->with('barang')->get() : collect();
            if ($items->isEmpty()) {
                $items = HasilPrediksiLinearRegression::whereHas('proses', $owner)->with(['barang', 'proses'])->get();
            }
            foreach ($items as $i) {
                $rows[] = [
                    $i->barang->kode_barang,
                    $i->barang->nama_barang,
                    $i->periode_prediksi->format('m/Y'),
                    round((float) $i->prediksi_penjualan, 2),
                    (int) $i->angka_ramalan_stok,
                    $i->tanggal_analisis->format('d/m/Y H:i'),
                ];
            }
            $total = count($rows) . ' produk memiliki proyeksi peramalan stok Linear Regression.';
        } else {
            $headers = ['Kode', 'Produk', 'Jenis Strategi', 'Prioritas', 'Sisa Stok', 'Ramalan Kebutuhan', 'Saran Restock', 'Status', 'Rekomendasi Tindakan'];
            $process = $user->proses()->where('status', 'berhasil')
                ->where(fn ($q) => $q->whereBetween('tanggal_mulai_data', [$start, $end])->orWhereBetween('tanggal_akhir_data', [$start, $end])->orWhereBetween('created_at', $range))
                ->latest('id_proses')
                ->first() ?? $user->proses()->where('status', 'berhasil')->latest('id_proses')->first();

            $items = $process ? $process->rekomendasi()->with('barang')->orderByRaw("CASE WHEN prioritas='Tinggi' THEN 1 WHEN prioritas='Sedang' THEN 2 ELSE 3 END")->get() : collect();
            if ($items->isEmpty()) {
                $items = RekomendasiKeputusan::whereHas('proses', $owner)->with(['barang', 'proses'])->get();
            }
            foreach ($items as $i) {
                $rows[] = [
                    $i->barang->kode_barang,
                    $i->barang->nama_barang,
                    strtoupper($i->jenis_rekomendasi),
                    $i->prioritas,
                    (int) $i->stok_saat_analisis,
                    (int) $i->ramalan_kebutuhan,
                    (int) $i->jumlah_restock_disarankan,
                    ucfirst($i->status),
                    str_replace(';', ',', $i->deskripsi),
                ];
            }
            $total = count($rows) . ' rekomendasi keputusan persediaan.';
        }

        $rawHeaders = ['Kode Transaksi', 'Tanggal', 'Kode Barang', 'Nama Barang', 'Kategori', 'Jumlah Terjual', 'Harga Satuan Jual', 'Total Pendapatan', 'Sumber Data'];
        $rawRows = $raw ? TransaksiPenjualan::whereHas('barang', $owner)->periode($start, $end)->with('barang')->orderBy('tanggal_transaksi')->get()
            ->map(fn ($t) => [$t->kode_transaksi, $t->tanggal_transaksi->format('Y-m-d H:i:s'), $t->barang->kode_barang, $t->barang->nama_barang, $t->barang->kategori, (int) $t->jumlah_terjual, $t->harga_satuan_jual, $t->total_pendapatan, $t->sumber_data])->all() : [];

        return ['title' => self::TYPES[$type], 'type' => $type, 'headers' => $headers, 'rows' => $rows, 'total' => $total, 'chart' => $chart, 'start' => $start, 'end' => $end, 'shop' => $user->nama_toko, 'owner' => $user->nama_pemilik, 'generated' => now()->format('d/m/Y H:i').' WIB', 'summary' => $summary, 'detail' => $detail, 'raw' => $raw, 'raw_headers' => $rawHeaders, 'raw_rows' => $rawRows];
    }
}
