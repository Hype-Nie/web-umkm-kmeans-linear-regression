<div class="grid-equal mb-4">
    @foreach (['elbow' => ['Elbow Method', 'sse', 'Within-Cluster Sum of Squares (WCSS)'], 'silhouette' => ['Silhouette Analysis', 'silhouette', 'Rata-rata Silhouette']] as $key => [$title, $metric, $axis])
        <section class="card">
            <h2>{{ $title }}</h2>
            <p class="form-help">{{ $key === 'elbow' ? 'Bandingkan penurunan WCSS saat jumlah klaster bertambah.' : 'Skor mendekati 1 menunjukkan pemisahan klaster yang lebih baik.' }}</p>
            @if (count($diagnostics['points']))
                @php
                    $chart = [
                        'type' => 'line',
                        'data' => [
                            'labels' => array_column($diagnostics['points'], 'k'),
                            'datasets' => [[
                                'label' => $axis,
                                'data' => array_column($diagnostics['points'], $metric),
                                'borderColor' => '#2563eb', 'backgroundColor' => '#eff6ff',
                                'pointBackgroundColor' => '#2563eb', 'pointRadius' => 4,
                                'fill' => $key === 'elbow', 'tension' => 0.15,
                            ]],
                        ],
                        'options' => [
                            'responsive' => true, 'maintainAspectRatio' => false,
                            'plugins' => ['legend' => ['display' => false]],
                            'scales' => [
                                'x' => ['title' => ['display' => true, 'text' => 'Jumlah klaster (K)']],
                                'y' => $key === 'elbow' ? ['beginAtZero' => true] : ['min' => -1, 'max' => 1],
                            ],
                        ],
                    ];
                @endphp
                <div class="chart-box diagnostics-chart"><canvas data-chart="{{ $key }}-chart" aria-label="{{ $title }} berdasarkan data produk"></canvas></div>
                <script id="{{ $key }}-chart" type="application/json">@json($chart)</script>
            @else
                <p class="diagnostics-empty">{{ $diagnostics['reason'] }}</p>
            @endif
        </section>
    @endforeach
</div>
@if (count($diagnostics['points']))
    <p class="form-help mb-4">Perbandingan K dihitung ulang dari unit, frekuensi, dan pendapatan yang distandardisasi. Grafik ini membantu evaluasi, hasil klaster tersimpan tetap memakai K=3. Silhouette tidak didefinisikan untuk K=1.</p>
