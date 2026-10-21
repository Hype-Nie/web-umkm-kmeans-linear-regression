<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\StokBarang;
use Illuminate\Validation\ValidationException;

class StockService
{
    // Caller must hold a lockForUpdate() on the parent product inside a DB transaction.
    public function snapshot(Barang $barang, int $value, string $type, string $date, ?string $note = null): StokBarang
    {
        $latest = $barang->stok()->orderByDesc('tanggal_update')->orderByDesc('id_stok')->first();
        if (in_array($type, ['restock', 'penjualan']) && $latest && $date < $latest->tanggal_update->format('Y-m-d H:i:s')) {
            throw ValidationException::withMessages(['tanggal_update' => 'Mutasi operasional harus bertanggal sama/setelah snapshot terakhir. Gunakan penyesuaian untuk snapshot historis.']);
        }
        $previous = $barang->stok()->where('tanggal_update', '<=', $date)->orderByDesc('tanggal_update')->orderByDesc('id_stok')->first()?->sisa_stok;
        if (in_array($type, ['restock', 'penjualan']) && $previous === null) {
            throw ValidationException::withMessages(['stok' => 'Catat stok awal sebelum mutasi operasional.']);
        }
        if ($type === 'stok_awal' && $barang->stok()->exists()) {
            throw ValidationException::withMessages(['stok' => 'Stok awal sudah dicatat. Gunakan restock atau penyesuaian.']);
        }
        $remaining = in_array($type, ['restock', 'penjualan']) ? $previous + $value : $value;
        if ($remaining < 0 || $remaining > 2147483647) {
            throw ValidationException::withMessages(['stok' => 'Mutasi membuat stok negatif atau melebihi batas penyimpanan.']);
        }

        return $barang->stok()->create(['tanggal_update' => $date, 'stok_sebelum' => $previous, 'perubahan_stok' => $previous === null ? null : $remaining - $previous, 'sisa_stok' => $remaining, 'sumber_data' => $type, 'keterangan' => $note]);
    }
}
