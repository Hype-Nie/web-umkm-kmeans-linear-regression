@extends('layouts.app')
@section('title', 'Hasil Analisis')
@section('content')
    <div class="page-head">
        <div>
            <h1>Hasil Analisis</h1>
            <p class="subtitle">Pengelompokan K-Means dan prediksi Linear Regression untuk keputusan persediaan.</p>
        </div>
        <div class="actions"><a class="btn" href="{{ route('laporan.index', ['jenis' => 'kmeans']) }}">Export PDF /
                Excel</a><button class="btn" data-print type="button"><x-icon name="print" />Print Preview</button></div>
    </div>
    @if ($stale)
        <div class="alert alert-warning">Data barang, transaksi, atau stok berubah sejak analisis ini. Jalankan ulang
            analisis sebelum menindaklanjuti rekomendasi.</div>
    @endif
    <div class="card mb-4 no-print">
        <div class="card-head">
            <div>
                <h2>Jalankan Analisis SPK</h2>
                <p class="form-help mb-0">Model K-Means Clustering ($K=3$) untuk segmentasi perputaran produk dan Linear Regression ($y = a + bx$) untuk peramalan kebutuhan stok.</p>
            </div>
        </div>
        <form method="post" action="{{ route('analisis.run') }}" data-submit-lock>
            @csrf
            <div class="grid-equal mb-3">
                <div class="field-row">
                    <label class="field" for="mulai">Periode Awal Data</label>
                    <input class="form-control" type="date" id="mulai" name="mulai"
                        value="{{ old('mulai', $defaultStart ?? '2024-10-01') }}" required>
                </div>
                <div class="field-row">
                    <label class="field" for="akhir">Periode Akhir Data</label>
                    <input class="form-control" type="date" id="akhir" name="akhir"
                        value="{{ old('akhir', $defaultEnd ?? '2026-07-31') }}" max="{{ now()->toDateString() }}" required>
                    <small class="text-muted d-block mt-1">Pilih hari terakhir dalam bulan (misal: 31/07/2026) agar bulan tersebut dihitung genap sebagai riwayat historis.</small>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <label class="form-check d-flex align-items-center gap-2 mb-0">
                    <input class="form-check-input" type="checkbox" name="complete" value="1" @checked(old('complete', true))>
                    <span class="form-check-label">Sertakan seluruh produk aktif (bulan tanpa transaksi dihitung 0 unit penjualan)</span>
                </label>
                <button class="btn btn-primary" type="submit">
                    <x-icon name="play" /> Jalankan Analisis (K-Means & Regresi)
                </button>
            </div>
        </form>
    </div>

    @if ($process)
        <!-- Kop Surat & Dokumen Resmi Khusus Cetak / Print Preview -->
        <div class="print-only print-header mb-4">
            <div class="kop-surat" style="text-align: center; margin-bottom: 10px;">
                <div class="kop-toko" style="font-size: 16pt; font-weight: bold; letter-spacing: 1px; color: #000000; margin-bottom: 2px;">
                    {{ strtoupper(auth()->user()->nama_toko ?? 'UD. ZUFRI') }}
                </div>
                <div class="kop-sub" style="font-size: 10pt; font-weight: bold; color: #111111; margin-bottom: 2px; letter-spacing: 0.5px;">
                    SISTEM PENDUKUNG KEPUTUSAN STRATEGIS STOK DAN ANALISIS PENJUALAN
                </div>
                <div class="kop-alamat" style="font-size: 9pt; color: #222222; margin-bottom: 2px;">
                    Jl. Merdeka No. 12, Padangsidimpuan Utara, Kota Padangsidimpuan, Sumatera Utara 22711
                </div>
                <div class="kop-kontak" style="font-size: 8.5pt; color: #333333;">
                    Email: {{ auth()->user()->email ?? 'admin@udzufri.test' }} &nbsp;·&nbsp; Penanggung Jawab: {{ auth()->user()->nama_pemilik ?? 'Zufri Padang' }}
                </div>
                <div class="kop-divider" style="border-top: 2.5px solid #000000; border-bottom: 1px solid #000000; height: 2px; margin-top: 8px; margin-bottom: 14px;"></div>
            </div>

            <div class="print-meta-box">
                <table class="meta-table" style="width: 100%; border: none; border-collapse: collapse; margin-bottom: 12px;">
                    <tr>
                        <td style="border: none; padding: 0; vertical-align: top; width: 62%; text-align: left;">
                            <div style="font-size: 13pt; font-weight: bold; text-transform: uppercase; color: #000000; margin-bottom: 3px; letter-spacing: 0.5px;">
                                LAPORAN HASIL ANALISIS SPK
                            </div>
                            <div style="font-size: 9pt; color: #111111;">
                                Model Segmentasi K-Means Clustering &amp; Peramalan Stok Linear Regression
                            </div>
                        </td>
                        <td style="border: none; padding: 0; vertical-align: top; text-align: right; font-size: 8.5pt; color: #333333;">
                            <div><strong>Waktu Cetak:</strong> {{ now()->translatedFormat('d F Y, H:i') }} WIB</div>
                            <div style="margin-top: 2px;"><strong>Dokumen:</strong> SPK-UDZ-ANL-{{ $process->id_proses }}-{{ now()->format('Ymd') }}</div>
                        </td>
                    </tr>
                </table>

                <table class="table print-table-summary" style="width: 100%; margin-bottom: 14px; font-size: 8.5pt;">
                    <tbody>
                        <tr>
                            <td style="width: 25%; font-weight: bold; background: #f8fafc;">Nomor Proses Analisis</td>
                            <td style="width: 25%;">#{{ $process->id_proses }}</td>
                            <td style="width: 25%; font-weight: bold; background: #f8fafc;">Waktu Eksekusi Model</td>
                            <td style="width: 25%;">{{ $process->created_at->format('d/m/Y H:i') }} WIB</td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; background: #f8fafc;">Rentang Periode Data</td>
                            <td>{{ $process->tanggal_mulai_data?->format('d/m/Y') ?? '—' }} s/d {{ $process->tanggal_akhir_data?->format('d/m/Y') ?? '—' }}</td>
                            <td style="font-weight: bold; background: #f8fafc;">Status Validasi Model</td>
                            <td><strong>{{ strtoupper($process->status) }}</strong> (Valid)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="info-box mb-4 no-print">
            <div class="d-flex justify-content-between gap-2 flex-wrap"><span><strong>Proses
                        #{{ $process->id_proses }}</strong> · Model SPK Terintegrasi ·
                    {{ $process->created_at->format('d M Y H:i') }} WIB · <span
                        class="badge {{ $process->status === 'gagal' ? 'badge-danger' : 'badge-success' }}">{{ strtoupper($process->status) }}</span></span>
                @if ($process->tanggal_mulai_data)
                    <a href="{{ route('analisis.dataset', ['proses' => $process->id_proses]) }}" class="btn btn-sm">Unduh
                        Dataset Analisis (CSV)</a>
                @endif
            </div>
            <p class="mb-0 mt-2">Periode data: {{ $process->tanggal_mulai_data?->format('d/m/Y') ?? '—' }} —
                {{ $process->tanggal_akhir_data?->format('d/m/Y') ?? '—' }}. Algoritma: K-Means / Linear Regression.
            </p>
            @if ($process->status === 'gagal')
                <p class="danger mt-2 mb-0">{{ $process->catatan }}</p>
            @endif
        </div>
    @endif
    @if ($process?->status === 'berhasil')
        <div class="stats four">
            <section class="card">
                <div class="stat-label">Produk Teranalisis</div>
                <div class="stat-value">{{ $analyzedProducts->count() }}</div>
                <p class="stat-note">Hasil model tersedia per produk</p>
            </section>
            <section class="card">
                <div class="stat-label">Jumlah Klaster (K)</div>
                <div class="stat-value">3</div>
                <p class="stat-note">{{ $metadata['iterasi'] ?? '1' }} putaran iterasi hingga konvergen</p>
            </section>
            <section class="card">
                <div class="stat-label">Silhouette Coefficient</div>
                <div class="stat-value">
                    {{ $process->silhouette_coefficient !== null ? number_format((float) $process->silhouette_coefficient, 4) : '—' }}
                </div>
                <p class="stat-note">
                    {{ $process->silhouette_coefficient !== null ? ($process->silhouette_coefficient >= 0.5 ? 'Pemisahan klaster kuat & berkualitas' : 'Klaster terbentuk wajar') : 'Data belum cukup untuk evaluasi' }}
                </p>
            </section>
            <section class="card">
                <div class="stat-label">Prediksi Tersedia</div>
                <div class="stat-value">{{ $forecasts->count() }} Produk</div>
                <p class="stat-note">{{ $analyzedProducts->count() - $forecasts->count() }} produk tanpa prediksi</p>
            </section>
        </div>
        @if ($clusters->count())
            <div class="grid-main">
                <div class="stack">
                    <section class="card">
                        <h2>Visualisasi K-Means Clustering</h2>
                        <p class="form-help">X: jumlah unit terjual · Y: frekuensi pembelian. Setiap titik mewakili produk;
                            centroid ditampilkan sebagai tanda silang.</p>
                        <div class="chart-box" style="height:320px"><canvas data-chart="cluster-chart"
                                aria-label="Scatter plot produk per klaster"></canvas></div>@php
                                    $datasets = [];
                                    foreach (
                                        ['Sangat Laris' => '#059669', 'Cukup' => '#2563eb', 'Kurang' => '#d97706']
                                        as $label => $color
                                    ) {
                                        $group = $clusters->where('label_klaster', $label);
                                        $datasets[] = [
                                            'label' => $label . ' (' . $group->count() . ')',
                                            'data' => $group
                                                ->map(
                                                    fn($c) => [
                                                        'x' => $c->fitur_jumlah_terjual,
                                                        'y' => $c->fitur_frekuensi_pembelian,
                                                    ],
                                                )
                                                ->values(),
                                            'backgroundColor' => $color,
                                            'pointRadius' => 6,
                                        ];
                                    }
                                    $datasets[] = [
                                        'label' => 'Centroid',
                                        'data' => $clusters
                                            ->unique('nomor_klaster')
                                            ->map(
                                                fn($c) => [
                                                    'x' => $c->centroid_jumlah_terjual,
                                                    'y' => $c->centroid_frekuensi_pembelian,
                                                ],
                                            )
                                            ->values(),
                                        'pointStyle' => 'crossRot',
                                        'pointRadius' => 10,
                                        'borderColor' => '#b51a1a',
                                        'backgroundColor' => '#b51a1a',
                                    ];
                                    $clusterConfig = [
                                        'type' => 'scatter',
                                        'data' => ['datasets' => $datasets],
                                        'options' => [
                                            'responsive' => true,
                                            'maintainAspectRatio' => false,
                                            'scales' => [
                                                'x' => [
                                                    'title' => ['display' => true, 'text' => 'Jumlah terjual (unit)'],
                                                    'beginAtZero' => true,
                                                ],
                                                'y' => [
                                                    'title' => ['display' => true, 'text' => 'Frekuensi pembelian'],
                                                    'beginAtZero' => true,
                                                ],
                                            ],
                                            'plugins' => [
                                                'legend' => [
                                                    'position' => 'bottom',
                                                    'labels' => [
                                                        'usePointStyle' => true,
                                                        'boxWidth' => 8,
                                                        'boxHeight' => 8,
                                                        'padding' => 16,
                                                        'font' => [
                                                            'family' => "'Plus Jakarta Sans', system-ui, sans-serif",
                                                            'size' => 12,
                                                            'weight' => '600',
                                                        ],
                                                        'color' => '#334155',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ];
                                @endphp
                        <script id="cluster-chart" type="application/json">@json($clusterConfig)</script>
                    </section>
                    <section class="card">
                        <h2>Centroid & Jumlah Produk</h2>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>LABEL KLASTER</th>
                                        <th style="text-align: center;">JUMLAH PRODUK</th>
                                        <th style="text-align: right;">CENTROID UNIT</th>
                                        <th style="text-align: right;">CENTROID FREKUENSI</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($clusters->unique('nomor_klaster') as $c)
                                        @php
                                            $cBadge = match($c->label_klaster) {
                                                'Sangat Laris' => 'badge-success',
                                                'Cukup Laris' => 'badge-warning',
                                                'Kurang Laris', 'Kurang' => 'badge-danger',
                                                default => '',
                                            };
                                        @endphp
                                        <tr>
                                            <td><span class="badge {{ $cBadge }}">{{ $c->label_klaster }}</span></td>
                                            <td style="text-align: center;">{{ $clusters->where('nomor_klaster', $c->nomor_klaster)->count() }} Produk</td>
                                            <td style="text-align: right;">{{ number_format($c->centroid_jumlah_terjual, 2, ',', '.') }}</td>
                                            <td style="text-align: right;">{{ number_format($c->centroid_frekuensi_pembelian, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
                <aside class="insights">
                    <h2>✧ Strategic Insights</h2>
                    <p class="mt-4"><strong>Perputaran
                            Produk</strong><br>{{ $clusters->where('label_klaster', 'Sangat Laris')->count() }} produk
                        berada di kelompok Sangat Laris; {{ $clusters->where('label_klaster', 'Kurang')->count() }} produk
                        berada di kelompok Kurang. Label mengikuti peringkat centroid setelah standardisasi fitur.</p>
                    <p><strong>Kebutuhan Bulan Berikutnya</strong><br>{{ $forecasts->count() }} produk memiliki prediksi
                        yang tersedia. Prediksi unit dibatasi minimal nol dan dibulatkan ke atas.</p>
                    <p><strong>Dasar Keputusan</strong><br>Gabungkan klaster, prediksi, dan snapshot stok terbaru untuk
                        menilai restock atau promosi. ROI belum dapat dihitung tanpa biaya dan asumsi promosi.</p><a
                        class="btn btn-wide mt-3 no-print" href="{{ route('rekomendasi.index') }}">LIHAT DETAIL STRATEGI</a>
                </aside>
            </div>
            <section class="card flush mb-4">
                <div class="card-head">
                    <div>
                        <h2>Pengelompokan Produk</h2>
                        <p class="form-help mb-0">Frekuensi dihitung dari kejadian pembelian unik berdasarkan kode
                            transaksi atau waktu transaksi.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 5%; text-align: center;">NO</th>
                                <th>PRODUK</th>
                                <th>KATEGORI</th>
                                <th style="text-align: right;">JUMLAH TERJUAL</th>
                                <th style="text-align: right;">FREKUENSI</th>
                                <th>KLASTER</th>
                                <th style="text-align: right;">JARAK Z-SCORE</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($clusters as $c)
                                @php
                                    $cBadge = match($c->label_klaster) {
                                        'Sangat Laris' => 'badge-success',
                                        'Cukup Laris' => 'badge-warning',
                                        'Kurang Laris', 'Kurang' => 'badge-danger',
                                        default => '',
                                    };
                                @endphp
                                <tr>
                                    <td style="text-align: center;">{{ $loop->iteration }}</td>
                                    <td><strong>{{ $c->barang->nama_barang }}</strong><small
                                            class="d-block muted">{{ $c->barang->kode_barang }}</small></td>
                                    <td>{{ $c->barang->kategori ?: '—' }}</td>
                                    <td style="text-align: right;">{{ number_format($c->fitur_jumlah_terjual, 0, ',', '.') }} unit</td>
                                    <td style="text-align: right;">{{ number_format($c->fitur_frekuensi_pembelian, 0, ',', '.') }} kali</td>
                                    <td><span class="badge {{ $cBadge }}">{{ $c->label_klaster }}</span></td>
                                    <td style="text-align: right;">{{ $c->jarak_ke_centroid !== null ? number_format($c->jarak_ke_centroid, 4, ',', '.') : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else<div class="mb-4"><x-empty title="K-Means belum tersedia"
                    message="K=3 memerlukan minimal tiga produk dengan fitur berbeda. Hasil regresi yang memenuhi syarat tetap dapat digunakan." />
            </div>
        @endif
        <section class="card mb-4">
            <h2>Linear Regression · Historis & Prediksi</h2>
            @if ($forecasts->count())
                <form class="filters mt-3 no-print"><input type="hidden" name="proses" value="{{ $process->id_proses }}">
                    <div class="filter-grow"><label class="field" for="produk">Pilih Produk</label><select class="form-select"
                            name="produk" id="produk" data-searchable onchange="if(this.form.requestSubmit){this.form.requestSubmit()}else{this.form.submit()}">
                            @foreach ($forecasts as $f)
                                <option value="{{ $f->id_barang }}" @selected($forecast?->id_barang === $f->id_barang)>
                                    {{ $f->barang->nama_barang }}</option>
                            @endforeach
                        </select>
                    </div><button class="btn">Tampilkan</button>
                </form>

                <div class="print-only print-product-subhead mb-2" style="font-size: 9.5pt;">
                    <strong>Sampel Grafik Peramalan Produk:</strong> {{ $forecast?->barang->nama_barang }} ({{ $forecast?->barang->kode_barang }})
                </div>

                @php
                    $evaluation = $metadata['evaluasi_per_produk'][$forecast?->barang->kode_barang] ?? null;
                @endphp

                @if ($evaluation)
                    @php
                        $formattedMonths = array_map(function ($m) {
                            try {
                                return \Carbon\Carbon::parse($m . '-01')->translatedFormat('M Y');
                            } catch (\Throwable $e) {
                                return $m;
                            }
                        }, $evaluation['months']);
                        $forecastLabel = $forecast->periode_prediksi->translatedFormat('M Y') . ' (Prediksi)';
                        $chartLabels = array_merge($formattedMonths, [$forecastLabel]);
                        $forecastConfig = [
                            'type' => 'line',
                            'data' => [
                                'labels' => $chartLabels,
                                'datasets' => [
                                    [
                                        'label' => 'Penjualan Historis',
                                        'data' => array_merge($evaluation['historical'], [null]),
                                        'borderColor' => '#0f172a',
                                        'backgroundColor' => '#0f172a',
                                        'tension' => 0.15,
                                        'pointRadius' => 4,
                                        'pointHoverRadius' => 6,
                                    ],
                                    [
                                        'label' => 'Prediksi Kebutuhan',
                                        'data' => array_merge(array_fill(0, count($evaluation['historical']) - 1, null), [end($evaluation['historical']), $forecast->angka_ramalan_stok]),
                                        'borderColor' => '#2563eb',
                                        'borderDash' => [6, 4],
                                        'backgroundColor' => '#2563eb',
                                        'pointRadius' => 5,
                                        'pointHoverRadius' => 7,
                                    ],
                                ],
                            ],
                            'options' => [
                                'responsive' => true,
                                'maintainAspectRatio' => false,
                                'plugins' => [
                                    'legend' => [
                                        'position' => 'top',
                                        'align' => 'center',
                                        'labels' => [
                                            'usePointStyle' => true,
                                            'pointStyle' => 'circle',
                                            'boxWidth' => 8,
                                            'boxHeight' => 8,
                                            'padding' => 16,
                                            'font' => [
                                                'family' => "'Plus Jakarta Sans', system-ui, sans-serif",
                                                'size' => 12,
                                                'weight' => '600',
                                            ],
                                            'color' => '#334155',
                                        ],
                                    ],
                                ],
                                'scales' => [
                                    'y' => [
                                        'beginAtZero' => true,
                                        'title' => [
                                            'display' => true,
                                            'text' => 'Jumlah Unit',
                                            'font' => [
                                                'family' => "'Plus Jakarta Sans', system-ui, sans-serif",
                                                'size' => 12,
                                                'weight' => '600',
                                            ],
                                            'color' => '#64748b',
                                        ],
                                        'grid' => ['color' => '#f1f5f9'],
                                        'ticks' => [
                                            'precision' => 0,
                                        ],
                                    ],
                                    'x' => [
                                        'grid' => ['color' => '#f1f5f9'],
                                        'ticks' => [
                                            'font' => [
                                                'family' => "'Plus Jakarta Sans', system-ui, sans-serif",
                                                'size' => 11,
                                            ],
                                            'color' => '#64748b',
                                        ],
                                    ],
                                ],
                            ],
                        ];
                    @endphp
                    <div class="chart-box"><canvas data-chart="forecast-chart"
                            aria-label="Historis penjualan dan prediksi"></canvas></div>
                    <script id="forecast-chart" type="application/json">@json($forecastConfig)</script>
                    <div class="card p-3 mt-3 bg-white border">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <div>
                                <strong class="d-block" style="font-size:13.5px; color:var(--ink);">Ringkasan Pola: {{ $forecast->barang->nama_barang }}</strong>
                                <span class="text-muted small">Dihitung dari {{ count($evaluation['months']) }} bulan transaksi dengan {{ $evaluation['validation_points'] }} titik validasi bertahap</span>
                            </div>
                            @php
                                $b = $evaluation['b'];
                                $trendText = $b > 0.3 ? 'Tren Meningkat' : ($b < -0.3 ? 'Tren Menurun' : 'Tren Relatif Stabil');
                                $trendBadge = $b > 0.3 ? 'badge-success' : ($b < -0.3 ? 'badge-warning' : 'badge-info');
                            @endphp
                            <span class="badge {{ $trendBadge }}">
                                {{ $trendText }} ({{ $b >= 0 ? '+' : '' }}{{ number_format($b, 1) }} unit/bln)
                            </span>
                        </div>

                        <div class="row g-2 mt-1">
                            <div class="col-md-4">
                                <div class="p-2 border rounded" style="background:var(--surface);">
                                    <small class="text-muted d-block" style="font-size:11px;">Rata-rata Simpangan (MAE)</small>
                                    <strong class="d-block" style="font-size:14px; color:var(--accent);">&plusmn;{{ number_format($evaluation['mae'], 1) }} Unit</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded" style="background:var(--surface);">
                                    <small class="text-muted d-block" style="font-size:11px;">Deviasi Ekstrem (RMSE)</small>
                                    <strong class="d-block" style="font-size:14px; color:var(--ink);">{{ number_format($evaluation['rmse'], 1) }} Unit</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded" style="background:var(--surface);">
                                    <small class="text-muted d-block" style="font-size:11px;">Koefisien Determinasi (R²)</small>
                                    <strong class="d-block" style="font-size:14px; color:var(--ink);">
                                        {{ $evaluation['r_squared'] !== null && $evaluation['r_squared'] > 0 ? number_format($evaluation['r_squared'] * 100, 1) . '%' : 'Bervariasi' }}
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <details class="mt-2 text-muted small print-expanded">
                            <summary style="cursor:pointer; color:var(--accent); font-weight:600;" class="no-print">Detail Persamaan &amp; Metrik</summary>
                            <div class="p-2 mt-2 bg-light rounded border">
                                <div><strong>Persamaan Regresi Linier:</strong> <code>Y = {{ number_format($evaluation['a'], 4) }} + ({{ number_format($evaluation['b'], 4) }})X</code></div>
                                <div class="mt-1"><strong>Nilai Metrik:</strong> MSE: {{ number_format($evaluation['mse'], 4) }} &middot; RMSE: {{ number_format($evaluation['rmse'], 4) }} &middot; MAE: {{ number_format($evaluation['mae'], 4) }} &middot; R²: {{ $evaluation['r_squared'] !== null ? number_format($evaluation['r_squared'], 4) : 'N/A' }}</div>
                                <div class="mt-1 text-muted">Evaluasi menggunakan validasi <em>Rolling-Origin</em> berdasarkan deret waktu historis.</div>
                            </div>
                        </details>
                    </div>@else<p class="muted mt-3">Historis/model per produk tidak disertakan dalam berkas RapidMiner.
                        Tabel berikut menampilkan nilai hasil yang diimpor; metrik proses tersedia pada rincian proses.</p>
            @endif @else<x-empty title="Data tidak cukup untuk prediksi"
                    message="Minimal 3 bulan lengkap diperlukan. Lengkapi transaksi atau konfirmasi arti bulan kosong sebelum menjalankan ulang." />
            @endif
        </section>
        <section class="card flush mb-4">
            <div class="card-head">
                <h2>Hasil Prediksi Kebutuhan Stok per Produk</h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 5%; text-align: center;">NO</th>
                            <th>PRODUK</th>
                            <th style="text-align: center;">TARGET BULAN PREDIKSI</th>
                            <th style="text-align: right;">PREDIKSI MENTAH (REGRESI)</th>
                            <th style="text-align: right;">KEBUTUHAN STOK</th>
                            <th style="text-align: center;">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($analyzedProducts as $product)
                            @php
                                $f = $forecasts->firstWhere('id_barang', $product->id_barang);
                            @endphp
                            <tr>
                                <td style="text-align: center;">{{ $loop->iteration }}</td>
                                <td><strong>{{ $product->nama_barang }}</strong><small class="d-block muted">{{ $product->kode_barang }}</small></td>
                                <td style="text-align: center;">{{ $f?->periode_prediksi->translatedFormat('F Y') ?? '—' }}</td>
                                <td style="text-align: right;">{{ $f?->prediksi_penjualan ?? '—' }}</td>
                                <td style="text-align: right;"><strong>{{ $f?->angka_ramalan_stok !== null ? number_format($f->angka_ramalan_stok, 0, ',', '.') . ' Unit' : '—' }}</strong></td>
                                <td style="text-align: center;">{{ $f ? 'Tersedia' : 'Data Kurang' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @if (!empty($metadata['peringatan']))
            <div class="alert alert-warning"><strong>Catatan Ketersediaan Model</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($metadata['peringatan'] as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @else<x-empty title="Analisis belum tersedia"
            message="Unggah transaksi asli, lalu jalankan analisis SPK di atas. Tidak ada klaster atau prediksi contoh yang ditampilkan." />
    @endif
    @if ($process)
        @php
            $evalList = $metadata['evaluasi_per_produk'] ?? [];
            $avgMae = count($evalList) ? array_sum(array_column($evalList, 'mae')) / count($evalList) : null;
            $avgRmse = count($evalList) ? array_sum(array_column($evalList, 'rmse')) / count($evalList) : null;
            $silScore = $process->silhouette_coefficient !== null ? (float) $process->silhouette_coefficient : null;

            if ($silScore !== null) {
                if ($silScore >= 0.70) {
                    $silLabel = 'Struktur Sangat Kuat';
                    $silBadge = 'badge-success';
                } elseif ($silScore >= 0.50) {
                    $silLabel = 'Struktur Baik';
                    $silBadge = 'badge-success';
                } elseif ($silScore >= 0.25) {
                    $silLabel = 'Struktur Sedang';
                    $silBadge = 'badge-info';
                } else {
                    $silLabel = 'Klaster Homogen';
                    $silBadge = 'badge-warning';
                }
            } else {
                $silLabel = 'Data Terbatas';
                $silBadge = 'badge-info';
            }
        @endphp

        <section class="card mt-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1" style="font-weight:700;">Rincian Proses &amp; Evaluasi Model</h2>
                    <p class="text-muted small mb-0">Parameter komputasi dan metrik evaluasi kinerja algoritma.</p>
                </div>
                <span class="badge {{ $process->status === 'berhasil' ? 'badge-success' : 'badge-danger' }}">
                    STATUS: {{ strtoupper($process->status) }}
                </span>
            </div>

            <div class="row g-3 mb-4">
                <!-- Card 1: Evaluasi K-Means -->
                <div class="col-md-6">
                    <div class="p-3 border rounded h-100" style="background:var(--surface);">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <small class="text-muted text-uppercase fw-bold" style="font-size:11px;">1. Pengelompokan (K-Means Clustering)</small>
                                <h3 class="h6 mb-0 mt-1" style="font-weight:700;">Kualitas Segmentasi</h3>
                            </div>
                            <span class="badge {{ $silBadge }}">{{ $silLabel }}</span>
                        </div>
                        <p class="small text-muted mb-3">Evaluasi pemisahan dan homogenitas klaster berdasarkan volume serta frekuensi transaksi.</p>
                        
                        <div class="d-flex gap-4 pt-2 border-top flex-wrap">
                            <div>
                                <small class="text-muted d-block" style="font-size:11px;">Skor Silhouette</small>
                                <strong>{{ $silScore !== null ? number_format($silScore, 4) : '—' }}</strong>
                            </div>
                            <div>
                                <small class="text-muted d-block" style="font-size:11px;">Iterasi Konvergen</small>
                                <strong>{{ $metadata['iterasi'] ?? '1' }} Putaran</strong>
                            </div>
                            <div>
                                <small class="text-muted d-block" style="font-size:11px;">Standarisasi</small>
                                <strong>Z-Score Populasi</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Evaluasi Regresi Linier -->
                <div class="col-md-6">
                    <div class="p-3 border rounded h-100" style="background:var(--surface);">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <small class="text-muted text-uppercase fw-bold" style="font-size:11px;">2. Peramalan (Linear Regression)</small>
                                <h3 class="h6 mb-0 mt-1" style="font-weight:700;">Akurasi Prediksi</h3>
                            </div>
                            <span class="badge badge-success">Rolling-Origin</span>
                        </div>
                        <p class="small text-muted mb-3">Pengujian model peramalan deret waktu bertahap pada data historis transaksi.</p>
                        
                        <div class="d-flex gap-4 pt-2 border-top flex-wrap">
                            <div>
                                <small class="text-muted d-block" style="font-size:11px;">Rata-rata Simpangan (MAE)</small>
                                <strong style="color:var(--accent);">{{ $avgMae !== null ? '&plusmn;' . number_format($avgMae, 1) . ' Unit' : '—' }}</strong>
                            </div>
                            <div>
                                <small class="text-muted d-block" style="font-size:11px;">Simpangan Kuadrat (RMSE)</small>
                                <strong>{{ $avgRmse !== null ? number_format($avgRmse, 1) . ' Unit' : '—' }}</strong>
                            </div>
                            <div>
                                <small class="text-muted d-block" style="font-size:11px;">Cakupan Produk</small>
                                <strong>{{ count($evalList) }} Komoditas</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Metrik Evaluasi per Produk -->
            @if (count($evalList))
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <strong style="font-size:13px;">Evaluasi Model per Komoditas:</strong>
                    </div>
                    <div class="table-wrap border rounded">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align: center;">NO</th>
                                    <th>KODE</th>
                                    <th>NAMA KOMODITAS</th>
                                    <th>TREN PENJUALAN ($b$)</th>
                                    <th style="text-align: right;">MAE</th>
                                    <th style="text-align: right;">RMSE</th>
                                    <th style="text-align: center;">STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($forecasts as $f)
                                    @php
                                        $e = $evalList[$f->barang->kode_barang] ?? null;
                                        if (!$e) continue;
                                        $slope = $e['b'];
                                        $maeVal = $e['mae'];
                                        $accuracyBadge = $maeVal <= 10 ? 'badge-success' : ($maeVal <= 25 ? 'badge-info' : 'badge-warning');
                                        $accuracyLabel = $maeVal <= 10 ? 'Akurat' : ($maeVal <= 25 ? 'Wajar' : 'Fluktuatif');
                                    @endphp
                                    <tr>
                                        <td style="text-align: center;">{{ $loop->iteration }}</td>
                                        <td><code>{{ $f->barang->kode_barang }}</code></td>
                                        <td><strong>{{ $f->barang->nama_barang }}</strong></td>
                                        <td>
                                            @if ($slope > 0.1)
                                                <span class="text-success">&uarr; Naik (+{{ number_format($slope, 2, ',', '.') }})</span>
                                            @elseif ($slope < -0.1)
                                                <span class="text-warning">&darr; Turun ({{ number_format($slope, 2, ',', '.') }})</span>
                                            @else
                                                <span class="text-muted">&rarr; Stabil</span>
                                            @endif
                                        </td>
                                        <td style="text-align: right;">&plusmn;{{ number_format($e['mae'], 1, ',', '.') }} unit</td>
                                        <td style="text-align: right;">{{ number_format($e['rmse'], 1, ',', '.') }} unit</td>
                                        <td style="text-align: center;"><span class="badge {{ $accuracyBadge }}">{{ $accuracyLabel }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Collapsible Technical JSON -->
            <details class="mt-3 no-print">
                <summary class="small text-muted" style="cursor:pointer; font-weight:600;">
                    <x-icon name="settings" /> Metadata Teknis (JSON)
                </summary>
                <div class="mt-2">
                    <pre class="metadata" style="max-height:260px; overflow-y:auto;">
{{ $metadata ? json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $process->catatan }}</pre>
                </div>
            </details>
        </section>
    @endif
    <section class="card flush mt-4 no-print">
        <div class="card-head">
            <h2>Riwayat Analisis</h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>PROSES</th>
                        <th>WAKTU (WIB)</th>
                        <th>PERIODE DATA</th>
                        <th>SUMBER</th>
                        <th>STATUS</th>
                        <th>AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $p)
                        <tr>
                            <td>#{{ $p->id_proses }}</td>
                            <td>{{ $p->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $p->tanggal_mulai_data?->format('d/m/Y') ?? '—' }} —
                                {{ $p->tanggal_akhir_data?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $p->sumber_proses === 'backend_php' ? 'Sistem SPK' : $p->sumber_proses }}</td>
                            <td><span
                                    class="badge {{ $p->status === 'gagal' ? 'badge-danger' : 'badge-success' }}">{{ $p->status }}</span>
                            </td>
                            <td><a class="btn btn-sm"
                                    href="{{ route('analisis.index', ['proses' => $p->id_proses]) }}">Detail</a></td>
                    </tr>@empty<tr>
                            <td colspan="6">Belum ada proses analisis.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $history->links() }}</div>
    </section>

    @if ($process)
        <!-- Lembar Pengesahan Resmi Khusus Cetak / Print Preview -->
        <div class="print-only print-signature mt-4" style="page-break-inside: avoid; break-inside: avoid;">
            <table class="signature-table" style="width: 100%; border: none; border-collapse: collapse; margin-top: 24px; page-break-inside: avoid;">
                <tr>
                    <td style="width: 60%; border: none;"></td>
                    <td style="width: 40%; border: none; text-align: center;">
                        <div style="font-size: 9pt; color: #222222; margin-bottom: 3px;">
                            Padangsidimpuan, {{ now()->translatedFormat('d F Y') }}
                        </div>
                        <div style="font-size: 9.5pt; font-weight: bold; color: #000000; margin-bottom: 50px;">
                            Mengetahui,<br>Pemilik {{ auth()->user()->nama_toko ?? 'UD. Zufri' }}
                        </div>
                        <div style="font-size: 9.5pt; font-weight: bold; text-decoration: underline; color: #000000;">
                            {{ auth()->user()->nama_pemilik ?? 'Zufri Padang' }}
                        </div>
                        <div style="font-size: 8pt; color: #333333; margin-top: 2px;">
                            NPM. 2204190001 (UMTS) &middot; Penanggung Jawab Usaha
                        </div>
                    </td>
                </tr>
            </table>

            <table style="width: 100%; border: none; border-top: 1px solid #666666; margin-top: 20px; padding-top: 6px; font-size: 7.5pt; color: #333333;">
                <tr>
                    <td style="border: none; padding: 0; text-align: left;">
                        Sistem Pendukung Keputusan Strategis Stok dan Analisis Penjualan UMKM · {{ auth()->user()->nama_toko ?? 'UD. Zufri' }}
                    </td>
                    <td style="border: none; padding: 0; text-align: right;">
                        Dokumen Resmi Hasil Analisis SPK Terverifikasi Sistem
                    </td>
                </tr>
            </table>
        </div>
    @endif
@endsection
