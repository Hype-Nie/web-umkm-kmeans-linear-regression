<?php

namespace App\Models;

class RekomendasiKeputusan extends Record
{
    protected $table = 'rekomendasi_keputusan';

    protected $primaryKey = 'id_rekomendasi';

    protected $casts = ['tanggal_rekomendasi' => 'datetime'];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang');
    }

    public function proses()
    {
        return $this->belongsTo(ProsesAnalisis::class, 'id_proses');
    }
}
