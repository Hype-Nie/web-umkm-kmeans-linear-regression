@extends('layouts.app')
@section('title', 'Profil Pengguna')
@section('content')
    <div class="page-head">
        <div>
            <h1>Profil Pengguna</h1>
            <p class="subtitle">Kelola informasi identitas pemilik, unit usaha {{ $user->nama_toko }}, dan keamanan akun.</p>
        </div>
    </div>

    <div class="profile-layout">
        <div class="profile-main">
            <!-- Informasi Akun & Usaha -->
            <section class="card mb-4">
                <div class="card-head">
                    <h2>Informasi Pemilik & Usaha</h2>
                </div>
                <form method="post" action="{{ route('profile.update') }}" class="mt-3" data-submit-lock>
                    @csrf
                    @method('put')

                    <div class="mb-3">
                        <label class="field" for="nama_pemilik">Nama Lengkap Pemilik</label>
                        <input class="form-control @error('nama_pemilik') is-invalid @enderror" type="text"
                            id="nama_pemilik" name="nama_pemilik" value="{{ old('nama_pemilik', $user->nama_pemilik) }}"
                            required autocomplete="name">
                        @error('nama_pemilik')
                            <p class="form-help text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="field" for="nama_toko">Nama Usaha / Toko</label>
                        <input class="form-control @error('nama_toko') is-invalid @enderror" type="text"
                            id="nama_toko" name="nama_toko" value="{{ old('nama_toko', $user->nama_toko) }}"
                            required>
                        @error('nama_toko')
                            <p class="form-help text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="field" for="username">Username</label>
                            <input class="form-control @error('username') is-invalid @enderror" type="text"
                                id="username" name="username" value="{{ old('username', $user->username) }}"
                                required autocomplete="username">
                            @error('username')
                                <p class="form-help text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="field" for="email">Alamat Email</label>
                            <input class="form-control @error('email') is-invalid @enderror" type="email"
                                id="email" name="email" value="{{ old('email', $user->email) }}"
                                required autocomplete="email">
                            @error('email')
                                <p class="form-help text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 pt-2 border-top d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </section>

            <!-- Ganti Password -->
            <section class="card mb-4">
                <div class="card-head">
                    <h2>Keamanan & Kata Sandi</h2>
                </div>
                <form method="post" action="{{ route('profile.password') }}" class="mt-3" data-submit-lock>
                    @csrf
                    @method('put')

                    <div class="mb-3">
                        <label class="field" for="current_password">Kata Sandi Saat Ini</label>
                        <input class="form-control @error('current_password') is-invalid @enderror" type="password"
                            id="current_password" name="current_password" required autocomplete="current-password"
                            placeholder="Masukkan kata sandi lama Anda">
                        @error('current_password')
                            <p class="form-help text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="field" for="password">Kata Sandi Baru</label>
                            <input class="form-control @error('password') is-invalid @enderror" type="password"
                                id="password" name="password" required autocomplete="new-password"
                                placeholder="Minimal 8 karakter">
                            @error('password')
                                <p class="form-help text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="field" for="password_confirmation">Konfirmasi Kata Sandi Baru</label>
                            <input class="form-control" type="password" id="password_confirmation"
                                name="password_confirmation" required autocomplete="new-password"
                                placeholder="Ulangi kata sandi baru">
                        </div>
                    </div>

                    <p class="form-help mt-2">Gunakan kombinasi huruf, angka, dan simbol untuk menjaga keamanan akses sistem SPK UD. Zufri.</p>

                    <div class="mt-4 pt-2 border-top d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            Perbarui Kata Sandi
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <div class="profile-side">
            <!-- Profil Card -->
            <div class="card mb-4 text-center">
                <div class="profile-avatar-large mx-auto mb-3">
                    <x-icon name="user" />
                </div>
                <h3 class="mb-1">{{ $user->nama_pemilik }}</h3>
                <p class="text-muted small mb-3">{{ $user->nama_toko }}</p>
                <div class="d-inline-flex justify-content-center">
                    <span class="badge badge-primary">PEMILIK USAHA (ADMIN)</span>
                </div>

                <hr class="my-3">

                <div class="text-start small">
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Username:</span>
                        <strong>{{ $user->username }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Email:</span>
                        <strong>{{ $user->email }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Terdaftar Sejak:</span>
                        <span>{{ $user->created_at?->format('d/m/Y') ?? '—' }}</span>
                    </div>
                </div>
            </div>

            <!-- Ringkasan Operasional -->
            <div class="card mb-4">
                <div class="card-head">
                    <h2>Aktivitas Sistem SPK</h2>
                </div>
                <div class="stat-list mt-3">
                    <div class="stat-list-item">
                        <span class="text-muted">Produk Terdaftar</span>
                        <strong>{{ number_format($stats['total_barang']) }} produk</strong>
                    </div>
                    <div class="stat-list-item">
                        <span class="text-muted">Total Transaksi</span>
                        <strong>{{ number_format($stats['total_transaksi']) }} rekaman</strong>
                    </div>
                    <div class="stat-list-item">
                        <span class="text-muted">Sesi Analisis Selesai</span>
                        <strong>{{ number_format($stats['total_proses']) }} proses</strong>
                    </div>
                    <div class="stat-list-item">
                        <span class="text-muted">Analisis Terakhir</span>
                        <span>{{ $stats['proses_terakhir']?->created_at->format('d/m/Y H:i') ?? 'Belum ada' }}</span>
                    </div>
                </div>
            </div>

            <!-- Pedoman Keamanan -->
            <div class="info-box">
                <strong>Integritas Data Strategis</strong>
                <p class="form-help mt-1 mb-0">Akun ini memiliki hak akses penuh untuk melakukan peramalan stok, klasterisasi penjualan, serta mengunduh laporan rahasia retail UD. Zufri.</p>
            </div>
        </div>
    </div>
@endsection
