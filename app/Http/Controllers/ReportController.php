<?php

namespace App\Http\Controllers;

use App\Http\Requests\PeriodeRequest;
use App\Services\ReportService;
use App\Services\SpreadsheetService;
use Dompdf\Dompdf;
use Dompdf\Options;

class ReportController extends Controller
{
    private function data(PeriodeRequest $request, ReportService $service): array
    {
        $request->validate(['jenis' => 'nullable|in:penjualan,stok,kmeans,prediksi,rekomendasi', 'format' => 'nullable|in:pdf,xlsx,csv', 'summary' => 'nullable|boolean', 'detail' => 'nullable|boolean', 'raw' => 'nullable|boolean']);
        [$start,$end] = $request->periode('month');

        return $service->build($request->user(), $request->input('jenis', 'penjualan'), $start, $end, $request->boolean('summary', true), $request->boolean('detail', true), $request->boolean('raw'));
    }

    public function index(PeriodeRequest $request, ReportService $service)
    {
        $user = $request->user();
        $minDate = \App\Models\TransaksiPenjualan::whereHas('barang', fn ($q) => $q->where('id_user', $user->id_user))->min('tanggal_transaksi');
        $maxDate = \App\Models\TransaksiPenjualan::whereHas('barang', fn ($q) => $q->where('id_user', $user->id_user))->max('tanggal_transaksi');

        $presets = [
            'latest_month' => [
                'start' => $maxDate ? \Carbon\Carbon::parse($maxDate)->startOfMonth()->toDateString() : now()->startOfMonth()->toDateString(),
                'end' => $maxDate ? \Carbon\Carbon::parse($maxDate)->endOfMonth()->toDateString() : now()->endOfMonth()->toDateString(),
            ],
            'this_month' => [
                'start' => now()->startOfMonth()->toDateString(),
                'end' => now()->endOfMonth()->toDateString(),
            ],
            'this_year' => [
                'start' => now()->startOfYear()->toDateString(),
                'end' => now()->endOfYear()->toDateString(),
            ],
            'all' => [
                'start' => $minDate ? \Carbon\Carbon::parse($minDate)->startOfMonth()->toDateString() : '2024-10-01',
                'end' => $maxDate ? \Carbon\Carbon::parse($maxDate)->toDateString() : now()->toDateString(),
            ],
        ];

        return view('laporan.index', [
            'report' => $this->data($request, $service),
            'types' => ReportService::TYPES,
            'presets' => $presets,
        ]);
    }

    public function export(PeriodeRequest $request, ReportService $service, SpreadsheetService $sheets)
    {
        $data = $this->data($request, $service);
        $format = $request->input('format', 'pdf');
        if ($format !== 'pdf') {
            return $sheets->download($data['headers'], $data['rows'], 'laporan-'.$data['type'], $format, $data);
        }
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', storage_path('framework/cache'));
        $options->set('fontCache', storage_path('framework/cache'));
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('laporan.document', ['report' => $data, 'pdf' => true])->render(), 'UTF-8');
        $pdf->setPaper('A4', count($data['headers']) > 6 ? 'landscape' : 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="laporan-'.$data['type'].'.pdf"']);
    }
}
