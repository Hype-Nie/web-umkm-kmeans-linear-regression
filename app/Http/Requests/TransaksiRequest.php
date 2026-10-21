<?php

namespace App\Http\Requests;

use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class TransaksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_barang' => 'required|integer',
            'kode_transaksi' => 'nullable|string|max:100',
            'tanggal_transaksi' => 'required|date|before_or_equal:now',
            'jumlah_terjual' => 'required|integer|min:1|max:2147483647',
            'harga_satuan_jual' => ['required', 'regex:/^\d{1,13}(\.\d{1,2})?$/D'],
            'operasional' => 'nullable|boolean',
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();
        $this->user()->barang()->findOrFail($data['id_barang']);
        $data['tanggal_transaksi'] = Carbon::parse($data['tanggal_transaksi'])->format('Y-m-d H:i:s');
        try {
            $data['total_pendapatan'] = Money::total($data['harga_satuan_jual'], (int) $data['jumlah_terjual']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['harga_satuan_jual' => $e->getMessage()]);
        }
        unset($data['operasional']);

        return $data;
    }
}
