@extends('layouts.app')
@section('title', 'Rekomendasi Keputusan')
@section('content')
    <div class="page-head">
        <div>
            <h1>Rekomendasi Keputusan</h1>
            <p class="subtitle">Berdasarkan hasil analisis data transaksi periode
                {{ $process ? ($process->tanggal_mulai_data?->format('d/m/Y') . ' — ' . $process->tanggal_akhir_data?->format('d/m/Y')) : 'terbaru' }}.
            </p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('laporan.export', ['jenis' => 'rekomendasi', 'format' => 'pdf']) }}" title="Unduh Rekomendasi Format PDF">
                <x-icon name="file" /> Unduh PDF
            </a>
            <a class="btn btn-primary" href="{{ route('analisis.index') }}" title="Perbarui Analisis Data">
                <x-icon name="sync" /> Perbarui Analisis
            </a>
        </div>
    </div>

    @if ($stale)
        <div class="alert alert-warning">
            Data transaksi atau stok berubah sejak proses analisis terakhir. Disarankan memperbarui analisis sebelum menerapkan keputusan pengadaan.
        </div>
    @endif

    @if ($process)
        <div class="info-box mb-4 d-flex justify-content-between gap-3 flex-wrap align-items-center">
            <div>
                <strong>Proses Analisis #{{ $process->id_proses }} · {{ $process->sumber_proses === 'backend_php' ? 'Sistem SPK' : $process->sumber_proses }}</strong><br>
                Rentang Data: {{ $process->tanggal_mulai_data?->format('d/m/Y') }} — {{ $process->tanggal_akhir_data?->format('d/m/Y') }}
                · Dieksekusi: {{ $process->created_at->format('d/m/Y H:i') }} WIB
            </div>
            <form method="post" action="{{ route('rekomendasi.generate') }}" data-submit-lock>
                @csrf
                <input type="hidden" name="id_proses" value="{{ $process->id_proses }}">
                <button class="btn btn-primary" type="submit">Susun Rekomendasi</button>
            </form>
        </div>
    @endif

    <!-- Priority Chips matching Proposal Mockup -->
    @php
        $activePrioritas = request('prioritas');
        if ($activePrioritas === 'Sedang') $activePrioritas = 'Menengah';
    @endphp
    <div class="chips mb-3">
        <a class="chip {{ !$activePrioritas ? 'active' : '' }}"
            href="{{ route('rekomendasi.index', request()->except(['prioritas', 'page'])) }}">Semua Prioritas</a>
        <a class="chip {{ $activePrioritas === 'Tinggi' ? 'active' : '' }}"
            href="{{ route('rekomendasi.index', array_merge(request()->except('page'), ['prioritas' => 'Tinggi'])) }}">Prioritas Tinggi ({{ $counts['Tinggi'] ?? 0 }})</a>
        <a class="chip {{ $activePrioritas === 'Menengah' ? 'active' : '' }}"
            href="{{ route('rekomendasi.index', array_merge(request()->except('page'), ['prioritas' => 'Menengah'])) }}">Menengah ({{ $counts['Sedang'] ?? 0 }})</a>
        <a class="chip {{ $activePrioritas === 'Rendah' ? 'active' : '' }}"
            href="{{ route('rekomendasi.index', array_merge(request()->except('page'), ['prioritas' => 'Rendah'])) }}">Rendah ({{ $counts['Rendah'] ?? 0 }})</a>
    </div>

    <!-- Filter Form -->
    <form class="filters mb-4">
        <input type="hidden" name="prioritas" value="{{ request('prioritas') }}">
        <div>
            <label class="field" for="q">Pencarian</label>
            <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Cari rekomendasi / nama produk…">
        </div>
        <div>
            <label class="field" for="jenis">Jenis Tindakan</label>
            <select class="form-select" id="jenis" name="jenis">
                <option value="">Semua tindakan</option>
                <option value="restock" @selected(request('jenis') === 'restock')>RESTOCK</option>
                <option value="promosi" @selected(request('jenis') === 'promosi')>PROMOSI</option>
            </select>
        </div>
        <div>
            <label class="field" for="status">Status Keputusan</label>
            <select class="form-select" id="status" name="status">
                <option value="">Semua status</option>
                @foreach (['baru' => 'Baru', 'ditinjau' => 'Ditinjau', 'selesai' => 'Selesai'] as $key => $lbl)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn">Filter</button>
        <a href="{{ route('rekomendasi.index') }}" class="btn">Reset</a>
    </form>

    @if ($items->count() || $otherItems->count())
        @php
            $summary = $summary ?? [
                'total_rekomendasi' => method_exists($items, 'total') ? $items->total() : $items->count(),
                'tinggi_count' => $counts['Tinggi'] ?? $items->where('prioritas', 'Tinggi')->count(),
                'sedang_count' => $counts['Sedang'] ?? $items->where('prioritas', 'Sedang')->count(),
                'rendah_count' => $counts['Rendah'] ?? $items->where('prioritas', 'Rendah')->count(),
                'restock_count' => $items->where('jenis_rekomendasi', 'restock')->count(),
                'restock_units' => (int) $items->where('jenis_rekomendasi', 'restock')->sum('jumlah_restock_disarankan'),
                'promosi_count' => $items->where('jenis_rekomendasi', 'promosi')->count(),
                'avg_confidence' => $items->isNotEmpty() ? $items->first()->tingkat_keyakinan : '92.4%',
            ];

            $gridItems = $items;
        @endphp

        <!-- Executive Summary Card (Sorotan Strategis Agregat sesuai Gambar 3.22) -->
        @if (!$activePrioritas)
            <section class="card rec-summary-card mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="badge badge-primary">SOROTAN KEPUTUSAN STRATEGIS</span>
                    <small class="text-muted">RINGKASAN EKSEKUTIF SPK</small>
                </div>
                <h2 class="mt-2">Optimasi Inventori & Kebutuhan Restok Kritis</h2>
                <p class="description">
                    Berdasarkan hasil integrasi K-Means dan Linear Regression, sistem mendeteksi <strong>{{ $summary['tinggi_count'] }} komoditas prioritas tinggi</strong> yang memerlukan perhatian segera.
                    @if ($summary['restock_units'] > 0)
                        Kebutuhan pengadaan restok sebesar <strong>+{{ number_format($summary['restock_units']) }} unit</strong> dialokasikan pada <strong>{{ $summary['restock_count'] }} produk Fast-Moving</strong> guna mempertahankan ketersediaan barang dan mencegah potensi stockout.
                    @else
                        Tidak ada komoditas yang mengalami defisit stok kritis saat ini.
                    @endif
                    @if ($summary['promosi_count'] > 0)
                        Selain itu, teridentifikasi <strong>{{ $summary['promosi_count'] }} produk</strong> dengan potensi overstock / perputaran lambat yang direkomendasikan untuk evaluasi promosi atau penyesuaian pesanan.
                    @endif
                </p>
                <div class="metrics">
                    <div>
                        Estimasi Total Pengadaan
                        <strong>+{{ number_format($summary['restock_units']) }} Unit</strong>
                        <span class="text-muted" style="font-size:0.75rem;">({{ $summary['restock_count'] }} produk restok)</span>
                    </div>
                    <div>
                        Tingkat Keyakinan Model
                        <strong>{{ $summary['avg_confidence'] }}</strong>
                        <span class="text-muted" style="font-size:0.75rem;">(R² &amp; Silhouette)</span>
                    </div>
                    <div>
                        Komoditas Prioritas Tinggi
                        <strong>{{ $summary['tinggi_count'] }} Produk</strong>
                        <span class="text-muted" style="font-size:0.75rem;">(Perlu tindakan segera)</span>
                    </div>
                </div>
                <div class="rec-footer">
                    <span class="text-muted small">Keputusan didasarkan pada peramalan stok periode {{ $process?->tanggal_akhir_data?->copy()->addMonthNoOverflow()->format('m/Y') ?? 'berikutnya' }} dan kondisi stok riil gudang.</span>
                </div>
            </section>
        @endif

        <!-- Grid Kartu Tinjauan per Produk (Ukuran Seragam & Rapi) -->
        <div class="recommendations">
            @foreach ($gridItems as $index => $rec)
                @php
                    $pBadge = match($rec->prioritas) {
                        'Tinggi' => 'badge-priority-high',
                        'Sedang', 'Menengah' => 'badge-priority-mid',
                        default => 'badge-priority-low',
                    };
                    $sBadge = match($rec->status) {
                        'selesai' => 'badge-success',
                        'ditinjau' => 'badge-warning',
                        default => 'badge-primary',
                    };
                @endphp
                <article class="card recommendation">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <span class="badge {{ $pBadge }}">
                            PRIORITAS {{ strtoupper($rec->prioritas) }}
                        </span>
                        <small class="text-muted">ID: REK-{{ $rec->id_rekomendasi }}</small>
                    </div>

                    <h2 class="mt-2">{{ $rec->judul }}</h2>
                    <p class="description">{{ str_replace(';', ',', $rec->deskripsi) }}</p>

                    <!-- Metric Boxes (Estimasi Dampak & Tingkat Keyakinan) -->
                    <div class="metrics">
                        <div>
                            Estimasi Dampak
                            <strong>{{ $rec->estimasi_dampak }}</strong>
                        </div>
                        <div>
                            Tingkat Keyakinan
                            <strong>{{ $rec->tingkat_keyakinan }}</strong>
                        </div>
                        <div>
                            Stok Saat Ini
                            <strong>{{ $rec->stok_saat_analisis }} unit</strong>
                        </div>
                    </div>

                    <div class="rec-footer">
                        <span class="badge {{ $sBadge }}">
                            {{ strtoupper($rec->status) }}
                        </span>

                        <div class="d-flex align-items-center gap-2">
                            @if ($rec->status !== 'selesai')
                                <form method="post" action="{{ route('rekomendasi.update', $rec) }}" class="d-inline">
                                    @csrf
                                    @method('patch')
                                    <input type="hidden" name="status" value="selesai">
                                    <button class="btn btn-primary btn-sm" type="submit" title="Tandai rekomendasi selesai diterapkan">
                                        Terapkan Keputusan <x-icon name="arrow" />
                                    </button>
                                </form>
                            @endif

                            <form method="post" action="{{ route('rekomendasi.update', $rec) }}" class="actions d-inline">
                                @csrf
                                @method('patch')
                                <select class="form-select form-select-sm" name="status"
                                    onchange="this.form.submit()"
                                    aria-label="Ubah status {{ $rec->barang->nama_barang }}" style="min-width:96px">
                                    @foreach (['baru' => 'Baru', 'ditinjau' => 'Ditinjau', 'selesai' => 'Selesai'] as $key => $label)
                                        <option value="{{ $key }}" @selected($rec->status === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="pagination-wrap mt-4">{{ $items->links() }}</div>
    @else
        <x-empty
            title="Belum ada rekomendasi yang sesuai"
            message="Jalankan analisis, pastikan prediksi dan snapshot stok tersedia, lalu pilih Susun Rekomendasi. Produk tanpa kebutuhan tindakan tidak diberi rekomendasi buatan.">
            <a class="btn" href="{{ route('analisis.index') }}">Lihat Analisis</a>
        </x-empty>
    @endif

    @include('rekomendasi.others')

    <details class="card mt-4">
        <summary>Aturan Rekomendasi & Prioritas</summary>
        <p class="form-help mt-3">RESTOCK = max(0, ceil(prediksi + stok minimum − stok terkini)), jika jumlah &gt; 0 dan
            barang Sangat Laris atau stok kritis. Tinggi: stok ≤ minimum dan di bawah prediksi, Sedang: stok di bawah
            prediksi, lainnya Rendah.</p>
        <p class="form-help">PROMOSI dipertimbangkan jika stok &gt; 0 dan klaster Kurang atau stok &gt; 2 × (prediksi +
            minimum), setelah aturan restock diperiksa. Tinggi: stok &gt; 2 × (prediksi + minimum), Sedang: klaster Kurang,
            lainnya Rendah. Tidak menjamin keuntungan.</p>
        <p class="form-help mb-0">Satu proses menghasilkan satu set rekomendasi yang dapat diaudit. Status ditinjau/selesai
            tidak melakukan pembelian, mengubah stok, atau mencatat penjualan. Untuk menghitung ulang berdasarkan perubahan
            stok, buat proses analisis baru.</p>
    </details>
@endsection
