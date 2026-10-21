@extends('layouts.app')
@section('title', 'Ikhtisar Eksekutif')
@section('content')
    <div class="page-head">
        <div>
            <h1>Ikhtisar Eksekutif</h1>
            <p class="subtitle">Ringkasan performa penjualan dan inventaris bisnis Anda.</p>
        </div><a class="btn btn-primary" href="{{ route('laporan.index', ['mulai' => $start, 'akhir' => $end]) }}"><x-icon
                name="download" />Unduh Laporan</a>
    </div>
    <form class="filters" method="get">
        <div><label class="field" for="mulai">Dari Tanggal</label><input class="form-control" type="date" id="mulai"
                name="mulai" value="{{ $start }}" required></div>
        <div><label class="field" for="akhir">Sampai Tanggal</label><input class="form-control" type="date"
                id="akhir" name="akhir" value="{{ $end }}" required></div>
        <div><label class="field" for="interval">Agregasi Grafik</label><select class="form-select" name="interval"
                id="interval">
                @foreach (['hari' => 'Harian', 'minggu' => 'Mingguan', 'bulan' => 'Bulanan'] as $key => $label)
                    <option value="{{ $key }}" @selected(($interval ?? request('interval', 'hari')) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div><button class="btn btn-primary">Terapkan Filter</button><a href="{{ route('dashboard') }}"
            class="btn">Reset</a>
    </form>
    <div class="stats">
        <section class="card">
            <div class="stat-label">Total Pendapatan <x-icon name="money" /></div>
            <div class="stat-value">{{ \App\Support\Money::format($totals->revenue) }}</div>
            <p class="stat-note">{{ number_format($totals->units, 0, ',', '.') }} unit terjual pada periode terpilih</p>
        </section>
        <section class="card">
            <div class="stat-label">Aset Inventaris <x-icon name="box" /></div>
            <div class="stat-value">{{ number_format($stock, 0, ',', '.') }} Unit</div>
            <p class="stat-note">{{ $products->count() }} produk · {{ $known->count() }} memiliki snapshot stok</p>
        </section>
        <section class="card">
            <div class="stat-label">Peringatan Stok <x-icon name="warning" class="danger" /></div>
            <div class="stat-value">{{ $critical }} Produk</div>
            <p class="stat-note {{ $critical ? 'danger' : 'muted' }}">
                {{ $critical ? 'Stok sama dengan atau di bawah ambang minimum' : 'Tidak ada stok kritis yang tercatat' }}
            </p>
        </section>
    </div>
    @if ($products->count() > $known->count())
        <div class="info-box mb-4">{{ $products->count() - $known->count() }} produk belum memiliki snapshot stok. Posisi
            dan
            peringatan stok hanya mencakup produk yang sudah tercatat. <a href="{{ route('barang.index') }}"><u>Lengkapi
                    stok</u></a>.</div>
    @endif
    <div class="grid-main">
        <section class="card">
            <div class="d-flex justify-content-between gap-2">
                <h2>Tren Penjualan</h2><span class="muted small">{{ ucfirst($interval ?? request('interval', 'hari')) }} ·
                    {{ $start }} — {{ $end }}</span>
            </div>
            @if (count($chart['labels']))
                <div class="chart-box"><canvas data-chart="sales-chart"
                        aria-label="Grafik pendapatan dan volume penjualan"></canvas></div>@php($salesConfig = ['type' => 'bar', 'data' => ['labels' => $chart['labels'], 'datasets' => [['label' => 'Pendapatan (Rp)', 'data' => $chart['revenue'], 'backgroundColor' => '#2563eb', 'borderRadius' => 4, 'yAxisID' => 'revenue'], ['label' => 'Unit terjual', 'data' => $chart['units'], 'type' => 'line', 'borderColor' => '#0d9488', 'backgroundColor' => '#0d9488', 'yAxisID' => 'units', 'tension' => 0.25, 'pointRadius' => 3]]], 'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'interaction' => ['mode' => 'index', 'intersect' => false], 'plugins' => ['legend' => ['position' => 'top', 'align' => 'end']], 'scales' => ['revenue' => ['beginAtZero' => true, 'position' => 'left', 'grid' => ['color' => '#f1f5f9']], 'units' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false]]]]])
            <script id="sales-chart" type="application/json">@json($salesConfig)</script>@else<x-empty title="Belum ada penjualan pada periode ini"
                    message="Unggah transaksi asli atau pilih rentang tanggal yang sesuai."><a
                        href="{{ route('impor.index') }}" class="btn">Unggah Data</a></x-empty>
            @endif
        </section>
        <section class="card">
            <h2>Distribusi Stok per Kategori</h2>
            @if ($categories->isNotEmpty())
                <div class="chart-box" style="height:220px"><canvas data-chart="category-chart"
                        aria-label="Distribusi stok per kategori"></canvas></div>@php($categoryConfig = ['type' => 'doughnut', 'data' => ['labels' => $categories->keys()->values(), 'datasets' => [['data' => $categories->values(), 'backgroundColor' => ['#0f172a', '#2563eb', '#0d9488', '#f59e0b', '#8b5cf6', '#64748b'], 'borderWidth' => 2, 'borderColor' => '#fff']]], 'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'cutout' => '76%', 'plugins' => ['legend' => ['display' => false]]]])
                <script id="category-chart" type="application/json">@json($categoryConfig)</script>
                @php($palette = ['#0f172a', '#2563eb', '#0d9488', '#f59e0b', '#8b5cf6', '#64748b'])
                <ul class="category-list">
                    @foreach ($categories as $category => $value)
                        <li><span><i class="chart-legend"
                                    style="background-color:{{ $palette[$loop->index % count($palette)] }}"></i>{{ $category }}</span><strong>{{ number_format($value, 0, ',', '.') }}
                                unit</strong></li>
                    @endforeach
                </ul>
            @else<x-empty title="Stok belum tersedia"
                    message="Catat snapshot stok untuk melihat distribusi kategori." />
            @endif
        </section>
    </div>
    <section class="card flush">
        <div class="card-head">
            <h2>Catatan Transaksi Terbaru</h2>
            <form class="actions" method="get"><input type="hidden" name="mulai" value="{{ $start }}"><input
                    type="hidden" name="akhir" value="{{ $end }}"><input type="hidden" name="interval"
                    value="{{ request('interval', 'hari') }}"><input class="form-control" name="q"
                    value="{{ request('q') }}" placeholder="Cari transaksi / produk…" aria-label="Cari transaksi"><button
                    class="btn">Cari</button><a class="btn btn-primary"
                    href="{{ route('transaksi.create') }}">Tambah</a></form>
        </div>
        @if ($recent->isNotEmpty())
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID TRANSAKSI</th>
                            <th>TANGGAL (WIB)</th>
                            <th>PRODUK</th>
                            <th>UNIT</th>
                            <th>TOTAL</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recent as $t)
                            <tr>
                                <td>{{ $t->kode_transaksi ?: '#' . $t->id_transaksi }}</td>
                                <td>{{ $t->tanggal_transaksi->format('d M Y, H:i') }}</td>
                                <td>{{ $t->barang->nama_barang }}</td>
                                <td>{{ $t->jumlah_terjual }}</td>
                                <td><strong>{{ \App\Support\Money::format($t->total_pendapatan) }}</strong></td>
                                <td><a href="{{ route('transaksi.edit', $t) }}" class="btn btn-sm">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        <div class="pagination-wrap">{{ $recent->links() }}</div>@else<div class="p-3"><x-empty
                    title="Tidak ada transaksi" message="Transaksi yang sesuai filter akan ditampilkan di sini." /></div>
        @endif
    </section>
    <div class="grid-equal mt-4">
        <section class="card">
            <h2>Produk Terlaris</h2>
            @forelse($top as $p)
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>{{ $p->barang->nama_barang }}</span><strong>{{ $p->units }} unit</strong>
            </div>@empty<p class="muted mb-0">Belum ada peringkat pada periode ini.</p>
            @endforelse
        </section>
        <section class="card">
            <h2>Wawasan Data</h2>
            <p class="muted">
                @if ($totals->row_count){{ $totals->row_count }} baris penjualan
                    tercatat pada periode terpilih. @if ($top->first())
                        Produk dengan volume tertinggi adalah {{ $top->first()->barang->nama_barang }} dengan
                        {{ $top->first()->units }} unit.
                    @endif
                @else
                    Tambahkan transaksi untuk mulai membaca tren bisnis Anda. @endif
            </p>
            <p class="form-help mb-2">Pembaruan data terakhir:
                {{ $latest ? \Carbon\Carbon::parse($latest)->format('d M Y, H:i') . ' WIB' : 'Belum ada data' }}. Stok
                menunjukkan posisi terkini, terpisah dari filter penjualan.</p><a href="{{ route('analisis.index') }}"
                class="btn btn-sm">Lihat Hasil Analisis <x-icon name="arrow" /></a>
        </section>
    </div>
@endsection
