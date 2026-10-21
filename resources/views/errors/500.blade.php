@extends('layouts.auth')
@section('title', 'Permintaan Gagal')
@section('content')<div class="auth-page">
        <div class="card">
            <h1>Permintaan belum dapat diproses</h1>
            <p>Periksa koneksi database atau hubungi pengelola instalasi. Rincian teknis dicatat pada log server.</p><a
                class="btn btn-primary" href="{{ url('/') }}">Kembali ke Aplikasi</a>
        </div>
</div>@endsection
