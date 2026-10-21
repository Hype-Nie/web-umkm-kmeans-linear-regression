@extends('layouts.auth')
@section('title', 'Reset Kata Sandi')
@section('content')
<div class="auth-page">
    <div class="login-shell">
        <div class="auth-brand">
            <span class="auth-brand-badge"><x-icon name="shield" /></span>
            <span>Strategic DSS Admin</span>
        </div>
        <p class="auth-sub">Tetapkan kata sandi baru</p>

        @include('partials.flash')

        <section class="card login-card auth-card">
            <h1>Reset Kata Sandi</h1>
            <form method="post" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="field-row mb-3">
                    <label class="field" for="email">Alamat Email</label>
                    <input class="form-control" type="email" id="email" name="email"
                        value="{{ old('email', request('email')) }}" required>
                </div>
                @foreach (['password' => 'Kata Sandi Baru', 'password_confirmation' => 'Konfirmasi Kata Sandi'] as $name => $label)
                    <div class="field-row mb-3">
                        <label class="field" for="{{ $name }}">{{ $label }}</label>
                        <div class="password-field">
                            <input class="form-control" type="password" id="{{ $name }}" name="{{ $name }}"
                                autocomplete="new-password" required minlength="8">
                            <button class="password-toggle" type="button" data-password="{{ $name }}" aria-label="Tampilkan kata sandi">
                                <x-icon name="eye" />
                            </button>
                        </div>
                    </div>
                @endforeach
                <p class="form-help mb-3" style="font-size:12px; color:var(--muted);">Minimal 8 karakter, huruf dan angka.</p>
                <button class="btn btn-login btn-wide mt-2" type="submit">SIMPAN KATA SANDI</button>
            </form>
        </section>
    </div>
</div>
@endsection
