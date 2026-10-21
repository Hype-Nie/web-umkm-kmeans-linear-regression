@props(['title' => 'Belum ada data', 'message' => 'Tambahkan data atau unggah berkas Excel/CSV untuk memulai.'])
<div class="empty"><x-icon name="chart" /><strong>{{ $title }}</strong>
    <div>{{ $message }}</div>{{ $slot }}
</div>
