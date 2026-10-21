<?php

namespace App\Models;

class StokBarang extends Record
{
    protected $table = 'stok_barang';

    protected $primaryKey = 'id_stok';

    protected $casts = ['tanggal_update' => 'datetime'];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang');
    }

    public function impor()
    {
        return $this->belongsTo(RiwayatImpor::class, 'id_impor');
    }
}
