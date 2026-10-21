<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('spk:install', function () {
    if (config('database.default') !== 'mysql') {
        $this->error('Gunakan DB_CONNECTION=mysql.');

        return 1;
    }
    $config = config('database.connections.mysql');
    $database = $config['database'];
    if (! preg_match('/^[a-zA-Z0-9_]+$/D', $database)) {
        throw new RuntimeException('Nama database tidak valid.');
    }
    $pdo = new PDO('mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `'.$database.'`');
    $sql = file_get_contents(database_path('db_spk_ud_zufri.sql'));
    $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS `db_spk_ud_zufri`\s+DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\s*USE `db_spk_ud_zufri`;/s', '', $sql);
    // All statements in the supplied schema are non-destructive CREATE TABLE IF NOT EXISTS.
    $pdo->exec($sql);
    $this->info('Skema SQL asli berhasil diimpor tanpa seeder atau tabel framework tambahan.');
})->purpose('Impor skema SQL asli ke database MySQL terkonfigurasi');
