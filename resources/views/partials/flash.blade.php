@if (auth()->check() && str_starts_with(auth()->user()->nama_toko, '[DATA UJI]'))
    <div class="alert alert-warning" role="status">
        <strong>DATA UJI</strong> · Data sintetis untuk pengujian aplikasi. Grafik, prediksi, dan rekomendasi pada akun
        ini bukan hasil penelitian dari data bisnis asli.
    </div>
@endif
@foreach (['success', 'warning'] as $kind)
    @if (session($kind))
        <div class="alert alert-{{ $kind }}" role="status">{{ str_replace(';', ',', session($kind)) }}</div>
    @endif
@endforeach
@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="flash-list">
            @foreach ($errors->all() as $error)
                <li>{{ str_replace(';', ',', $error) }}</li>
            @endforeach
        </ul>
    </div>
@endif
