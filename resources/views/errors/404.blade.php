@extends('layouts.auth')
@section('title', 'Tidak Ditemukan')
@section('content')<div class="auth-page">
        <div class="card">
            <h1>Halaman tidak ditemukan</h1>
            <p>Data atau halaman yang diminta tidak tersedia pada akun ini.</p><a class="btn btn-primary"
                href="{{ url('/') }}">Kembali ke Aplikasi</a>
        </div>
</div>@endsection
