<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Strategic DSS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <style>
        html, body { background-color: #f8fafc !important; }
        @view-transition { navigation: auto; }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    <script defer src="{{ asset('assets/app.js') }}"></script>
</head>

<body>
    <header class="app-top">
        <div class="actions"><button class="icon-button mobile-only" data-menu aria-label="Buka menu"><x-icon
                    name="menu" /></button><a href="{{ route('dashboard') }}" class="brand">Strategic DSS Admin</a>
        </div>
        <div class="top-actions"><a href="{{ route('barang.index') }}" class="icon-button"
                title="Kelola barang dan stok" aria-label="Kelola barang dan stok"><x-icon name="settings" /></a><a
                href="{{ route('profile.index') }}" class="icon-button {{ request()->routeIs('profile.*') ? 'active' : '' }}"
                title="Profil {{ auth()->user()->nama_pemilik }}" aria-label="Profil Pengguna"><x-icon name="user" /></a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="icon-button" title="Keluar"
                    aria-label="Keluar"><x-icon name="logout" /></button></form>
        </div>
    </header>
    <aside class="sidebar">
        <div class="sidebar-title">Admin Panel</div>
        <div class="eyebrow">Strategic DSS</div>
        <nav aria-label="Menu utama">
            @foreach ([['dashboard', 'grid', 'Dashboard', 'dashboard'], ['impor.index', 'table', 'Data Transaksi', 'impor.*,transaksi.*,barang.*'], ['analisis.index', 'chart', 'Hasil Analisis', 'analisis.*'], ['rekomendasi.index', 'bulb', 'Rekomendasi', 'rekomendasi.*'], ['laporan.index', 'file', 'Laporan', 'laporan.*']] as [$route, $icon, $label, $patterns])
                <a class="nav-item {{ request()->routeIs(...explode(',', $patterns)) ? 'active' : '' }}"
                    href="{{ route($route) }}"><x-icon :name="$icon" />{{ $label }}</a>
            @endforeach
        </nav>
        <a href="{{ route('profile.index') }}" class="account {{ request()->routeIs('profile.*') ? 'active' : '' }}" title="Kelola Profil & Akun"><span class="avatar"><x-icon name="user" /></span>
            <div><strong>{{ auth()->user()->nama_pemilik }}</strong><small>{{ auth()->user()->nama_toko }}</small>
            </div>
        </a>
    </aside>
    <main class="main">@include('partials.flash')@yield('content')<footer class="app-footer"><span>©
                {{ date('Y') }} Strategic DSS Admin · Sistem Pendukung Keputusan UMKM</span><span>Business
                Intelligence · Asia/Jakarta</span></footer>
    </main>
</body>

</html>
