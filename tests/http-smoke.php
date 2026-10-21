<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Real HTTP smoke test. Requires a separate server on port 8001 using the test database. */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'db_spk_ud_zufri_test') {
    throw new RuntimeException('Run only with DB_DATABASE=db_spk_ud_zufri_test.');
}
$base = 'http://127.0.0.1:8001';
$cookie = tempnam(sys_get_temp_dir(), 'spk-cookie-');
$file = tempnam(sys_get_temp_dir(), 'spk-http-');
$email = 'http-'.bin2hex(random_bytes(6)).'@example.test';
$checks = 0;
$importPaths = [];
function check(bool $condition, string $message): void
{
    global $checks;
    if (! $condition) {
        throw new RuntimeException('FAIL: '.$message);
    }
    $checks++;
    echo 'PASS: '.$message.PHP_EOL;
}
function http(string $path, ?array $data = null): array
{
    global $base, $cookie;
    $ch = curl_init($base.$path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie, CURLOPT_TIMEOUT => 40, CURLOPT_FOLLOWLOCATION => false]);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array_filter($data, fn ($v) => $v instanceof CURLFile) ? $data : http_build_query($data));
    }
    $body = curl_exec($ch);
    if ($body === false) {
        throw new RuntimeException(curl_error($ch));
    }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return ['body' => $body, 'code' => $code];
}
function token(string $html): string
{
    if (! preg_match('/name="_token" value="([^"]+)"/', $html, $m)) {
        throw new RuntimeException('CSRF token missing.');
    }

    return html_entity_decode($m[1]);
}
try {
    $login = http('/login');
    check($login['code'] === 200 && str_contains($login['body'], 'Masuk ke Sistem'), 'Halaman login HTTP nyata');
    check(http('/login', ['identity' => 'x', 'password' => 'x'])['code'] === 419, 'POST tanpa token ditolak CSRF');
    $register = http('/register');
    $result = http('/register', ['_token' => token($register['body']), 'nama_pemilik' => 'Pengujian HTTP', 'nama_toko' => 'Toko Uji HTTP', 'username' => 'http_'.bin2hex(random_bytes(5)), 'email' => $email, 'password' => 'HttpPassword123', 'password_confirmation' => 'HttpPassword123', 'terms' => '1']);
    check($result['code'] === 302, 'Pendaftaran melalui HTTP berhasil');
    $user = User::where('email', $email)->firstOrFail();
    check(Hash::check('HttpPassword123', $user->password), 'Hash password tersimpan pada MySQL uji');
    foreach (['/dashboard', '/barang', '/transaksi', '/impor', '/analisis', '/rekomendasi', '/laporan'] as $url) {
        check(http($url)['code'] === 200, 'Halaman terlindungi '.$url);
    }
    $form = http('/barang/create');
    check(http('/barang', ['_token' => token($form['body']), 'kode_barang' => 'HTTP-1', 'nama_barang' => 'Produk Uji HTTP', 'kategori' => 'Uji', 'harga_satuan' => '0.10', 'stok_minimum' => '5'])['code'] === 302, 'CRUD barang melalui form HTTP');
    $product = $user->barang()->firstOrFail();
    $edit = http('/barang/'.$product->id_barang.'/edit');
    check(http('/barang/'.$product->id_barang.'/stok', ['_token' => token($edit['body']), 'jenis' => 'stok_awal', 'jumlah' => '20', 'tanggal_update' => '2025-01-01 10:00:00'])['code'] === 302, 'Stok awal melalui HTTP');
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray([['kode_barang', 'tanggal_transaksi', 'jumlah_terjual', 'harga_satuan_jual'], ['HTTP-1', '2025-02-01', 3, '0.10']]);
    (new Xlsx($book))->save($file);
    $book->disconnectWorksheets();
    $upload = http('/impor', ['_token' => token($edit['body']), 'jenis_data' => 'transaksi', 'file' => new CURLFile($file, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'test-http.xlsx')]);
    check($upload['code'] === 302, 'Upload multipart XLSX melalui HTTP');
    $history = $user->impor()->latest('id_impor')->firstOrFail();
    $importPaths[] = $history->path_file;
    $preview = http('/impor/'.$history->id_impor.'/preview');
    check($preview['code'] === 200 && str_contains($preview['body'], '0.30'), 'Preview XLSX menampilkan total desimal benar');
    $confirm = ['_token' => token($preview['body']), 'confirmed' => 1];
    foreach (['kode_barang', 'tanggal_transaksi', 'jumlah_terjual', 'harga_satuan_jual'] as $field) {
        $confirm['mapping['.$field.']'] = $field;
    }
    check(http('/impor/'.$history->id_impor.'/confirm', $confirm)['code'] === 302, 'Konfirmasi impor melalui HTTP');
    check($product->transaksi()->firstOrFail()->total_pendapatan === '0.30', 'Transaksi impor nyata tersimpan pada MySQL');
    check((int) $product->fresh()->stokTerakhir->sisa_stok === 20, 'Impor historis tidak mengurangi stok dua kali');
    $report = http('/laporan/export?mulai=2025-01-01&akhir=2025-03-31&format=pdf');
    check($report['code'] === 200 && str_starts_with($report['body'], '%PDF-'), 'Ekspor PDF nyata melalui HTTP');
    $report = http('/laporan/export?mulai=2025-01-01&akhir=2025-03-31&format=xlsx');
    check($report['code'] === 200 && str_starts_with($report['body'], 'PK'), 'Ekspor XLSX nyata melalui HTTP');
    $dashboard = http('/dashboard');
    check(http('/logout', ['_token' => token($dashboard['body'])])['code'] === 302, 'Logout melalui HTTP');
    check(http('/dashboard')['code'] === 302, 'Sesi terlindungi setelah logout');
    echo $checks.' pemeriksaan HTTP lulus.'.PHP_EOL;
} finally {
    // Exact test owner only; its FK cascade removes this test account's temporary business data.
    $user = User::where('email', $email)->first();
    if ($user) {
        $user->delete();
    }
    foreach ($importPaths as $path) {
        Storage::disk('local')->delete($path);
    }
    if (is_file($cookie)) {
        unlink($cookie);
    }
    if (is_file($file)) {
        unlink($file);
    }
}
