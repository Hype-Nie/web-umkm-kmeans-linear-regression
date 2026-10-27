@extends('layouts.auth')
@section('title', 'Ketentuan & Privasi')
@section('content')<div class="auth-page">
        <article class="card" style="max-width:650px">
            <h1>Ketentuan & Privasi</h1>
            <p>Aplikasi ini membantu pemilik UMKM mengelola data miliknya dan meninjau analisis stok serta penjualan. Anda
                bertanggung jawab atas izin penggunaan, ketepatan, dan kelengkapan data yang diunggah.</p>
            <p>Data akun, transaksi, snapshot stok, unggahan, serta hasil analisis disimpan pada server aplikasi. Akses
                dibatasi menurut akun. Pengelola instalasi bertanggung jawab atas keamanan server, cadangan, dan permintaan
                penghapusan data.</p>
            <p>Prediksi dan rekomendasi merupakan alat bantu keputusan berdasarkan data dan asumsi yang dijelaskan. Hasil
                tidak menjamin penjualan atau keuntungan. Tinjau kembali sebelum melakukan pembelian atau promosi.</p>
            <p>Aset Bootstrap dan Chart.js dimuat dari jsDelivr, koneksi ke CDN mengikuti kebijakan penyedia tersebut. Data
                transaksi tidak dikirim ke CDN oleh aplikasi.</p><a class="btn" href="{{ route('register') }}">Kembali ke
                pendaftaran</a>
        </article>
</div>@endsection
