@extends('layouts.app')
@section('title', 'Barang & Stok')
@section('content')<div class="page-head">
        <div>
            <h1>Kelola Barang & Stok</h1>
            <p class="subtitle">Master produk dan posisi persediaan dari snapshot terbaru.</p>
        </div><a class="btn btn-primary" href="{{ route('barang.create') }}"><x-icon name="plus" />Tambah Barang</a>
    </div>@include('partials.data-tabs')
    <form class="filters">
        <div><label class="field" for="q">Cari Produk</label><input class="form-control" name="q" id="q"
                value="{{ request('q') }}" placeholder="Nama / kode barang"></div>
        <div class="filter-grow"><label class="field" for="kategori">Kategori</label><select class="form-select" id="kategori"
                name="kategori" data-searchable>
                <option value="">Semua kategori</option>
                @foreach ($categories as $c)
                    <option @selected(request('kategori') === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="field" for="sort">Urutkan</label><select class="form-select" id="sort" name="sort">
                @foreach (['nama_barang' => 'Nama', 'kode_barang' => 'Kode', 'harga_satuan' => 'Harga', 'created_at' => 'Tanggal ditambahkan'] as $key => $label)
                    <option value="{{ $key }}" @selected(request('sort', 'nama_barang') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="field" for="direction">Arah</label><select class="form-select" id="direction" name="direction">
                <option value="asc" @selected(request('direction') !== 'desc')>Menaik</option>
                <option value="desc" @selected(request('direction') === 'desc')>Menurun</option>
            </select></div><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="kritis"
                value="1" @checked(request('kritis'))> Stok kritis</label><button
            class="btn btn-primary">Terapkan</button><a class="btn" href="{{ route('barang.index') }}">Reset</a>
    </form>
    <section class="card flush">
        @if ($items->count())
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>KODE</th>
                            <th>PRODUK</th>
                            <th>KATEGORI</th>
                            <th>HARGA JUAL</th>
                            <th>STOK AKTIF</th>
                            <th>MINIMAL</th>
                            <th>PEMBARUAN</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td><code>{{ $item->kode_barang }}</code></td>
                                <td><strong>{{ $item->nama_barang }}</strong></td>
                                <td>{{ $item->kategori ?: '—' }}</td>
                                <td>{{ \App\Support\Money::format($item->harga_satuan) }}</td>
                                <td>
                                    @if ($item->stokTerakhir)
                                        <strong class="{{ $item->stokTerakhir->sisa_stok <= $item->stok_minimum ? 'danger' : '' }}">
                                            {{ $item->stokTerakhir->sisa_stok }}
                                        </strong>
                                    @else
                                        <span class="muted small">Belum tercatat</span>
                                    @endif
                                </td>
                                <td>{{ $item->stok_minimum }}</td>
                                <td>{{ $item->stokTerakhir?->tanggal_update->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>
                                    <div class="table-actions">
                                        <a class="btn btn-sm" href="{{ route('barang.edit', $item) }}">Edit / Stok</a>
                                        <form method="post" action="{{ route('barang.destroy', $item) }}"
                                            data-confirm="Hapus barang ini? Barang dengan riwayat tidak dapat dihapus.">
                                            @csrf @method('delete')
                                            <button class="btn btn-sm btn-danger">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        <div class="pagination-wrap">{{ $items->links() }}</div>@else<div class="p-3"><x-empty
                    title="Tidak ada barang yang sesuai"
                    message="Tambahkan barang pertama Anda atau ubah filter pencarian."><a class="btn"
                        href="{{ route('barang.create') }}">Tambah Barang</a></x-empty></div>
        @endif
</section>@endsection
