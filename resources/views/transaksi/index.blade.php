@extends('layouts.app')
@section('title', 'Transaksi Penjualan')
@section('content')<div class="page-head">
        <div>
            <h1>Transaksi Penjualan</h1>
            <p class="subtitle">Kelola riwayat penjualan dan transaksi operasional.</p>
        </div><a class="btn btn-primary" href="{{ route('transaksi.create') }}"><x-icon name="plus" />Tambah Transaksi</a>
    </div>@include('partials.data-tabs')<form class="filters">
        <div><label class="field" for="q">Pencarian</label><input class="form-control" id="q" name="q"
                value="{{ request('q') }}" placeholder="Kode transaksi / produk"></div>
        <div><label class="field" for="mulai">Dari Tanggal</label><input class="form-control" type="date"
                id="mulai" name="mulai" value="{{ request('mulai') }}"></div>
        <div><label class="field" for="akhir">Sampai Tanggal</label><input class="form-control" type="date"
                id="akhir" name="akhir" value="{{ request('akhir') }}"></div><button
            class="btn btn-primary">Filter</button><a class="btn" href="{{ route('transaksi.index') }}">Reset</a>
    </form>
    <section class="card flush">
        @if ($items->count())
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>KODE TRANSAKSI</th>
                            <th>TANGGAL (WIB)</th>
                            <th>PRODUK</th>
                            <th>UNIT</th>
                            <th>HARGA JUAL</th>
                            <th>TOTAL</th>
                            <th>SUMBER</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $t)
                            <tr>
                                <td><code>{{ $t->kode_transaksi ?: '#' . $t->id_transaksi }}</code></td>
                                <td>{{ $t->tanggal_transaksi->format('d/m/Y H:i') }}</td>
                                <td><strong>{{ $t->barang->nama_barang }}</strong></td>
                                <td>{{ $t->jumlah_terjual }}</td>
                                <td>{{ \App\Support\Money::format($t->harga_satuan_jual) }}</td>
                                <td><strong>{{ \App\Support\Money::format($t->total_pendapatan) }}</strong></td>
                                <td><span class="badge {{ $t->sumber_data === 'impor' ? 'badge-info' : 'badge-primary' }}">{{ strtoupper($t->sumber_data) }}</span></td>
                                <td>
                                    <div class="table-actions">
                                        <a href="{{ route('transaksi.edit', $t) }}" class="btn btn-sm">Edit</a>
                                        <form method="post" action="{{ route('transaksi.destroy', $t) }}"
                                            data-confirm="Hapus transaksi historis ini? Snapshot stok tidak diubah. Transaksi operasional yang terhubung stok dilindungi.">
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
                    title="Belum ada transaksi yang sesuai"
                    message="Tambahkan transaksi atau unggah riwayat penjualan bisnis Anda."><a
                        href="{{ route('impor.index') }}" class="btn">Upload Data</a></x-empty></div>
        @endif
</section>@endsection
