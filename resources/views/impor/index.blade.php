@extends('layouts.app')
@section('title', 'Kelola Data Transaksi')
@section('content')<div class="page-head">
        <div>
            <h1>Kelola Data Transaksi</h1>
            <p class="subtitle">Unggah riwayat penjualan dan stok dari Excel atau CSV.</p>
        </div>
    </div>@include('partials.data-tabs')<div class="upload-layout">
        <div class="stack">
            <form action="{{ route('impor.upload') }}" method="post" enctype="multipart/form-data" data-submit-lock>@csrf<div
                    class="dropzone" data-dropzone><span class="upload-icon"><x-icon name="upload" /></span>
                    <h2>Drop file .xlsx / .csv</h2><span class="muted">atau pilih file dari komputer Anda · maksimal 10
                        MB</span><input type="file" name="file" id="upload-file" accept=".xlsx,.csv"
                        class="form-control" style="max-width:310px" required><span class="form-help" data-file-name>Belum
                        ada file dipilih</span>
                </div>
                <div class="card mt-3">
                    <div class="grid-equal">
                        <div><label class="field" for="jenis_data">Jenis Impor</label><select class="form-select"
                                name="jenis_data" id="jenis_data">
                                <option value="gabungan">Gabungan transaksi & stok</option>
                                <option value="transaksi" @selected(old('jenis_data') === 'transaksi')>Transaksi penjualan</option>
                                <option value="stok" @selected(old('jenis_data') === 'stok')>Snapshot stok</option>
                            </select></div>
                        <div class="d-flex align-items-end"><button type="submit"
                                class="btn btn-primary btn-wide">Lanjutkan ke Pemetaan & Preview <x-icon
                                    name="arrow" /></button></div>
                    </div>
                </div>
            </form>
            <section class="card flush">
                <div class="card-head">
                    <h2>Riwayat Upload</h2><span class="muted small">{{ $history->total() }} berkas</span>
                </div>
                @if ($history->count())
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>NAMA FILE</th>
                                    <th>TANGGAL (WIB)</th>
                                    <th>JENIS</th>
                                    <th>HASIL</th>
                                    <th>STATUS</th>
                                    <th>AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($history as $h)
                                    <tr>
                                        <td style="max-width:240px;overflow-wrap:anywhere">
                                            <strong>{{ $h->nama_file_asli }}</strong>
                                        </td>
                                        <td>{{ $h->created_at->format('d M Y, H:i') }}</td>
                                        <td><span class="text-uppercase small">{{ $h->jenis_data }}</span></td>
                                        <td>
                                            @if ($h->status === 'diproses')
                                                <span class="text-muted small">Menunggu konfirmasi</span>
                                            @else
                                                {{ $h->baris_berhasil }} berhasil<br>
                                                <span class="muted small">{{ $h->baris_gagal }} gagal / {{ $h->jumlah_baris }} baris</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $badgeClass = match($h->status) {
                                                    'berhasil' => 'badge-success',
                                                    'gagal' => 'badge-danger',
                                                    'parsial' => 'badge-warning',
                                                    default => '',
                                                };
                                            @endphp
                                            <span class="badge {{ $badgeClass }}">{{ strtoupper($h->status) }}</span>
                                            @if ($h->rincian_error)
                                                <details class="mt-2">
                                                    <summary class="small text-muted" style="cursor:pointer">Rincian Error</summary>
                                                    <ul class="ps-3 mt-1 small text-danger mb-0">
                                                        @foreach (array_slice($h->rincian_error, 0, 100) as $error)
                                                            <li>Baris {{ $error['baris'] ?? '—' }}: {{ $error['pesan'] }}</li>
                                                        @endforeach
                                                    </ul>
                                                    @if (count($h->rincian_error) > 100)
                                                        <small class="text-muted d-block mt-1">100 error pertama ditampilkan.</small>
                                                    @endif
                                                </details>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($h->status === 'diproses')
                                                <div class="d-flex align-items-center gap-2">
                                                    <a class="btn btn-sm btn-primary" href="{{ route('impor.preview', $h) }}">Lanjutkan</a>
                                                    <form action="{{ route('impor.destroy', $h) }}" method="post" class="d-inline" data-confirm="Batalkan proses impor berkas ini? Berkas belum disimpan ke database.">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" class="btn btn-sm btn-danger">Batalkan</button>
                                                    </form>
                                                </div>
                                            @elseif ($h->status === 'gagal')
                                                <form action="{{ route('impor.destroy', $h) }}" method="post" class="d-inline" data-confirm="Hapus riwayat berkas gagal ini?">
                                                    @csrf
                                                    @method('delete')
                                                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                                </form>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                <div class="pagination-wrap">{{ $history->links() }}</div>@else<div class="p-3"><x-empty
                            title="Belum ada berkas yang diunggah"
                            message="Unggah data asli bisnis Anda. Tidak ada data contoh yang dimasukkan ke database." />
                    </div>
                @endif
            </section>
        </div>
        <aside class="stack">
            <section class="card">
                <h2>Panduan Format Excel</h2>
                <div class="guide-item"><strong>Struktur Kolom</strong>Transaksi memerlukan tanggal_transaksi,
                    kode_barang/nama_barang, jumlah_terjual, dan harga_satuan_jual. total_pendapatan dihitung dan
                    diverifikasi. Alias transaction_id dan amount_value diterima melalui pemetaan.</div>
                <div class="guide-item"><strong>Format Tanggal</strong>Gunakan YYYY-MM-DD atau DD/MM/YYYY. Waktu opsional
                    HH:MM:SS. Tanggal serial Excel juga diterima. Tidak ada batas tahun historis yang dikunci.</div>
                <div class="guide-item"><strong>Tipe Data Angka</strong>Jumlah dan stok berupa bilangan bulat. Harga tanpa
                    pemisah ribuan, desimal titik. Produk baru membutuhkan nama_barang dan kategori.</div>
                <div class="guide-item"><strong>Snapshot Stok</strong>tanggal_update dan sisa_stok menyatakan stok akhir
                    saat itu. Impor penjualan historis tidak mengurangi snapshot lagi.</div>
                <div class="info-box"><strong>Unduh template kosong</strong>
                    <p class="form-help">Isi header yang diperlukan lalu periksa pemetaan sebelum konfirmasi.</p>
                    <form action="{{ route('impor.template') }}"><label class="field" for="template-jenis">Jenis
                            Template</label><select class="form-select mb-2" id="template-jenis" name="jenis">
                            <option value="gabungan">Gabungan</option>
                            <option value="transaksi">Transaksi</option>
                            <option value="stok">Stok</option>
                        </select>
                        <div class="actions"><button class="btn btn-sm" name="format" value="xlsx"><x-icon
                                    name="download" />Excel .xlsx</button><button class="btn btn-sm" name="format"
                                value="csv">CSV</button></div>
                    </form>
                </div>
            </section>
            <section class="info-box">Untuk riset 24 bulan, unggah semua bagian riwayat yang tersedia. Konfirmasi
                kelengkapan bulan pada halaman analisis; aplikasi tidak mengasumsikan bulan kosong sebagai nol tanpa
                persetujuan.</section>
        </aside>
</div>@endsection
