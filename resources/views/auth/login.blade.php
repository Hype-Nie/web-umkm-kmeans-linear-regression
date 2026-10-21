@extends('layouts.auth')
@section('title', 'Masuk ke Sistem')
@section('content')
<div class="auth-page">
    <div class="login-shell">
        <div class="auth-brand">
            <span class="auth-brand-badge"><x-icon name="shield" /></span>
            <span>Strategic DSS Admin</span>
        </div>
        <p class="auth-sub">Silakan masuk untuk mengelola sistem</p>

        @include('partials.flash')

        <section class="card login-card auth-card">
            <h1>Masuk ke Sistem</h1>
            <form method="post" action="{{ route('login') }}">
                @csrf
                <div class="field-row mb-3">
                    <label class="field" for="identity">Alamat Email</label>
                    <input class="form-control" id="identity" name="identity" value="{{ old('identity') }}"
                        placeholder="nama@email.com" autocomplete="username" required autofocus maxlength="191">
                </div>

                <div class="field-row mb-3">
                    <div class="forgot-row d-flex justify-content-between align-items-center mb-1">
                        <label class="field mb-0" for="password">Kata Sandi</label>
                        <a href="{{ route('password.request') }}" class="small">Lupa Kata Sandi?</a>
                    </div>
                    <div class="password-field">
                        <input class="form-control" id="password" name="password" type="password"
                            autocomplete="current-password" placeholder="••••••••" required>
                        <button type="button" class="password-toggle" data-password="password" aria-label="Tampilkan kata sandi">
                            <x-icon name="eye" />
                        </button>
                    </div>
                </div>

                <label class="form-check d-flex gap-2 align-items-center my-3">
                    <input class="form-check-input mt-0" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span class="form-check-label text-muted" style="font-size:13px;">Ingat saya di perangkat ini</span>
                </label>

                <button class="btn btn-login btn-wide mt-2" type="submit">MASUK</button>
            </form>
        </section>

        <div class="auth-links text-center mt-3" style="font-size:12.5px; color:#64748b;">
            Butuh bantuan akses? <strong style="color:var(--accent);">Hubungi IT Support</strong>
        </div>
        <div class="login-register text-center mt-2">
            Belum memiliki akun? <a href="{{ route('register') }}">Daftarkan bisnis</a>
        </div>
    </div>
</div>
@endsection
