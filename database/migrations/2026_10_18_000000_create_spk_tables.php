<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'users', 'barang', 'riwayat_impor', 'transaksi_penjualan', 'stok_barang',
        'proses_analisis', 'hasil_analisis_kmeans', 'hasil_prediksi_linear_regression',
        'rekomendasi_keputusan', 'password_reset_tokens',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Migration SPK memerlukan MySQL/MariaDB sesuai SQL sumber.');
        }

        // Keep column types, custom primary keys, indexes and FK names identical to the original SQL.
        // Deliberately exclude CREATE DATABASE / USE, so the configured database is always respected.
        $sql = file_get_contents(database_path('db_spk_ud_zufri.sql'));
        preg_match_all('/CREATE TABLE IF NOT EXISTS\s+`([^`]+)`\s*\(.*?\)\s*ENGINE=InnoDB[^;]*;/s', $sql, $matches, PREG_SET_ORDER);
        if (array_column($matches, 1) !== self::TABLES) {
            throw new RuntimeException('Definisi sepuluh tabel SPK pada SQL sumber tidak lengkap atau urutannya berubah.');
        }

        foreach ($matches as $statement) {
            DB::unprepared($statement[0]);
        }
    }

    public function down(): void
    {
        // Reverse dependency order; do not disable FK checks or touch unrelated tables.
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
