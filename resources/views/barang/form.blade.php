@extends('layouts.app')
@section('title', $item ? 'Edit Barang & Stok' : 'Tambah Barang')
@section('content')<div class="page-head">
        <div>
            <h1>{{ $item ? 'Detail Barang & Stok' : 'Tambah Barang' }}</h1>
            <p class="subtitle">Kelola identitas, harga, serta batas minimum persediaan.</p>
        </div><a class="btn" href="{{ route('barang.index') }}">Kembali ke Barang</a>
    </div>@include('partials.data-tabs')<div class="page-form">
        <section class="card mb-4">
            <h2 class="mb-4">Informasi Barang</h2>
            <form method="post" action="{{ $item ? route('barang.update', $item) : route('barang.store') }}">@csrf
                @if ($item)
                    @method('put')
                @endif
                <div class="grid-equal">
                    @foreach (['kode_barang' => 'Kode Barang', 'nama_barang' => 'Nama Barang', 'kategori' => 'Kategori', 'harga_satuan' => 'Harga Jual (Rp)', 'harga_beli' => 'Harga Beli (Rp, opsional)', 'stok_minimum' => 'Ambang Minimum Stok'] as $field => $label)
                        <div class="field-row"><label class="field"
                                for="{{ $field }}">{{ $label }}</label><input class="form-control"
                                name="{{ $field }}" id="{{ $field }}"
                                value="{{ old($field, $item?->$field ?? (in_array($field, ['harga_satuan', 'stok_minimum']) ? '0' : '')) }}"
                                @if (in_array($field, ['harga_satuan', 'harga_beli', 'stok_minimum'])) type="number" min="0" step="{{ $field === 'stok_minimum' ? '1' : '0.01' }}" @else type="text" maxlength="{{ $field === 'kode_barang' ? 60 : ($field === 'kategori' ? 120 : 191) }}" @endif
                                @required(!in_array($field, ['kategori', 'harga_beli']))></div>
                    @endforeach
                </div>
                <p class="form-help">Input harga menggunakan angka tanpa pemisah ribuan, desimal titik (contoh 12500.50).
                </p><button class="btn btn-primary" type="submit">Simpan Barang</button>
            </form>
        </section>
        @if ($item)
            <section class="card mb-4 scroll-anchor" id="stok">
                <h2>Catat Stok</h2>
                <p class="muted">Stok awal/penyesuaian menyimpan nilai akhir absolut. Restock menambah posisi stok
                    terakhir. Snapshot bersifat riwayat audit.</p>
                <form method="post" action="{{ route('barang.stock', $item) }}">@csrf<div class="grid-equal">
                        <div class="field-row"><label class="field" for="jenis">Jenis Pencatatan</label><select
                                class="form-select" id="jenis" name="jenis">
                                <option value="stok_awal">Stok Awal (nilai akhir)</option>
                                <option value="restock" @selected(old('jenis') === 'restock')>Restock (jumlah tambahan)</option>
                                <option value="penyesuaian" @selected(old('jenis') === 'penyesuaian')>Penyesuaian / Snapshot Historis
                                    (nilai akhir)</option>
                            </select></div>
                        <div class="field-row"><label class="field" for="jumlah">Jumlah Unit</label><input
                                class="form-control" type="number" id="jumlah" name="jumlah" min="0"
                                max="2147483647" step="1" value="{{ old('jumlah') }}" required></div>
                        <div class="field-row"><label class="field" for="tanggal_update">Tanggal & Waktu
                                (WIB)</label><input class="form-control" type="datetime-local" id="tanggal_update"
                                name="tanggal_update" value="{{ old('tanggal_update', now()->format('Y-m-d\TH:i')) }}"
                                required></div>
                        <div class="field-row"><label class="field" for="keterangan">Keterangan</label><input
                                class="form-control" id="keterangan" name="keterangan" maxlength="255"
                                value="{{ old('keterangan') }}"></div>
                    </div><button class="btn btn-primary">Simpan Snapshot Stok</button></form>
            </section>
            <section class="card flush">
                <div class="card-head">
                    <h2>Histori Snapshot Stok</h2>
                </div>
                @if ($history->count())
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>TANGGAL (WIB)</th>
                                    <th>JENIS</th>
                                    <th>SEBELUM</th>
                                    <th>PERUBAHAN</th>
                                    <th>SISA</th>
                                    <th>KETERANGAN</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($history as $s)
                                    <tr>
                                        <td>{{ $s->tanggal_update->format('d/m/Y H:i:s') }}</td>
                                        <td>{{ $s->sumber_data }}</td>
                                        <td>{{ $s->stok_sebelum ?? '—' }}</td>
                                        <td>{{ $s->perubahan_stok ?? '—' }}</td>
                                        <td><strong>{{ $s->sisa_stok }}</strong></td>
                                        <td>{{ $s->keterangan ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                <div class="pagination-wrap">{{ $history->links() }}</div>@else<div class="p-3"><x-empty
                            title="Stok belum tercatat" message="Catat stok awal atau unggah snapshot historis." /></div>
                @endif
            </section>
        @endif
</div>@endsection
