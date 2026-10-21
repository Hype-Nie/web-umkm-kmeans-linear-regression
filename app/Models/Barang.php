<?php

namespace App\Models;

class Barang extends Record
{
    protected $table = 'barang';

    protected $primaryKey = 'id_barang';

    protected $casts = ['harga_satuan' => 'decimal:2', 'harga_beli' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function transaksi()
    {
        return $this->hasMany(TransaksiPenjualan::class, 'id_barang');
    }

    public function stok()
    {
        return $this->hasMany(StokBarang::class, 'id_barang');
    }

    public function stokTerakhir()
    {
        return $this->hasOne(StokBarang::class, 'id_barang')->ofMany(['tanggal_update' => 'max', 'id_stok' => 'max']);
    }

    public function klaster()
    {
        return $this->hasMany(HasilAnalisisKMeans::class, 'id_barang');
    }

    public function prediksi()
    {
        return $this->hasMany(HasilPrediksiLinearRegression::class, 'id_barang');
    }

    public function scopeSearch($query, ?string $search)
    {
        return $query->when(filled($search), function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('nama_barang', 'like', '%'.$search.'%')
                    ->orWhere('kode_barang', 'like', '%'.$search.'%');
            });
        });
    }

    public function scopeKategori($query, ?string $kategori)
    {
        return $query->when(filled($kategori), fn ($q) => $q->where('kategori', $kategori));
    }

    public function scopeKritis($query, bool $kritis = true)
    {
        return $query->when($kritis, function ($q) {
            $q->whereHas('stokTerakhir', fn ($sub) => $sub->whereColumn('sisa_stok', '<=', 'barang.stok_minimum'));
        });
    }
}
