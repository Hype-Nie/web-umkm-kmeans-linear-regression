<?php

namespace App\Http\Controllers;

use App\Http\Requests\BarangRequest;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BarangController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:191', 'sort' => 'nullable|in:nama_barang,kode_barang,harga_satuan,created_at', 'direction' => 'nullable|in:asc,desc', 'kategori' => 'nullable|string|max:120', 'kritis' => 'nullable|boolean']);
        $query = $request->user()->barang()
            ->with('stokTerakhir')
            ->search($request->input('q'))
            ->kategori($request->input('kategori'))
            ->kritis($request->boolean('kritis'));

        return view('barang.index', ['items' => $query->orderBy($request->input('sort', 'nama_barang'), $request->input('direction', 'asc'))->paginate(15)->withQueryString(), 'categories' => $request->user()->barang()->whereNotNull('kategori')->distinct()->pluck('kategori')]);
    }

    public function create()
    {
        return view('barang.form', ['item' => null]);
    }

    public function store(BarangRequest $request)
    {
        $item = $request->user()->barang()->create($request->validated());

        return redirect()->route('barang.edit', $item)->with('success', 'Barang disimpan. Catat stok awal di bagian riwayat stok.');
    }

    public function edit(Request $request, string $barang)
    {
        $item = $request->user()->barang()->findOrFail($barang);

        return view('barang.form', ['item' => $item, 'history' => $item->stok()->orderByDesc('tanggal_update')->orderByDesc('id_stok')->paginate(15)]);
    }

    public function update(BarangRequest $request, string $barang)
    {
        $request->user()->barang()->findOrFail($barang)->update($request->validated());

        return back()->with('success', 'Barang diperbarui. Jalankan ulang analisis jika parameter stok berubah.');
    }

    public function destroy(Request $request, string $barang)
    {
        DB::transaction(function () use ($request, $barang) {
            $item = $request->user()->barang()->lockForUpdate()->findOrFail($barang);
            if ($item->transaksi()->exists() || $item->stok()->exists() || $item->klaster()->exists() || $item->prediksi()->exists()) {
                throw ValidationException::withMessages(['barang' => 'Barang memiliki riwayat stok/transaksi/analisis dan tidak dapat dihapus.']);
            }
            $item->delete();
        });

        return back()->with('success', 'Barang dihapus.');
    }

    public function stock(Request $request, string $barang, StockService $service)
    {
        $data = $request->validate(['jenis' => 'required|in:stok_awal,restock,penyesuaian', 'jumlah' => 'required|integer|min:0|max:2147483647', 'tanggal_update' => 'required|date|before_or_equal:now', 'keterangan' => 'nullable|string|max:255']);
        DB::transaction(function () use ($request, $barang, $data, $service) {
            $item = $request->user()->barang()->lockForUpdate()->findOrFail($barang);
            $service->snapshot($item, (int) $data['jumlah'], $data['jenis'], Carbon::parse($data['tanggal_update'])->format('Y-m-d H:i:s'), $data['keterangan'] ?? null);
        });

        return back()->with('success','Snapshot stok tersimpan. Posisi stok memakai tanggal terbaru, lalu ID snapshot terbaru.');
    }
}
