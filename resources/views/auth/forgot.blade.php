@extends('layouts.auth')
@section('title', 'Pemulihan Kata Sandi')
@section('content')
<div class="auth-page">
    <div class="login-shell">
        <div class="auth-brand">
            <span class="auth-brand-badge"><x-icon name="shield" /></span>
            <span>Strategic DSS Admin</span>
        </div>
        <p class="auth-sub">Pemulihan akses akun</p>

        @include('partials.flash')

        <section class="card login-card auth-card">
            <h1>Lupa Kata Sandi</h1>
            <p class="text-muted" style="font-size:13px; margin-bottom:20px;">Masukkan email akun untuk menerima tautan reset kata sandi.</p>
            <form method="post" action="{{ route('password.email') }}">
                @csrf
                <div class="field-row mb-3">
                    <label class="field" for="email">Alamat Email</label>
                    <input class="form-control" type="email" id="email" name="email"
                        value="{{ old('email') }}" placeholder="nama@email.com" required autofocus autocomplete="email">
                </div>
                <button class="btn btn-login btn-wide mt-2" type="submit">KIRIM TAUTAN RESET</button>
            </form>
            <div class="login-register text-center mt-3">
                <a href="{{ route('login') }}">Kembali ke halaman masuk</a>
            </div>
        </section>
    </div>
</div>
@endsection
