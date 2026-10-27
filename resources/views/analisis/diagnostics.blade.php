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
        <table class="table">
            <thead><tr><th>Kategori Produk</th><th>Jumlah Produk</th><th>Unit Terjual</th><th>Frekuensi Pembelian</th><th>Distribusi Klaster</th></tr></thead>
            <tbody>
                @forelse ($categoryMetrics as $category => $group)
                    <tr>
                        <td><strong>{{ $category }}</strong></td>
                        <td>{{ $group->count() }} produk</td>
                        <td>{{ number_format($group->sum('fitur_jumlah_terjual'), 0, ',', '.') }}</td>
                        <td>{{ number_format($group->sum('fitur_frekuensi_pembelian'), 0, ',', '.') }}</td>
                        <td><div class="category-badges">@foreach ($group->countBy('label_klaster') as $label => $count)<span class="badge {{ $label === 'Sangat Laris' ? 'badge-success' : ($label === 'Cukup' ? 'badge-primary' : 'badge-warning') }}">{{ $label }} · {{ $count }}</span>@endforeach</div></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center muted">Tidak ada kategori yang sesuai dengan pencarian atau filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="form-help px-4 mt-2">Frekuensi merupakan jumlah kejadian pembelian per produk, bukan jumlah struk unik antarproduk.</p>
</section>
