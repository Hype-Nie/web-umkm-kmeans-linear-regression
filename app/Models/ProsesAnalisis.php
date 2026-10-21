<?php

namespace App\Models;

class ProsesAnalisis extends Record
{
    protected $table = 'proses_analisis';

    protected $primaryKey = 'id_proses';

    protected $casts = ['tanggal_mulai_data' => 'date', 'tanggal_akhir_data' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function klaster()
    {
        return $this->hasMany(HasilAnalisisKMeans::class, 'id_proses');
    }

    public function prediksi()
    {
        return $this->hasMany(HasilPrediksiLinearRegression::class, 'id_proses');
    }

    public function rekomendasi()
    {
        return $this->hasMany(RekomendasiKeputusan::class, 'id_proses');
    }
}
