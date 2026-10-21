<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class PeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['mulai' => 'nullable|date_format:Y-m-d', 'akhir' => 'nullable|date_format:Y-m-d|after_or_equal:mulai', 'interval' => 'nullable|in:hari,minggu,bulan'];
    }

    public function periode(string $defaultMode = 'all'): array
    {
        $user = $this->user();

        if ($defaultMode === 'month') {
            return $this->periodeMonth();
        }

        if (! $this->filled('mulai') && $user) {
            $minTrx = \App\Models\TransaksiPenjualan::whereHas('barang', fn ($q) => $q->where('id_user', $user->id_user))->min('tanggal_transaksi');
            $start = $minTrx ? \Carbon\Carbon::parse($minTrx)->startOfMonth()->toDateString() : now()->startOfMonth()->toDateString();
        } else {
            $start = $this->input('mulai') ?: now()->startOfMonth()->toDateString();
        }

        if (! $this->filled('akhir') && $user) {
            $maxTrx = \App\Models\TransaksiPenjualan::whereHas('barang', fn ($q) => $q->where('id_user', $user->id_user))->max('tanggal_transaksi');
            $end = $maxTrx ? \Carbon\Carbon::parse($maxTrx)->toDateString() : now()->toDateString();
            if ($end < $start) {
                $end = now()->toDateString();
            }
        } else {
            $end = $this->input('akhir') ?: now()->toDateString();
        }

        if ($start > $end) {
            throw ValidationException::withMessages(['akhir' => 'Tanggal akhir harus sama atau setelah tanggal mulai.']);
        }

        return [$start, $end];
    }

    public function periodeMonth(): array
    {
        $user = $this->user();
        $hasMulai = $this->filled('mulai');
        $hasAkhir = $this->filled('akhir');

        if ($hasMulai && $hasAkhir) {
            $start = $this->input('mulai');
            $end = $this->input('akhir');
        } elseif ($hasMulai && ! $hasAkhir) {
            $start = $this->input('mulai');
            $end = \Carbon\Carbon::parse($start)->endOfMonth()->toDateString();
        } elseif (! $hasMulai && $hasAkhir) {
            $end = $this->input('akhir');
            $start = \Carbon\Carbon::parse($end)->startOfMonth()->toDateString();
        } else {
            $maxTrx = $user ? \App\Models\TransaksiPenjualan::whereHas('barang', fn ($q) => $q->where('id_user', $user->id_user))->max('tanggal_transaksi') : null;
            $ref = $maxTrx ? \Carbon\Carbon::parse($maxTrx) : now();
            $start = $ref->copy()->startOfMonth()->toDateString();
            $end = $ref->copy()->endOfMonth()->toDateString();
        }

        if ($start > $end) {
            throw ValidationException::withMessages(['akhir' => 'Tanggal akhir harus sama atau setelah tanggal mulai.']);
        }

        return [$start, $end];
    }
}
