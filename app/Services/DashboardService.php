<?php

namespace App\Services;

use App\Models\StokBarang;
use App\Models\TransaksiPenjualan;
use App\Models\User;
use Carbon\Carbon;

class DashboardService
{
    public function data(User $user, string $start, string $end, string $interval = 'hari', string $search = ''): array
    {
        $base = TransaksiPenjualan::whereHas('barang', fn ($q) => $q->where('id_user', $user->id_user))->periode($start, $end);
        $totals = (clone $base)->selectRaw('COALESCE(SUM(total_pendapatan),0) as revenue, COALESCE(SUM(jumlah_terjual),0) as units, COUNT(*) as row_count')->first();
        $products = $user->barang()->with('stokTerakhir')->get();
        $dated = (clone $base)->selectRaw('DATE(tanggal_transaksi) as day, SUM(total_pendapatan) as revenue, SUM(jumlah_terjual) as units')->groupBy('day')->orderBy('day')->get();
        $buckets = [];
        foreach ($dated as $d) {
            $date = Carbon::parse($d->day);
            $key = match ($interval) {
                'minggu' => $date->startOfWeek()->format('Y-m-d'),'bulan' => $date->format('Y-m'),default => $date->format('Y-m-d')
            };
            $buckets[$key] ??= ['revenue' => '0.00', 'units' => 0];
            $buckets[$key]['revenue'] = bcadd($buckets[$key]['revenue'], (string) $d->revenue, 2);
            $buckets[$key]['units'] += (int) $d->units;
        }
        $top = (clone $base)->selectRaw('id_barang, SUM(jumlah_terjual) as units, SUM(total_pendapatan) as revenue')->groupBy('id_barang')->orderByDesc('units')->with('barang')->limit(5)->get();
        $recent = (clone $base)->with('barang')->search($search)->orderByDesc('tanggal_transaksi')->orderByDesc('id_transaksi')->paginate(5)->withQueryString();
        $categories = $products->groupBy(fn ($p) => $p->kategori ?: 'Tanpa kategori')->map(fn ($g) => $g->sum(fn ($p) => $p->stokTerakhir?->sisa_stok ?? 0))->filter(fn ($v) => $v > 0);
        $known = $products->filter(fn ($p) => $p->stokTerakhir !== null);
        $latestSale = TransaksiPenjualan::whereHas('barang', fn ($q) => $q->where('id_user', $user->id_user))->max('updated_at');
        $latestStock = StokBarang::whereHas('barang', fn ($q) => $q->where('id_user', $user->id_user))->max('updated_at');

        return compact('totals', 'products', 'top', 'recent', 'categories', 'known') + ['stock' => $known->sum(fn ($p) => $p->stokTerakhir->sisa_stok), 'critical' => $known->filter(fn ($p) => $p->stokTerakhir->sisa_stok <= $p->stok_minimum)->count(), 'latest' => max($latestSale ?? '', $latestStock ?? '') ?: null, 'chart' => ['labels' => array_keys($buckets), 'revenue' => array_map(fn ($v) => (float) $v['revenue'], array_values($buckets)), 'units' => array_column(array_values($buckets),'units')]];
    }
}
