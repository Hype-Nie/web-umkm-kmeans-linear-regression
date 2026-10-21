<?php

namespace App\Models;

class HasilAnalisisKMeans extends Record
{
    protected $table = 'hasil_analisis_kmeans';

    protected $primaryKey = 'id_klaster';

    protected $casts = ['tanggal_analisis' => 'datetime'];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang');
    }

    public function proses()
    {
        return $this->belongsTo(ProsesAnalisis::class, 'id_proses');
    }
}
