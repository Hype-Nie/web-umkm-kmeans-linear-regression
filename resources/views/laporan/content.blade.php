<div class="report-document">
    <!-- Kop Surat Resmi UD. Zufri (Hitam Putih Standar Skripsi/Resmi) -->
    <div class="kop-surat">
        <table style="width:100%; border:none; border-collapse:collapse; text-align:center; margin-bottom:0;">
            <tr>
                <td style="border:none; padding:0; text-align:center;">
                    <div class="kop-toko" style="font-size:16px; font-weight:bold; letter-spacing:1px; color:#000000; margin-bottom:2px;">
                        {{ strtoupper($report['shop']) }}
                    </div>
                    <div class="kop-sub" style="font-size:10px; font-weight:bold; color:#111111; margin-bottom:2px; letter-spacing:0.5px;">
                        SISTEM PENDUKUNG KEPUTUSAN STRATEGIS STOK DAN ANALISIS PENJUALAN
                    </div>
                    <div class="kop-alamat" style="font-size:9px; color:#222222; margin-bottom:2px;">
                        Jl. Merdeka No. 12, Padangsidimpuan Utara, Kota Padangsidimpuan, Sumatera Utara 22711
                    </div>
                    <div class="kop-kontak" style="font-size:8.5px; color:#333333;">
                        Email: {{ auth()->user()->email ?? 'admin@udzufri.test' }} &nbsp;·&nbsp; Penanggung Jawab: {{ $report['owner'] }}
                    </div>
                </td>
            </tr>
        </table>
        <div class="kop-divider" style="border-top:2.5px solid #000000; border-bottom:1px solid #000000; height:2px; margin-top:8px; margin-bottom:14px;"></div>
    </div>

    <!-- Metadata Judul & Periode Laporan -->
    <div class="report-meta-box">
        <table class="meta-table" style="width:100%; border:none; border-collapse:collapse; margin-bottom:12px;">
            <tr>
                <td style="border:none; padding:0; vertical-align:top; width:62%; text-align:left;">
                    <div style="font-size:13px; font-weight:bold; text-transform:uppercase; color:#000000; margin-bottom:3px; letter-spacing:0.5px;">
                        LAPORAN {{ $report['title'] }}
                    </div>
                    <div style="font-size:9.5px; color:#111111;">
                        <strong>Periode Data:</strong> {{ \Carbon\Carbon::parse($report['start'])->translatedFormat('d F Y') }} s/d {{ \Carbon\Carbon::parse($report['end'])->translatedFormat('d F Y') }}
                    </div>
                </td>
                <td style="border:none; padding:0; vertical-align:top; text-align:right; font-size:9px; color:#333333;">
                    <div><strong>Waktu Cetak:</strong> {{ $report['generated'] }}</div>
                    <div style="margin-top:2px;"><strong>Dokumen:</strong> SPK-UDZ-{{ strtoupper(substr($report['type'], 0, 3)) }}-{{ now()->format('Ymd') }}</div>
                </td>
            </tr>
        </table>
    </div>

    @if ($report['summary'])
        <div class="report-summary-section" style="margin-bottom:14px; padding:0;">
            <div style="font-size:9.5px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; color:#000000; margin-bottom:3px; border-bottom:1px solid #000000; padding-bottom:3px;">
                I. Ringkasan Eksekutif
            </div>
            <div style="font-size:9.5px; font-weight:bold; color:#000000; margin-top:4px;">
                {{ $report['total'] }}
            </div>
            <div style="font-size:8.5px; color:#333333; margin-top:2px;">
                Total data terlampir: {{ number_format(count($report['rows']), 0, ',', '.') }} baris entri toko {{ $report['shop'] }}.
            </div>
        </div>
    @endif

    @if (count($report['chart']))
        @php
            $rawValues = array_map('floatval', array_values($report['chart']));
            $chartMax = count($rawValues) ? max($rawValues) : 1;
            if ($chartMax <= 0) {
                $chartMax = 1;
            }
        @endphp
        <div class="chart-section" style="margin-bottom:18px;">
            <div style="font-size:9.5px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; color:#000000; margin-bottom:6px;">
                Grafik Tren Pendapatan Penjualan
            </div>
            <table class="chart-table" style="width:100%; border-collapse:collapse; border-bottom:1.5px solid #000000; margin-bottom:10px;">
                <tr style="height:70px; vertical-align:bottom;">
                    @foreach ($report['chart'] as $periodKey => $value)
                        @php
                            $barHeight = max(4, round((60 * (float) $value) / $chartMax));
                        @endphp
                        <td class="chart-bar-cell" style="vertical-align:bottom; text-align:center; padding:0 1px; border:none;" title="{{ $periodKey }}: {{ \App\Support\Money::format($value) }}">
                            <div class="chart-bar" style="background-color:#000000; width:80%; max-width:24px; min-width:6px; margin:0 auto; height:{{ $barHeight }}px; border:1px solid #000000;"></div>
                        </td>
                    @endforeach
                </tr>
                <tr style="height:20px; vertical-align:top;">
                    @foreach ($report['chart'] as $periodKey => $value)
                        <td class="chart-label-cell" style="vertical-align:top; text-align:center; padding:3px 0 0; border:none; font-size:6.5px; color:#222222;">
                            {{ $periodKey }}
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

    <div class="table-section">
        <div style="font-size:9.5px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; color:#000000; margin-bottom:6px;">
            {{ $report['summary'] ? 'II.' : 'I.' }} Detail Data Laporan
        </div>

        @php
            $displayLimit = 150;
            $totalRows = count($report['rows']);
            $isCapped = $totalRows > $displayLimit;
            $visibleRows = $isCapped ? array_slice($report['rows'], 0, $displayLimit) : $report['rows'];
        @endphp

        <table class="report-table" style="width:100%; border-collapse:collapse; margin-bottom:14px; border:1px solid #000000;">
            <thead>
                <tr style="background-color:#000000; color:#ffffff;">
                    @foreach ($report['headers'] as $header)
                        <th style="padding:5px 6px; font-size:8px; font-weight:bold; text-align:left; border:1px solid #333333; text-transform:uppercase;">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($visibleRows as $row)
                    <tr style="background-color: #ffffff; color:#000000;">
                        @foreach ($row as $i => $cell)
                            @php
                                $headerName = $report['headers'][$i] ?? '';
                                $isNumericCell = is_numeric($cell) && !in_array($headerName, ['No', 'Kode', 'Kode Barang', 'Proses / Sumber']);
                            @endphp
                            <td style="padding:4px 6px; font-size:8px; border:1px solid #666666; vertical-align:top; @if($isNumericCell) text-align:right; @endif">
                                @if ($report['type'] === 'penjualan' && in_array($i, [4, 5]))
                                    {{ \App\Support\Money::format($cell) }}
                                @elseif ($headerName === 'Klaster')
                                    <strong>{{ $cell }}</strong>
                                @elseif ($headerName === 'Prioritas')
                                    <strong>{{ $cell }}</strong>
                                @else
                                    {{ $cell }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($report['headers']) }}" style="padding:20px; text-align:center; font-size:9px; color:#444444;">
                            Belum ada data pada filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($isCapped)
            <div style="background-color:#ffffff; border:1px solid #000000; color:#000000; padding:7px 10px; font-size:8.5px; border-radius:3px; margin-bottom:14px;">
                <strong>Catatan Pratinjau Dokumen:</strong> Menampilkan {{ $displayLimit }} baris data transaksi teratas untuk menjaga performa cetak dan kerapian kertas. Seluruh {{ number_format($totalRows, 0, ',', '.') }} baris transaksi lengkap tersedia melalui tombol <strong>Unduh Excel / CSV</strong>.
            </div>
        @endif
    </div>

    <!-- Lembar Pengesahan / Tanda Tangan Formal -->
    <table class="signature-table" style="width:100%; border:none; border-collapse:collapse; margin-top:20px; page-break-inside:avoid;">
        <tr>
            <td style="width:65%; border:none;"></td>
            <td style="width:35%; border:none; text-align:center;">
                <div style="font-size:9px; color:#222222; margin-bottom:3px;">
                    Padangsidimpuan, {{ now()->translatedFormat('d F Y') }}
                </div>
                <div style="font-size:9.5px; font-weight:bold; color:#000000; margin-bottom:45px;">
                    Mengetahui,<br>Pemilik {{ $report['shop'] }}
                </div>
                <div style="font-size:9.5px; font-weight:bold; text-decoration:underline; color:#000000;">
                    {{ $report['owner'] }}
                </div>
                <div style="font-size:8px; color:#333333; margin-top:2px;">
                    NPM. 2204190001 (UMTS)
                </div>
            </td>
        </tr>
    </table>

    <table style="width:100%; border:none; border-top:1px solid #666666; margin-top:18px; padding-top:6px; font-size:7.5px; color:#333333;">
        <tr>
            <td style="border:none; padding:0; text-align:left;">
                Sistem Pendukung Keputusan Strategis Stok dan Analisis Penjualan UMKM · UD. Zufri
            </td>
            <td style="border:none; padding:0; text-align:right;">
                Dokumen Resmi Terverifikasi Sistem
            </td>
        </tr>
    </table>
</div>
