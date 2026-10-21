<?php

namespace App\Models;

class HasilPrediksiLinearRegression extends Record
{
    protected $table = 'hasil_prediksi_linear_regression';

    protected $primaryKey = 'id_prediksi';

    protected $casts = ['tanggal_analisis' => 'datetime', 'periode_prediksi' => 'date', 'prediksi_penjualan' => 'decimal:4'];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang');
    }

    public function proses()
    {
        return $this->belongsTo(ProsesAnalisis::class, 'id_proses');
    }
}
