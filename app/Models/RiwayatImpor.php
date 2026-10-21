<?php

namespace App\Models;

class RiwayatImpor extends Record
{
    protected $table = 'riwayat_impor';

    protected $primaryKey = 'id_impor';

    protected $casts = ['rincian_error' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
