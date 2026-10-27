<?php

namespace App\Http\Controllers;

use App\Services\AnalysisService;
use App\Services\RapidMinerService;
use App\Services\SpreadsheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnalysisController extends Controller
{
    public function index(Request $request, AnalysisService $service)
    {
        $request->validate(['proses' => 'nullable|integer', 'kategori' => 'nullable|string|max:120', 'klaster' => 'nullable|in:Sangat Laris,Cukup,Kurang']);
        $process = $request->filled('proses') ? $request->user()->proses()->findOrFail($request->proses) : $request->user()->proses()->latest('id_proses')->first();
        $clusters = $process?->klaster()->with('barang.stokTerakhir')->get() ?? collect();
        $forecasts = $process?->prediksi()->with('barang')->get() ?? collect();
        $analyzedProducts = $clusters->pluck('barang')->merge($forecasts->pluck('barang'))->unique('id_barang')->values();
        $metadata = $process ? json_decode($process->catatan ?? '', true) : null;
        $stale = $process && isset($metadata['data_signature']) && $metadata['data_signature'] !== $service->signature($request->user());
        $diagnostics = ['points' => [], 'reason' => 'Jalankan analisis terbaru untuk melihat evaluasi pembanding.'];
        if ($process?->status === 'berhasil' && ! $stale && $process->sumber_proses === 'backend_php' && isset($metadata['data_signature'])) {
            $diagnostics = \Illuminate\Support\Facades\Cache::remember('cluster-diagnostics-v1-'.$process->id_proses.'-'.$metadata['data_signature'], 3600, function () use ($request, $process) {
                $products = $request->user()->barang()->with(['transaksi' => fn ($q) => $q->periode($process->tanggal_mulai_data->toDateString(), $process->tanggal_akhir_data->toDateString())])->orderBy('id_barang')->get();
                $features = $products->map(fn ($p) => [
                    (int) $p->transaksi->sum('jumlah_terjual'),
                    $p->transaksi->map(fn ($t) => $t->tanggal_transaksi->format('Y-m-d').'|'.($t->kode_transaksi ?: $t->tanggal_transaksi->format('H:i:s')))->unique()->count(),
                    (float) $p->transaksi->sum('total_pendapatan'),
                ])->all();
                return app(\App\Services\ClusterDiagnosticsService::class)->calculate($features);
            });
        }
        $categoryMetrics = $clusters->groupBy(fn ($c) => $c->barang->kategori ?: 'Tanpa kategori')
            ->filter(fn ($group, $category) => ! $request->filled('kategori') || mb_stripos($category, $request->kategori) !== false)
            ->map(fn ($group) => $group->filter(fn ($c) => ! $request->filled('klaster') || $c->label_klaster === $request->klaster))
            ->filter(fn ($group) => $group->isNotEmpty());
        $selected = $request->input('produk');
        $forecast = $selected ? $forecasts->firstWhere('id_barang', (int) $selected) : $forecasts->first();

        $maxTrx = \App\Models\TransaksiPenjualan::whereHas('barang', fn ($q) => $q->where('id_user', $request->user()->id_user))->max('tanggal_transaksi');
        $minTrx = \App\Models\TransaksiPenjualan::whereHas('barang', fn ($q) => $q->where('id_user', $request->user()->id_user))->min('tanggal_transaksi');
        $defaultStart = $minTrx ? \Carbon\Carbon::parse($minTrx)->startOfMonth()->toDateString() : '2024-10-01';
        $defaultEnd = $maxTrx ? \Carbon\Carbon::parse($maxTrx)->endOfMonth()->toDateString() : '2026-07-31';

        return view('analisis.index', compact('process', 'clusters', 'forecasts', 'analyzedProducts', 'metadata', 'stale', 'forecast', 'defaultStart', 'defaultEnd', 'diagnostics', 'categoryMetrics') + ['history' => $request->user()->proses()->latest('id_proses')->paginate(8)]);
    }

    public function run(Request $request, AnalysisService $service)
    {
        $data = $request->validate(['mulai' => 'required|date_format:Y-m-d', 'akhir' => 'required|date_format:Y-m-d|after_or_equal:mulai|before_or_equal:today', 'complete' => 'nullable|boolean']);
        $process = $service->run($request->user(), $data['mulai'], $data['akhir'], $request->boolean('complete'));

        return redirect()->route('analisis.index', ['proses' => $process->id_proses])->with($process->status === 'berhasil' ? 'success' : 'warning', $process->status === 'berhasil' ? 'Analisis tersimpan. Periksa cakupan prediksi per produk.' : 'Analisis belum berhasil. Lihat alasan pada riwayat proses.');
    }

    public function rapidminer(Request $request, SpreadsheetService $sheets, RapidMinerService $service)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,csv,txt|max:10240', 'confirmed' => 'accepted']);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'xlsx'])) {
            return back()->withErrors(['file' => 'Gunakan berkas CSV atau XLSX.']);
        }
        try {
            $data = $sheets->read($file->getRealPath(), $ext);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e instanceof \DomainException ? $e->getMessage() : 'Berkas tidak dapat dibaca.']);
        }
        $path = 'rapidminer/'.$request->user()->id_user.'/'.hash_file('sha256', $file->getRealPath()).'.'.$ext;
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
        $process = $service->import($request->user(), $data, $file->getClientOriginalName());
        $meta = json_decode($process->catatan ?? '', true);
        if (is_array($meta)) {
            $meta['path_bukti'] = $path;
            $process->update(['catatan' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);
        }

        return redirect()->route('analisis.index', ['proses' => $process->id_proses])->with($process->status === 'berhasil' ? 'success' : 'warning', 'Hasil RapidMiner berstatus '.$process->status.'. Periksa rincian proses.');
    }

    public function template(Request $request, SpreadsheetService $sheets)
    {
        $request->validate(['format' => 'nullable|in:csv,xlsx']);

        return $sheets->download(RapidMinerService::HEADERS, [], 'template-hasil-rapidminer', $request->input('format', 'xlsx'));
    }

    public function dataset(Request $request, SpreadsheetService $sheets)
    {
        $request->validate(['proses' => 'required|integer']);
        $p = $request->user()->proses()->findOrFail($request->proses);
        $products = $request->user()->barang()->with(['transaksi' => fn ($q) => $q->whereBetween('tanggal_transaksi', [$p->tanggal_mulai_data->toDateString().' 00:00:00', $p->tanggal_akhir_data->toDateString().' 23:59:59'])])->get();
        $rows = [];
        foreach ($products as $product) {
            foreach ($product->transaksi as $t) {
                $rows[] = [$product->kode_barang, $product->nama_barang, $t->kode_transaksi, $t->tanggal_transaksi->format('Y-m-d H:i:s'), $t->jumlah_terjual, $t->harga_satuan_jual, $t->total_pendapatan];
            }
        }

        return $sheets->download(['kode_barang', 'nama_barang', 'kode_transaksi', 'tanggal_transaksi', 'jumlah_terjual', 'harga_satuan_jual', 'total_pendapatan'], $rows, 'dataset-rapidminer', 'csv');
    }
}
