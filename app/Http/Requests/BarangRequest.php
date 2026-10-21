<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_barang' => ['required', 'string', 'max:60', Rule::unique('barang')->where('id_user', $this->user()->id_user)->ignore($this->route('barang'), 'id_barang')],
            'nama_barang' => 'required|string|max:191', 'kategori' => 'nullable|string|max:120',
            'harga_satuan' => ['required', 'regex:/^\d{1,13}(\.\d{1,2})?$/D'],
            'harga_beli' => ['nullable', 'regex:/^\d{1,13}(\.\d{1,2})?$/D'],
            'stok_minimum' => 'required|integer|min:0|max:2147483647',
        ];
    }
}