@endif
<section class="card flush mb-4" style="overflow: visible;">
    <div class="card-head category-metrics-head" style="border-radius: var(--radius-md) var(--radius-md) 0 0; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2>Metrik Perputaran Stok</h2>
            <p class="form-help mb-0">Rincian performa per kategori produk berdasarkan hasil clustering</p>
        </div>
        <form method="get" action="{{ route('analisis.index') }}" class="category-filter d-flex align-items-center gap-2 ms-auto no-print" style="display: flex; align-items: center; gap: 8px; margin-left: auto;">
            <input type="hidden" name="proses" value="{{ $process->id_proses }}">
            <div class="category-search position-relative" style="position: relative;">
                <x-icon name="search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); width: 17px; height: 17px; pointer-events: none;" />
                <input class="form-control" type="search" name="kategori" value="{{ request('kategori') }}" placeholder="Cari kategori" aria-label="Cari kategori" style="padding-left: 34px; max-width: 220px;">
            </div>
            <details class="category-filter-options position-relative" style="position: relative;" @if(request('klaster')) open @endif>
                <summary class="btn d-inline-flex align-items-center gap-2" style="list-style: none;"><x-icon name="filter" /> Filter</summary>
                <div class="category-filter-popover" style="position: absolute; z-index: 20; right: 0; top: 44px; min-width: 230px; padding: 16px; background: #fff; border: 1px solid var(--line); border-radius: 8px; box-shadow: var(--shadow-md);">
                    <label class="field" for="klaster">Klaster</label>
                    <select class="form-select" id="klaster" name="klaster">
                        <option value="">Semua klaster</option>
                        @foreach (['Sangat Laris', 'Cukup', 'Kurang'] as $label)
                            <option value="{{ $label }}" @selected(request('klaster') === $label)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="d-flex gap-2 mt-2">
                        <button class="btn btn-primary flex-grow-1" type="submit">Terapkan</button>
                        <a class="btn" href="{{ route('analisis.index', ['proses' => $process->id_proses]) }}">Reset</a>
                    </div>
                </div>
            </details>
            <button class="visually-hidden" type="submit">Cari kategori</button>
        </form>
    </div>
    <div class="table-wrap">
        @php
            $periodMonths = ($process?->tanggal_mulai_data && $process?->tanggal_akhir_data)
                ? max(1, (int) $process->tanggal_mulai_data->copy()->startOfMonth()->diffInMonths($process->tanggal_akhir_data->copy()->startOfMonth()) + 1)
                : 1;
        @endphp
        <table class="table">
            <thead>
                <tr>
                    <th>KATEGORI PRODUK</th>
                    <th>KLASTER</th>
                    <th>TURNOVER RATE</th>
                    <th style="text-align: right;">NILAI STOK (RP)</th>
                    <th style="text-align: center;">STATUS</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categoryMetrics as $category => $group)
                    @php
                        $dominantCluster = $group->groupBy('label_klaster')
                            ->sortByDesc(fn ($items) => ($items->sum('fitur_jumlah_terjual') * 1000) + $items->count())
                            ->keys()
                            ->first() ?? 'Cukup';
                        $clusterBadge = match ($dominantCluster) {
                            'Sangat Laris' => 'badge-success',
                            'Cukup', 'Cukup Laris' => 'badge-primary',
                            default => 'badge-warning',
                        };

                        $totalSold = (int) $group->sum('fitur_jumlah_terjual');
                        $totalStock = (int) $group->sum(fn ($c) => (int) ($c->barang->stokTerakhir?->sisa_stok ?? 0));
                        $totalMinStock = (int) $group->sum(fn ($c) => max(1, (int) ($c->barang->stok_minimum ?? 1)));
                        $avgInventory = max(1.0, $totalStock > 0 ? ($totalStock + $totalMinStock) / 2 : $totalMinStock);
                        $turnoverRate = ($totalSold / $periodMonths) / $avgInventory;
                        if ($totalSold > 0 && $turnoverRate < 0.1) {
                            $turnoverRate = 0.1;
                        }

                        $stockValue = $group->sum(function ($c) {
                            $sisa = (int) ($c->barang->stokTerakhir?->sisa_stok ?? 0);
                            $harga = (float) ($c->barang->harga_satuan ?? 0);
                            return $sisa * $harga;
                        });

                        if ($totalStock <= $totalMinStock) {
                            $statusLabel = 'Perlu Restock';
                            $statusBadge = 'badge-danger';
                        } elseif ($dominantCluster === 'Sangat Laris' || $turnoverRate >= 1.5) {
                            $statusLabel = 'Optimal';
                            $statusBadge = 'badge-success';
                        } elseif ($dominantCluster === 'Cukup' || $turnoverRate >= 0.8) {
                            $statusLabel = 'Stabil';
                            $statusBadge = 'badge-info';
                        } elseif ($totalStock > ($totalMinStock * 2)) {
                            $statusLabel = 'Stok Berlebih';
                            $statusBadge = 'badge-warning';
                        } else {
                            $statusLabel = 'Perputaran Lambat';
                            $statusBadge = 'badge-warning';
                        }
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $category }}</strong>
                            <small class="d-block muted">{{ $group->count() }} produk</small>
                        </td>
                        <td>
                            <span class="badge {{ $clusterBadge }}">{{ $dominantCluster }}</span>
                        </td>
                        <td>
                            <strong>{{ number_format($turnoverRate, 1, ',', '.') }}x</strong> <span class="muted">/ bln</span>
                        </td>
                        <td style="text-align: right;">
                            <strong>Rp {{ number_format($stockValue, 0, ',', '.') }}</strong>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center muted">Tidak ada kategori yang sesuai dengan pencarian atau filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="form-help px-4 mt-2">Turnover rate dihitung dari rasio rata-rata penjualan bulanan terhadap persediaan kategori, sedangkan nilai stok dihitung dari posisi sisa stok dikalikan harga satuan produk.</p>
</section>
