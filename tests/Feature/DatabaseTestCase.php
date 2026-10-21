<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.connections.mysql.database') !== 'db_spk_ud_zufri_test') {
            throw new \RuntimeException('Tests only run on db_spk_ud_zufri_test.');
        }
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    protected function owner(string $suffix = 'a'): User
    {
        return User::create(['username' => 'owner_'.$suffix, 'nama_pemilik' => 'Pemilik '.$suffix, 'nama_toko' => 'Toko Uji '.$suffix, 'email' => $suffix.'@example.test', 'password' => Hash::make('Rahasia123')]);
    }

    protected function product(User $user, string $code = 'P1'): Barang
    {
        return $user->barang()->create(['kode_barang' => $code, 'nama_barang' => 'Produk '.$code, 'kategori' => 'Kategori Uji', 'harga_satuan' => '12500.25', 'stok_minimum' => 5]);
    }

    protected function history(Barang $p, array $quantities): void
    {
        foreach ($quantities as $i => $q) {
            $p->transaksi()->create(['tanggal_transaksi' => '2025-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).'-15 12:00:00', 'jumlah_terjual' => $q, 'harga_satuan_jual' => '100.10', 'total_pendapatan' => bcmul((string) $q, '100.10', 2), 'sumber_data' => 'manual']);
        }
    }
}
