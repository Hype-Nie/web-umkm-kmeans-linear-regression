@extends('layouts.app')
@section('title', 'Cetak Laporan')
@section('content')
    <style>
        .report-layout { display: grid; grid-template-columns: 285px minmax(0, 1fr); gap: 24px; }
        .report-config { gap: 16px; }
        .report-config .card { padding: 22px 20px; border-radius: 12px; }
        .report-config h2 { font-size: 14.5px; font-weight: 700; color: #0f172a; margin: 0 0 18px; }
        .report-config .field-row { margin-bottom: 16px; }
        .report-config label.field,
        .report-config legend.field { display: block; float: none; width: auto; padding: 0; font-size: 12px; font-weight: 500; color: #64748b; margin-bottom: 6px; letter-spacing: 0; }
        .report-date-fields { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 10px; }
        .report-date-fields input.form-control { min-width: 0; padding: 8px 8px; font-size: 12px; width: 100%; }
        .report-format { border: 0; padding: 0; margin: 0 0 16px; min-width: 0; }
        .report-format-options { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
        .report-format-input { position: absolute; opacity: 0; width: 1px; height: 1px; pointer-events: none; }
        .report-format-options label { border: 1px solid #cbd5e1; border-radius: 6px; text-align: center; padding: 7px 8px; font-size: 12.5px; font-weight: 600; color: #0f172a; cursor: pointer; background: #ffffff; margin: 0; transition: all 0.15s ease; }
        .report-format-options label:hover { border-color: #94a3b8; background: #f8fafc; }
        .report-format-input:checked + label { color: #ffffff; background: var(--accent); border-color: var(--accent); }
        .report-format-input:focus-visible + label { outline: 3px solid var(--accent-border); outline-offset: 2px; }
        .report-options { display: grid; gap: 10px; margin: 18px 0 22px; }
        .report-options .form-check { display: flex; align-items: center; gap: 9px; padding-left: 0; margin: 0; font-size: 12.5px; font-weight: 500; color: #334155; cursor: pointer; min-height: 0; }
        .report-options .form-check-input { margin: 0; float: none; width: 15px; height: 15px; flex-shrink: 0; cursor: pointer; border-color: #94a3b8; }
        .report-options .form-check-input:checked { background-color: var(--accent); border-color: var(--accent); }
        .report-config-actions { display: grid; gap: 10px; }
        .report-config-actions .btn { min-height: 42px; border-radius: 7px; font-size: 13px; font-weight: 600; }
        .report-config-actions .btn-report-print { background: var(--accent); border-color: var(--accent); color: #ffffff; }
        .report-config-actions .btn-report-print:hover { background: var(--accent-hover); border-color: var(--accent-hover); color: #ffffff; }
        .report-config-actions .btn-report-download { background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; }
        .report-config-actions .btn-report-download:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }
        .report-info-note { display: flex; align-items: flex-start; gap: 10px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; font-size: 12px; line-height: 1.5; color: #64748b; }
        .report-info-note .icon { width: 18px; height: 18px; color: #0f172a; flex-shrink: 0; margin-top: 1px; }
        @media (max-width: 800px) { .report-layout { grid-template-columns: 1fr; } }
    </style>
    <div class="page-head"><div><h1>Cetak Laporan</h1><p class="subtitle">Konfigurasikan periode, tinjau laporan, lalu unduh atau cetak.</p></div></div>
    <div class="report-layout">
        <aside class="stack report-config">
            <section class="card">
                <h2>Konfigurasi Laporan</h2>
                <form method="get" action="{{ route('laporan.index') }}" data-report-config data-export-url="{{ route('laporan.export') }}">
                    <div class="field-row">
                        <label class="field" for="jenis">Jenis Laporan</label>
                        <select class="form-select" name="jenis" id="jenis">
                            @foreach ($types as $key => $title)<option value="{{ $key }}" @selected($report['type'] === $key)>{{ $title }}</option>@endforeach
                        </select>
                    </div>
                    <div class="report-date-fields">
                        <div class="field-row"><label class="field" for="mulai">Dari Tanggal</label><input class="form-control" type="date" name="mulai" id="mulai" value="{{ $report['start'] }}" required></div>
                        <div class="field-row"><label class="field" for="akhir">Sampai Tanggal</label><input class="form-control" type="date" name="akhir" id="akhir" value="{{ $report['end'] }}" min="{{ $report['start'] }}" required></div>
                    </div>
                    <fieldset class="field-row report-format">
                        <legend class="field">Format File</legend>
                        <div class="report-format-options">
                            @foreach (['pdf' => 'PDF', 'xlsx' => 'Excel', 'csv' => 'CSV'] as $format => $label)
                                <input type="radio" class="report-format-input" name="format" id="format-{{ $format }}" value="{{ $format }}" @checked(request('format', 'pdf') === $format)>
                                <label for="format-{{ $format }}">{{ $label }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="report-options">
                        @foreach (['summary' => ['Sertakan Ringkasan Eksekutif', $report['summary']], 'detail' => ['Tampilkan Detail Transaksi', $report['detail']], 'raw' => ['Sertakan Lampiran Data Mentah', $report['raw']]] as $name => [$label, $checked])
                            <input type="hidden" name="{{ $name }}" value="0">
                            <label class="form-check"><input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" @checked($checked)><span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                    <div class="report-config-actions">
                        <button class="btn btn-primary btn-report-print btn-wide" data-report-print type="button"><x-icon name="print" /> Cetak Laporan</button>
                        <button class="btn btn-report-download btn-wide" type="submit" formaction="{{ route('laporan.export') }}" data-report-download><x-icon name="download" /> Unduh Draft</button>
                    </div>
                    <p class="visually-hidden" data-report-status role="status" aria-live="polite">Pratinjau diperbarui otomatis.</p>
                    <noscript><p class="form-help">Aktifkan JavaScript untuk pratinjau otomatis.</p><button class="btn" type="submit">Tampilkan Laporan</button></noscript>
                </form>
            </section>
            <div class="report-info-note">
                <x-icon name="info" />
                <span>Laporan ini dihasilkan berdasarkan parameter data terkini yang tersedia di server DSS.</span>
            </div>
        </aside>
        <section class="report-viewport">
            <div class="d-flex justify-content-between align-items-center mb-3 no-print"><h2 class="mb-0">Pratinjau Laporan</h2><div class="actions"><button class="btn btn-sm" data-zoom="-0.1" aria-label="Perkecil laporan">−</button><button class="btn btn-sm" data-zoom="0.1" aria-label="Perbesar laporan">+</button></div></div>
            <div class="paper-stage"><div class="paper">@include('laporan.content')</div></div>
        </section>
    </div>
@endsection
