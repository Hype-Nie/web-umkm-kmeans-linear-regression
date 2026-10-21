<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AnalysisService;
use App\Services\RecommendationService;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class TestingDataSeeder extends Seeder
{
    public const EMAIL = 'admin.uji@example.test';

    public const USERNAME = 'admin_uji';

    public const PASSWORD = 'UjiUmkm123!';

    public const SHOP = '[DATA UJI] UMKM';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Seeder sintetis hanya untuk APP_ENV=local/testing. Gunakan data asli pada production.');
        }

        DB::transaction(function () {
            $user = User::where('email', self::EMAIL)->first();
            if ($user && ($user->username !== self::USERNAME || $user->nama_toko !== self::SHOP)) {
                throw new RuntimeException('Email akun uji sudah dipakai akun lain. Seeder dibatalkan tanpa mengubah akun tersebut.');
            }
            if (! $user && User::where('username', self::USERNAME)->exists()) {
                throw new RuntimeException('Username admin_uji sudah dipakai akun lain. Seeder dibatalkan.');
            }
            $user ??= User::create([
                'nama_pemilik' => '[DATA UJI] Administrator', 'nama_toko' => self::SHOP,
                'username' => self::USERNAME, 'email' => self::EMAIL,
                'password' => Hash::make(self::PASSWORD),
            ]);

            // Deterministic synthetic fixtures, clearly named and owned by the dedicated test account.
            // Quantity/frequency differences support real K=3 calculations without assigning fake outputs.
            $fixtures = [
                ['Kopi Bubuk', 'Minuman', '25000.00', '18000.00', 16, 6, 10, 2],
                ['Teh Kemasan', 'Minuman', '12000.00', '8000.00', 14, 5, 10, 5],
                ['Beras Premium', 'Sembako', '75000.00', '65000.00', 12, 6, 10, 8],
                ['Gula Pasir', 'Sembako', '18000.00', '15000.00', 6, 3, 8, 35],
                ['Minyak Goreng', 'Sembako', '20000.00', '16000.00', 5, 3, 8, 45],
                ['Mi Instan', 'Makanan', '3500.00', '2800.00', 4, 2, 8, 60],
                ['Biskuit Kaleng', 'Makanan', '45000.00', '35000.00', 1, 1, 5, 160],
                ['Sirup Buah', 'Minuman', '22000.00', '17000.00', 2, 1, 5, 220],
                ['Saus Botol', 'Makanan', '15000.00', '11000.00', 3, 1, 5, 280],
            ];

            $today = now();
            foreach ($fixtures as $index => [$name, $category, $price, $cost, $base, $frequency, $minimum, $stock]) {
                $code = 'UJI-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
                $product = $user->barang()->firstOrCreate(['kode_barang' => $code], [
                    'nama_barang' => '[DATA UJI] '.$name, 'kategori' => $category,
                    'harga_satuan' => $price, 'harga_beli' => $cost, 'stok_minimum' => $minimum,
                ]);

                // 24 complete historical months plus the current month for dashboard testing.
                for ($offset = 24; $offset >= 0; $offset--) {
                    $month = $today->copy()->startOfMonth()->subMonthsNoOverflow($offset);
                    for ($event = 0; $event < $frequency; $event++) {
                        $date = $month->copy()->addDays(1 + $event * 4)->setTime(9, 0);
                        if ($date->gt($today)) {
                            continue;
                        }
                        $quantity = $base + $month->month % 3;
                        $receipt = 'UJI-'.$code.'-'.$month->format('Ym').'-'.($event + 1);
                        $fingerprint = hash('sha256', 'seeder-data-uji-v1|'.$user->id_user.'|'.$receipt);
                        $product->transaksi()->firstOrCreate(['fingerprint_baris' => $fingerprint], [
                            'kode_transaksi' => $receipt, 'tanggal_transaksi' => $date,
                            'jumlah_terjual' => $quantity, 'harga_satuan_jual' => $price,
                            'total_pendapatan' => Money::total($price, $quantity), 'sumber_data' => 'manual',
                        ]);
                    }
                    $snapshot = $offset === 0 ? $month : $month->copy()->endOfMonth()->setTime(18, 0);
                    $product->stok()->firstOrCreate(['tanggal_update' => $snapshot], [
                        'sisa_stok' => $stock, 'sumber_data' => 'penyesuaian',
                        'keterangan' => '[DATA UJI] Snapshot sintetis untuk pengujian; bukan data penelitian asli.',
                    ]);
                }
            }

            $analysis = app(AnalysisService::class);
            $start = $today->copy()->startOfMonth()->subMonthsNoOverflow(24)->toDateString();
            $end = $today->copy()->startOfMonth()->subDay()->toDateString();
            $previous = $user->proses()->where('status', 'berhasil')->latest('id_proses')->first();
            $metadata = json_decode($previous?->catatan ?? '', true) ?: [];
            if (($metadata['dataset'] ?? '') !== 'data_uji_seeder'
                || ($metadata['data_signature'] ?? '') !== $analysis->signature($user)
                || $previous?->tanggal_akhir_data?->toDateString() !== $end) {
                $previous = $analysis->run($user, $start, $end, false);
                if ($previous->status !== 'berhasil') {
                    throw new RuntimeException('Analisis data uji gagal; seeding dibatalkan: '.$previous->catatan);
                }
                $metadata = json_decode($previous->catatan, true);
                $metadata['dataset'] = 'data_uji_seeder';
                $metadata['peringatan'][] = '[DATA UJI] Semua masukan adalah data sintetis untuk pengujian, bukan hasil penelitian asli.';
                $previous->update(['catatan' => json_encode($metadata, JSON_UNESCAPED_UNICODE)]);
            }
            app(RecommendationService::class)->generate($previous);
        });

        $this->command?->info('Data sintetis [DATA UJI] siap. Login awal (akun baru/reset penuh): '.self::USERNAME.' / '.self::PASSWORD);
    }
}
