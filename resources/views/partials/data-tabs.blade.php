<div class="chips"><a href="{{ route('impor.index') }}"
        class="chip {{ request()->routeIs('impor.*') ? 'active' : '' }}">Upload & Riwayat</a><a
        href="{{ route('barang.index') }}" class="chip {{ request()->routeIs('barang.*') ? 'active' : '' }}">Barang &
        Stok</a><a href="{{ route('transaksi.index') }}"
        class="chip {{ request()->routeIs('transaksi.*') ? 'active' : '' }}">Transaksi Penjualan</a></div>
