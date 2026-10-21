@extends('layouts.app')
@section('title', 'Cetak Laporan')
@section('content')<div class="page-head">
        <div>
            <h1>Cetak Laporan</h1>
            <p class="subtitle">Konfigurasikan periode, tinjau laporan, lalu unduh atau cetak.</p>
        </div>
    </div>
    <div class="report-layout">
        <aside class="stack report-config">
            <section class="card">
                <h2 class="mb-4">Konfigurasi Laporan</h2>
                <form method="get" action="{{ route('laporan.index') }}">
                    <div class="field-row"><label class="field" for="jenis">Jenis Laporan</label><select
                            class="form-select" name="jenis" id="jenis">
                            @foreach ($types as $key => $title)
                                <option value="{{ $key }}" @selected($report['type'] === $key)>{{ $title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if (!empty($presets))
                        <div class="field-row mb-3">
                            <label class="field mb-1">Preset Periode Cepat</label>
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-sm {{ ($report['start'] === $presets['latest_month']['start'] && $report['end'] === $presets['latest_month']['end']) ? 'btn-primary' : '' }}"
                                    onclick="document.getElementById('mulai').value='{{ $presets['latest_month']['start'] }}';document.getElementById('akhir').value='{{ $presets['latest_month']['end'] }}';this.form.submit();"
                                    title="1 bulan data transaksi terakhir">Bulan Terakhir</button>
                                <button type="button" class="btn btn-sm {{ ($report['start'] === $presets['this_month']['start'] && $report['end'] === $presets['this_month']['end']) ? 'btn-primary' : '' }}"
                                    onclick="document.getElementById('mulai').value='{{ $presets['this_month']['start'] }}';document.getElementById('akhir').value='{{ $presets['this_month']['end'] }}';this.form.submit();"
                                    title="Bulan kalender berjalan">Bulan Ini</button>
                                <button type="button" class="btn btn-sm {{ ($report['start'] === $presets['this_year']['start'] && $report['end'] === $presets['this_year']['end']) ? 'btn-primary' : '' }}"
                                    onclick="document.getElementById('mulai').value='{{ $presets['this_year']['start'] }}';document.getElementById('akhir').value='{{ $presets['this_year']['end'] }}';this.form.submit();"
                                    title="Tahun kalender berjalan">Tahun Ini</button>
                                <button type="button" class="btn btn-sm {{ ($report['start'] === $presets['all']['start'] && $report['end'] === $presets['all']['end']) ? 'btn-primary' : '' }}"
                                    onclick="document.getElementById('mulai').value='{{ $presets['all']['start'] }}';document.getElementById('akhir').value='{{ $presets['all']['end'] }}';this.form.submit();"
                                    title="Seluruh rentang data sejak 2024">Semua Data</button>
                            </div>
                        </div>
                    @endif

                    <div class="field-row"><label class="field" for="mulai">Dari Tanggal</label><input
                            class="form-control" type="date" name="mulai" id="mulai" value="{{ $report['start'] }}"
                            required></div>
                    <div class="field-row"><label class="field" for="akhir">Sampai Tanggal</label><input
                            class="form-control" type="date" name="akhir" id="akhir" value="{{ $report['end'] }}"
                            required></div><input type="hidden" name="summary" value="0"><label
                        class="form-check d-flex gap-2 mb-4"><input class="form-check-input" type="checkbox" name="summary"
                            value="1" @checked($report['summary'])><span class="form-check-label">Sertakan Ringkasan
                            Eksekutif</span></label><button class="btn btn-primary btn-wide">Perbarui Preview</button>
                </form>
                <hr class="my-4"><label class="field">Unduh Laporan</label>
                <div class="actions mb-3">
                    @foreach (['pdf' => 'PDF', 'xlsx' => 'Excel', 'csv' => 'CSV'] as $format => $label)
                        <a class="btn {{ $format === 'pdf' ? 'btn-primary' : '' }}"
                            href="{{ route('laporan.export', ['jenis' => $report['type'], 'mulai' => $report['start'], 'akhir' => $report['end'], 'summary' => (int) $report['summary'], 'format' => $format]) }}"><x-icon
                                name="download" />{{ $label }}</a>
                    @endforeach
                </div><button class="btn btn-primary btn-wide" data-print type="button"><x-icon name="print" />Cetak
                    Laporan</button>
            </section>
            <div class="info-box"><x-icon name="bulb" /> Laporan dihasilkan dari data akun
                {{ auth()->user()->nama_toko }} yang tersimpan di database. Stok adalah posisi terakhir sampai akhir
                periode; tanggal pembaruan ditampilkan per produk.</div>
        </aside>
        <section class="report-viewport">
            <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                <h2 class="mb-0">Pratinjau Laporan</h2>
                <div class="actions"><button class="btn btn-sm" data-zoom="-0.1"
                        aria-label="Perkecil laporan">−</button><button class="btn btn-sm" data-zoom="0.1"
                        aria-label="Perbesar laporan">+</button></div>
            </div>
            <div class="paper-stage">
                <div class="paper">@include('laporan.content')</div>
            </div>
        </section>
</div>@endsection
