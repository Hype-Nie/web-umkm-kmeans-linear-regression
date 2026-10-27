<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransaksiRequest;
use App\Models\TransaksiPenjualan;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransaksiController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:191', 'mulai' => 'nullable|date_format:Y-m-d', 'akhir' => 'nullable|date_format:Y-m-d|after_or_equal:mulai']);
        $query = TransaksiPenjualan::whereHas('barang', fn ($q) => $q->where('id_user', $request->user()->id_user))
            ->with('barang')
            ->search($request->input('q'))
            ->periode($request->input('mulai'), $request->input('akhir'));

        return view('transaksi.index', ['items' => $query->orderByDesc('tanggal_transaksi')->orderByDesc('id_transaksi')->paginate(15)->withQueryString()]);
    }

    private function owned(Request $request, string $id): TransaksiPenjualan
    {
        return TransaksiPenjualan::whereHas('barang', fn ($q) => $q->where('id_user', $request->user()->id_user))->findOrFail($id);
    }

    public function create(Request $request)
    {
        return view('transaksi.form', ['item' => null, 'products' => $request->user()->barang()->orderBy('nama_barang')->get()]);
    }

    public function edit(Request $request, string $transaksi)
    {
        return view('transaksi.form', ['item' => $this->owned($request, $transaksi), 'products' => $request->user()->barang()->orderBy('nama_barang')->get()]);
    }

    public function store(TransaksiRequest $request, StockService $stock)
    {
        $data = $request->payload();
        DB::transaction(function () use ($request, $data, $stock) {
            $product = $request->user()->barang()->lockForUpdate()->findOrFail($data['id_barang']);
            $transaction = $product->transaksi()->create($data + ['sumber_data' => 'manual']);
            if ($request->boolean('operasional')) {
                $stock->snapshot($product, -(int) $data['jumlah_terjual'], 'penjualan', $data['tanggal_transaksi'], 'Transaksi #'.$transaction->id_transaksi);
            }
        });

        return redirect()->route('transaksi.index')->with('success', 'Transaksi disimpan. Perbarui analisis agar hasil mengikuti data terbaru.');
    }

    private function assertEditable(TransaksiPenjualan $item): void
    {
        if ($item->barang->stok()->where('sumber_data', 'penjualan')->where('keterangan', 'Transaksi #'.$item->id_transaksi)->exists()) {
            throw ValidationException::withMessages(['transaksi' => 'Transaksi operasional terhubung riwayat stok dan dikunci untuk menjaga audit. Catat koreksi sebagai penyesuaian stok, perubahan transaksi harus melalui pencatatan koreksi historis terpisah.']);
        }
    }

    public function update(TransaksiRequest $request, string $transaksi)
    {
        $item = $this->owned($request, $transaksi);
        $this->assertEditable($item);
        $data = $request->payload();
        DB::transaction(function () use ($request, $item, $data) {
            $request->user()->barang()->whereIn('id_barang', [$item->id_barang, $data['id_barang']])->orderBy('id_barang')->lockForUpdate()->get();
            $item->update($data);
        });

        return redirect()->route('transaksi.index')->with('success', 'Transaksi historis diperbarui. Snapshot stok tidak dikurangi ulang.');
    }

    public function destroy(Request $request, string $transaksi)
    {
        $item = $this->owned($request, $transaksi);
        $this->assertEditable($item);
        DB::transaction(function () use ($item) {
            $item->barang()->lockForUpdate()->first();
            $item->delete();
        });

        return back()->with('success','Transaksi historis dihapus. Snapshot stok tidak diubah.');
    }
}
