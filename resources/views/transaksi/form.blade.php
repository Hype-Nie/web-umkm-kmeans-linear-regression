@extends('layouts.app')
@section('title', $item ? 'Edit Transaksi' : 'Tambah Transaksi')
@section('content')<div class="page-head">
        <div>
            <h1>{{ $item ? 'Edit Transaksi' : 'Tambah Transaksi' }}</h1>
            <p class="subtitle">Total dihitung di server dari jumlah × harga dengan presisi desimal.</p>
        </div><a class="btn" href="{{ route('transaksi.index') }}">Kembali</a>
    </div>@include('partials.data-tabs')<section class="card page-form">
        @if (!$products->count())
            <x-empty title="Tambahkan barang terlebih dahulu"
                message="Transaksi harus terhubung dengan barang milik akun Anda."><a class="btn"
                href="{{ route('barang.create') }}">Tambah Barang</a></x-empty>@else<form method="post"
                action="{{ $item ? route('transaksi.update', $item) : route('transaksi.store') }}">@csrf @if ($item)
                    @method('put')
                @endif
                <div class="grid-equal">
                    <div class="field-row"><label class="field" for="id_barang">Produk</label><select class="form-select"
                            id="id_barang" name="id_barang" data-product-price data-searchable required>
                            <option value="">Pilih produk</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id_barang }}" data-price="{{ $p->harga_satuan }}"
                                    @selected(old('id_barang', $item?->id_barang) == $p->id_barang)>{{ $p->kode_barang }} — {{ $p->nama_barang }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field-row"><label class="field" for="kode_transaksi">Kode Transaksi
                            (opsional)</label><input class="form-control" id="kode_transaksi" name="kode_transaksi"
                            value="{{ old('kode_transaksi', $item?->kode_transaksi) }}" maxlength="100"></div>
                    <div class="field-row"><label class="field" for="tanggal_transaksi">Waktu Transaksi (WIB)</label><input
                            class="form-control" type="datetime-local" id="tanggal_transaksi" name="tanggal_transaksi"
                            value="{{ old('tanggal_transaksi', ($item?->tanggal_transaksi ?? now())->format('Y-m-d\TH:i')) }}"
                            required></div>
                    <div class="field-row"><label class="field" for="jumlah_terjual">Jumlah Terjual</label><input
                            class="form-control" type="number" id="jumlah_terjual" name="jumlah_terjual" min="1"
                            max="2147483647" step="1"
                            value="{{ old('jumlah_terjual', $item?->jumlah_terjual ?? 1) }}" data-quantity required></div>
                    <div class="field-row"><label class="field" for="harga_satuan_jual">Harga Satuan Jual
                            (Rp)</label><input class="form-control" type="number" id="harga_satuan_jual"
                            name="harga_satuan_jual" min="0" step="0.01"
                            value="{{ old('harga_satuan_jual', $item?->harga_satuan_jual) }}" data-unit-price data-price required></div>
                    <div class="field-row"><label class="field" for="total">Total Pendapatan (Rp)</label><input
                            class="form-control" id="total" value="{{ $item ? number_format($item->total_pendapatan, 2, '.', '') : '0' }}" data-total readonly tabindex="-1">
                        <p class="form-help">Pratinjau otomatis, server menghitung ulang nilai yang disimpan.</p>
                    </div>
                </div>
                @if (!$item)
                    <label class="form-check d-flex gap-2 mb-3"><input class="form-check-input" name="operasional"
                            type="checkbox" value="1" @checked(old('operasional'))><span
                            class="form-check-label">Penjualan operasional: kurangi stok aktif secara atomik.</span></label>
                    <p class="form-help mb-4">Biarkan kosong untuk pencatatan historis yang sudah tercermin pada snapshot
                        stok. Penjualan operasional membutuhkan stok awal, tidak dapat dibelakangkan sebelum snapshot
                    terbaru, dan dikunci dari edit/hapus setelah terhubung riwayat stok.</p>@else<div
                        class="info-box mb-4">Perubahan ini untuk transaksi historis dan tidak mengubah snapshot stok.
                        Transaksi operasional yang terhubung stok dilindungi dari edit/hapus demi audit.</div>
                @endif
                <button class="btn btn-primary">
                    Simpan Transaksi</button>
            </form>
        @endif
</section>@endsection
