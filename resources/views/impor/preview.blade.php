@extends('layouts.app')
@section('title', 'Pemetaan & Preview Impor')
@section('content')<div class="page-head">
        <div>
            <h1>Pemetaan & Preview Impor</h1>
            <p class="subtitle">{{ $history->nama_file_asli }} · {{ $history->jenis_data }} · {{ count($file['rows']) }}
                baris sebelum pembersihan</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if ($history->status === 'diproses')
                <form method="post" action="{{ route('impor.destroy', $history) }}" data-confirm="Batalkan proses impor dan hapus berkas unggahan ini?">
                    @csrf
                    @method('delete')
                    <button type="submit" class="btn btn-danger">Batalkan Impor</button>
                </form>
            @endif
            <a class="btn" href="{{ route('impor.index') }}">Kembali</a>
        </div>
    </div>
    <form method="post" action="{{ route('impor.preview', $history) }}" class="card mb-4">@csrf<h2 class="mb-3">Petakan Kolom
            Berkas</h2>
        <p class="muted">Pilih kolom sumber untuk setiap atribut. Biarkan tidak dipetakan bila atribut tidak dipakai. Untuk
            stok saja, kolom transaksi tidak diperlukan.</p>
        <div class="mapping">
            @foreach ($fields as $field)
                <div><label class="field" for="map-{{ $field }}">{{ $field }}</label><select
                        class="form-select" id="map-{{ $field }}" name="mapping[{{ $field }}]">
                        <option value="">— Tidak dipetakan —</option>
                        @foreach ($file['headers'] as $header)
                            <option value="{{ $header }}" @selected(($mapping[$field] ?? '') === $header)>{{ $header }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
        <div class="mt-3"><button class="btn">Validasi Ulang Pemetaan</button></div>
    </form>
    <div class="stats">
        <div class="card"><span class="stat-label">Baris Valid</span><strong
                class="stat-value">{{ count($preview['valid']) }}</strong></div>
        <div class="card"><span class="stat-label">Baris Bermasalah</span><strong
                class="stat-value {{ $preview['errors'] ? 'danger' : '' }}">{{ count($preview['errors']) }}</strong></div>
        <div class="card"><span class="stat-label">Penyimpanan</span>
            <p class="mt-3 mb-0 muted">Belum ada transaksi atau snapshot yang disimpan sebelum konfirmasi.</p>
        </div>
    </div>
    <section class="card flush mb-4">
        <div class="card-head">
            <h2>Preview Data Valid · 20 Baris Pertama</h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>BARIS</th>
                        <th>KODE BARANG</th>
                        <th>WAKTU TRANSAKSI</th>
                        <th>UNIT</th>
                        <th>HARGA</th>
                        <th>PENDAPATAN</th>
                        <th>WAKTU STOK</th>
                        <th>SISA STOK</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(array_slice($preview['valid'],0,20) as $entry)
                        @php($d = $entry['data'])
                        <tr>
                            <td>{{ $entry['row'] }}</td>
                            <td>{{ $d['kode_barang'] }}</td>
                            <td>{{ $d['has_sale'] ? $d['tanggal_transaksi'] : '—' }}</td>
                            <td>{{ $d['has_sale'] ? $d['jumlah_terjual'] : '—' }}</td>
                            <td>{{ $d['has_sale'] ? $d['harga_satuan_jual'] : '—' }}</td>
                            <td>{{ $d['has_sale'] ? $d['total_pendapatan'] : '—' }}</td>
                            <td>{{ $d['has_stock'] ? $d['tanggal_update'] : '—' }}</td>
                            <td>{{ $d['has_stock'] ? $d['sisa_stok'] : '—' }}</td>
                    </tr>@empty<tr>
                            <td colspan="8">Tidak ada baris valid. Periksa pemetaan atau perbaiki berkas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @if ($preview['errors'])
        <section class="card mb-4">
            <h2>Rincian Validasi</h2>
            <ul class="mt-2 mb-0">
                @foreach (array_slice($preview['errors'], 0, 100) as $error)
                    <li class="mb-1">Baris {{ $error['baris'] }}: {{ $error['pesan'] }}</li>
                @endforeach
            </ul>
            @if (count($preview['errors']) > 100)
                <p class="form-help">100 error pertama ditampilkan; seluruh error disimpan saat konfirmasi.</p>
            @endif
        </section>
    @endif
    @if ($history->status === 'diproses')
        <form method="post" action="{{ route('impor.confirm', $history) }}" class="card" data-submit-lock>@csrf
            @foreach ($mapping as $field => $column)
                <input type="hidden" name="mapping[{{ $field }}]" value="{{ $column }}">
            @endforeach
            <label class="form-check d-flex gap-2 mb-3"><input class="form-check-input" name="partial" type="checkbox"
                    value="1" @checked(old('partial'))><span class="form-check-label">Izinkan impor parsial: simpan
                    baris valid, lewati baris gagal. Tanpa pilihan ini, error validasi membatalkan seluruh
                    impor.</span></label><label class="form-check d-flex gap-2 mb-3"><input class="form-check-input"
                    name="confirmed" type="checkbox" value="1" required><span class="form-check-label">Saya telah
                    meninjau pemetaan dan preview. Snapshot historis sudah mewakili posisi stok akhir.</span></label>
            <div><button type="submit" class="btn btn-primary">Konfirmasi Impor Data</button></div>
    </form>@else<div class="info-box">Status unggahan: {{ $history->status }}. Unggahan yang telah dikonfirmasi tidak
            dapat dijalankan ulang.</div>
    @endif @endsection
