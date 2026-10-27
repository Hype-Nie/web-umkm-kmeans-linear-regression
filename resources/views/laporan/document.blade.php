<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page {
            margin: 22px 26px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #000000;
            line-height: 1.35;
            background: #ffffff;
        }

        .report-document {
            width: 100%;
        }

        .kop-surat {
            text-align: center;
            margin-bottom: 12px;
        }

        .kop-toko {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #000000;
            margin-bottom: 2px;
        }

        .kop-sub {
            font-size: 9.5px;
            font-weight: bold;
            color: #111111;
            margin-bottom: 2px;
        }

        .kop-alamat {
            font-size: 8.5px;
            color: #222222;
            margin-bottom: 2px;
        }

        .kop-kontak {
            font-size: 8px;
            color: #333333;
        }

        .kop-divider {
            border-top: 2.5px solid #000000;
            border-bottom: 1px solid #000000;
            height: 2px;
            margin-top: 6px;
            margin-bottom: 12px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .meta-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .summary-box, .report-summary-section {
            background-color: transparent;
            border: none;
            padding: 0;
            margin-bottom: 12px;
        }

        .chart-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1.5px solid #000000;
            margin-bottom: 14px;
        }

        .chart-bar-cell {
            vertical-align: bottom;
            text-align: center;
            padding: 0 1px;
            border: none;
        }

        .chart-bar {
            background-color: #2563eb;
            width: 80%;
            margin: 0 auto;
            border: 1px solid #2563eb;
            border-radius: 2px 2px 0 0;
        }

        .chart-label-cell {
            vertical-align: top;
            text-align: center;
            padding: 2px 0 0;
            border: none;
            font-size: 6px;
            color: #222222;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border: 1px solid #000000;
        }

        .report-table th {
            background-color: #2563eb;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: bold;
            text-align: left;
            padding: 5px 5px;
            border: 1px solid #333333;
            text-transform: uppercase;
        }

        .report-table td {
            padding: 4px 5px;
            font-size: 7.5px;
            border: 1px solid #666666;
            vertical-align: top;
            color: #000000;
        }

        .report-table tr {
            page-break-inside: avoid;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            page-break-inside: avoid;
        }

        .signature-table td {
            border: none;
            padding: 0;
        }
    </style>
</head>

<body>
    @include('laporan.content')
</body>

</html>
