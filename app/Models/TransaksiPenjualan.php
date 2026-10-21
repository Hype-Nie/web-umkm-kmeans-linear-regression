<?php

namespace App\Models;

class TransaksiPenjualan extends Record
{
    protected $table = 'transaksi_penjualan';

    protected $primaryKey = 'id_transaksi';

    protected $casts = ['tanggal_transaksi' => 'datetime', 'harga_satuan_jual' => 'decimal:2', 'total_pendapatan' => 'decimal:2'];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang');
    }

    public function impor()
    {
        return $this->belongsTo(RiwayatImpor::class, 'id_impor');
    }

    public function scopeSearch($query, ?string $search)
    {
        return $query->when(filled($search), function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('kode_transaksi', 'like', '%'.$search.'%')
                    ->orWhereHas('barang', fn ($b) => $b->where('nama_barang', 'like', '%'.$search.'%'));
            });
        });
    }

    public function scopePeriode($query, ?string $mulai, ?string $akhir)
    {
        return $query
            ->when(filled($mulai), fn ($q) => $q->where('tanggal_transaksi', '>=', $mulai.' 00:00:00'))
            ->when(filled($akhir), fn ($q) => $q->where('tanggal_transaksi', '<=', $akhir.' 23:59:59'));
    }
}
