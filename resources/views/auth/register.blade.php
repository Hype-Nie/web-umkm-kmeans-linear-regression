@extends('layouts.auth')
@section('title', 'Buat Akun')
@section('content')
    <div class="register-page">
        <section class="register-story">
            <a class="brand" href="{{ route('login') }}">
                <span class="auth-brand-badge"><x-icon name="shield" /></span>
                <span>Strategic DSS Admin</span>
            </a>
            <h1>Optimalkan<br>Keputusan Strategis<br>Bisnis Anda.</h1>
            @foreach ([
                ['chart', 'ANALISIS BERBASIS DATA', 'Pahami tren transaksi dan posisi persediaan dengan visualisasi yang akurat.'],
                ['bulb', 'REKOMENDASI CERDAS', 'Susun keputusan restock dan promosi berdasarkan analisis penjualan bisnis Anda.'],
                ['file', 'LAPORAN TERINTEGRASI', 'Tinjau dan unduh laporan penjualan, stok, serta hasil analisis kapan diperlukan.']
            ] as [$icon, $title, $text])
                <div class="feature">
                    <span class="feature-icon-badge"><x-icon :name="$icon" /></span>
                    <div>
                        <strong>{{ $title }}</strong>
                        <p>{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </section>
        <section class="register-form auth-card">
            <h1>Buat Akun</h1>
            <p class="subtitle">Mulailah transformasi digital bisnis Anda hari ini.</p>
            @include('partials.flash')
            <form method="post" action="{{ route('register') }}">
                @csrf
                @foreach ([
                    ['nama_pemilik', 'Nama Lengkap', 'Contoh: Budi Santoso'],
                    ['nama_toko', 'Nama Toko / Bisnis', 'Contoh: UD. Zufri'],
                    ['username', 'Username', 'Contoh: budi_zufri'],
                    ['email', 'Alamat Email', 'email@bisnisanda.com']
                ] as [$name, $label, $placeholder])
                    <div class="field-row">
                        <label class="field" for="{{ $name }}">{{ $label }}</label>
                        <input class="form-control" id="{{ $name }}" name="{{ $name }}"
                            value="{{ old($name) }}" type="{{ $name === 'email' ? 'email' : 'text' }}"
                            placeholder="{{ $placeholder }}" required maxlength="{{ $name === 'username' ? 100 : 191 }}"
                            autocomplete="{{ $name === 'username' ? 'username' : ($name === 'email' ? 'email' : 'off') }}">
                    </div>
                @endforeach
                @foreach (['password' => 'Kata Sandi', 'password_confirmation' => 'Konfirmasi Kata Sandi'] as $name => $label)
                    <div class="field-row">
                        <label class="field" for="{{ $name }}">{{ $label }}</label>
                        <div class="password-field">
                            <input class="form-control" id="{{ $name }}"
                                name="{{ $name }}" type="password" required minlength="8"
                                autocomplete="new-password">
                            <button class="password-toggle" type="button"
                                data-password="{{ $name }}" aria-label="Tampilkan kata sandi">
                                <x-icon name="eye" />
                            </button>
                        </div>
                    </div>
                @endforeach
                <p class="form-help mb-3" style="font-size:12px; color:var(--muted);">Minimal 8 karakter dengan kombinasi huruf dan angka.</p>
                <label class="form-check d-flex gap-2 align-items-center mb-3">
                    <input class="form-check-input mt-0" type="checkbox" name="terms"
                        value="1" required @checked(old('terms'))>
                    <span class="form-check-label text-muted" style="font-size:13px;">
                        Saya menyetujui <a href="{{ route('terms') }}" target="_blank" style="color:var(--accent); font-weight:600; text-decoration:underline;">Syarat & Ketentuan serta Kebijakan Privasi</a>.
                    </span>
                </label>
                <button class="btn btn-login btn-wide mt-2" type="submit">DAFTARKAN BISNIS</button>
            </form>
            <div class="login-register text-center mt-3">
                Sudah memiliki akun? <a href="{{ route('login') }}">Masuk di sini</a>
            </div>
            <p class="auth-note text-center mt-3 mb-0">Data bisnis dikelola aman di dalam akun Anda.</p>
        </section>
    </div>
    <footer class="auth-footer">
        <span>© {{ date('Y') }} Strategic DSS Admin. Seluruh Hak Cipta Dilindungi.</span>
        <a href="{{ route('terms') }}">Ketentuan & Privasi</a>
    </footer>
@endsection
