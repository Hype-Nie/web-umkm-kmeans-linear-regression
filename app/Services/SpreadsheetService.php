<?php

namespace App\Services;

use DomainException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpreadsheetService
{
    public function read(string $path, string $extension): array
    {
        if ($extension === 'csv') {
            $handle = fopen($path, 'r');
            $sample = fgets($handle) ?: '';
            rewind($handle);
            $delimiter = substr_count($sample, ';') > substr_count($sample, ',') ? ';' : ',';
            $rows = [];
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rows[] = $row;
                if (count($rows) > 25001) {
                    fclose($handle);
                    throw new DomainException('Maksimal 25.000 baris per unggahan; pecah file menjadi beberapa bagian.');
                }
            }
            fclose($handle);
        } else {
            // Inspect the archive before expanding to limit compressed-file resource abuse.
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true) {
                throw new DomainException('Berkas XLSX tidak valid.');
            }
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
            }
            $zip->close();
            if ($size > 80 * 1024 * 1024) {
                throw new DomainException('Ukuran hasil dekompresi XLSX melebihi 80 MB.');
            }
            $reader = IOFactory::createReader('Xlsx');
            $info = $reader->listWorksheetInfo($path);
            if (! $info || $info[0]['totalRows'] > 25001 || $info[0]['totalColumns'] > 80) {
                throw new DomainException('Maksimal 25.000 baris dan 80 kolom.');
            }
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly($info[0]['worksheetName']);
            $book = $reader->load($path);
            $rows = $book->getSheet(0)->toArray(null, false, false, false);
            $book->disconnectWorksheets();
        }
        if (count($rows) < 2) {
            throw new DomainException('File memerlukan header dan minimal satu baris data.');
        }
        $headers = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), array_shift($rows));
        if (count($headers) > 80 || count(array_unique($headers)) !== count($headers) || in_array('', $headers, true)) {
            throw new DomainException('Header harus unik, tidak kosong, maksimal 80 kolom.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    public function download(array $headers, array $rows, string $name, string $format = 'xlsx', array $context = [])
    {
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows, $context) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                $metadata = $context ? [['Laporan', $context['title']], ['Nama toko', $context['shop']], ['Periode', $context['start'].' s.d. '.$context['end']], ['Dibuat', $context['generated']], ['Total / catatan', $context['total']], []] : [];
                foreach (array_merge($metadata, [$headers], $rows) as $row) {
                    fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^(?:\s*[=+@\-]|[\t\r\n])/', $v) ? "'".$v : $v, $row));
                }
                fclose($out);
            }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($headers, $rows, $context) {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Data');
            foreach (array_merge([$headers], $rows) as $i => $row) {
                foreach (array_values($row) as $j => $value) {
                    // Text explicitly prevents spreadsheet formula injection; native numeric outputs remain numeric.
                    $coordinate = Coordinate::stringFromColumnIndex($j + 1).($i + 1);
                    $numeric = is_int($value) || is_float($value);
                    if ($context && $context['type'] === 'penjualan' && $i > 0 && in_array($j, [4, 5]) && strlen(str_replace('.', '', (string) $value)) <= 15) {
                        $numeric = true;
                    }
                    $sheet->setCellValueExplicit($coordinate, $value ?? '', $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
            }
            $last = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle('A1:'.$last.'1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF161616');
            $sheet->getRowDimension(1)->setRowHeight(28);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:'.$last.max(1, count($rows) + 1));
            foreach (range(1, count($headers)) as $column) {
                $sheet->getColumnDimensionByColumn($column)->setWidth(26);
            }
            $sheet->getStyle('A1:'.$last.max(1, count($rows) + 1))->getAlignment()->setWrapText(true);
            $sheet->setShowGridlines(false);
            if ($context) {
                $summary = new Worksheet($book, 'Ringkasan');
                $book->addSheet($summary, 0);
                $meta = [[$context['title']], ['Nama Toko', $context['shop']], ['Pemilik', $context['owner']], ['Periode', $context['start'].' s.d. '.$context['end']], ['Dibuat (WIB)', $context['generated']], ['Total / Catatan', $context['total']], ['Jumlah Baris', count($rows)]];
                foreach ($meta as $i => $r) {
                    foreach ($r as $j => $v) {
                        $summary->setCellValueExplicit(Coordinate::stringFromColumnIndex($j + 1).($i + 1), $v, DataType::TYPE_STRING);
                    }
                }
                $summary->mergeCells('A1:M1');
                $summary->getStyle('A1:M1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
                $summary->getStyle('A1:M1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF161616');
                $summary->getRowDimension(1)->setRowHeight(36);
                $summary->getColumnDimension('A')->setWidth(25);
                $summary->getColumnDimension('B')->setWidth(45);
                $summary->getStyle('A2:B7')->getAlignment()->setWrapText(true);
                $summary->setShowGridlines(false);
                if (! empty($context['chart'])) {
                    $summary->setCellValue('A10', 'Tanggal');
                    $summary->setCellValue('B10', 'Pendapatan (Rp)');
                    $r = 11;
                    foreach ($context['chart'] as $day => $value) {
                        $summary->setCellValueExplicit('A'.$r, $day, DataType::TYPE_STRING);
                        $summary->setCellValue('B'.$r, (float) $value);
                        $r++;
                    }
                    $lastRow = $r - 1;
                    $labels = [new DataSeriesValues('String', "'Ringkasan'!\$B\$10", null, 1)];
                    $x = [new DataSeriesValues('String', "'Ringkasan'!\$A\$11:\$A\$$lastRow", null, $lastRow - 10)];
                    $y = [new DataSeriesValues('Number', "'Ringkasan'!\$B\$11:\$B\$$lastRow", null, $lastRow - 10)];
                    $series = new DataSeries(DataSeries::TYPE_LINECHART, null, [0], $labels, $x, $y);
                    $chart = new Chart('Pendapatan', new Title('Tren Pendapatan Penjualan'), null, new PlotArea(null, [$series]), true, 'gap', new Title('Tanggal'), new Title('Pendapatan (Rp)'));
                    $chart->setTopLeftPosition('D3');
                    $chart->setBottomRightPosition('M20');
                    $summary->addChart($chart);
                    $summary->getStyle('B11:B'.$lastRow)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
                }
                if ($context['type'] === 'penjualan' && $rows) {
                    $sheet->getStyle('E2:F'.(count($rows) + 1))->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
                }
                $book->setActiveSheetIndex(0);
            }
            $writer = new Xlsx($book);
            $writer->setIncludeCharts(true);
            $writer->save('php://output');
            $book->disconnectWorksheets();
        }, $name.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
