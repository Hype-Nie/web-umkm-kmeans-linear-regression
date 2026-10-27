<?php

namespace App\Services;

use App\Models\RiwayatImpor;
use App\Models\TransaksiPenjualan;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ImportService
{
    public const FIELDS = ['kode_transaksi', 'tanggal_transaksi', 'kode_barang', 'nama_barang', 'kategori', 'jumlah_terjual', 'harga_satuan_jual', 'total_pendapatan', 'tanggal_update', 'sisa_stok'];

    public const ALIASES = ['transaction_id' => 'kode_transaksi', 'amount_value' => 'total_pendapatan', 'tanggal' => 'tanggal_transaksi', 'jumlah' => 'jumlah_terjual', 'total_harga' => 'total_pendapatan', 'harga_satuan' => 'harga_satuan_jual'];

    public function date(mixed $value): string
    {
        if (is_numeric($value) && $value > 10000 && $value < 100000) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s');
        }
        $value = trim((string) $value);
        foreach (['Y-m-d H:i:s', 'Y-m-d\TH:i', 'Y-m-d', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->format('Y-m-d H:i:s');
                }
            } catch (\Throwable) {
            }
        }
        throw new DomainException('Tanggal tidak valid. Gunakan YYYY-MM-DD atau DD/MM/YYYY, dengan waktu opsional.');
    }

    public function normalize(User $user, array $raw, array $headers, array $mapping, string $type): array
    {
        if (count($raw) > count($headers)) {
            throw new DomainException('Jumlah nilai pada baris melebihi jumlah kolom header. Periksa delimiter/pemisah CSV.');
        }
        $data = [];
        foreach (self::FIELDS as $field) {
            $column = $mapping[$field] ?? '';
            $index = array_search($column, $headers, true);
            $data[$field] = $column !== '' && $index !== false ? trim((string) ($raw[$index] ?? '')) : '';
        }
        if ($data['kode_barang'] === '' && $data['nama_barang'] === '') {
            throw new DomainException('Kode atau nama barang wajib diisi.');
        }
        $lookup = $user->barang();
        $lookup = $data['kode_barang'] !== '' ? $lookup->where('kode_barang', $data['kode_barang']) : $lookup->where('nama_barang', $data['nama_barang']);
        $matches = $lookup->get();
        if ($matches->count() > 1) {
            throw new DomainException('Nama barang ambigu. Petakan kode_barang yang unik.');
        }
        $product = $matches->first();
        if (! $product && ($data['nama_barang'] === '' || $data['kategori'] === '')) {
            throw new DomainException('Produk belum ditemukan. Produk baru memerlukan nama_barang dan kategori.');
        }
        if ($product && $data['nama_barang'] !== '' && $product->nama_barang !== $data['nama_barang']) {
            throw new DomainException('Kode barang dan nama barang tidak cocok.');
        }
        $validator = Validator::make($data, ['kode_barang' => 'nullable|string|max:60', 'nama_barang' => 'nullable|string|max:191', 'kategori' => 'nullable|string|max:120', 'kode_transaksi' => 'nullable|string|max:100']);
        if ($validator->fails()) {
            throw new DomainException($validator->errors()->first());
        }
        $data['product_id'] = $product?->id_barang;
        if ($data['kode_barang'] === '') {
            $data['kode_barang'] = $product?->kode_barang ?: 'IMP-'.strtoupper(substr(hash('sha256', mb_strtolower($data['nama_barang'])), 0, 16));
        }
        $hasSale = $type === 'transaksi' || ($type === 'gabungan' && ($data['tanggal_transaksi'] !== '' || $data['jumlah_terjual'] !== ''));
        $hasStock = $type === 'stok' || ($type === 'gabungan' && ($data['tanggal_update'] !== '' || $data['sisa_stok'] !== ''));
        if (! $hasSale && ! $hasStock) {
            throw new DomainException('Baris tidak berisi transaksi atau snapshot stok.');
        }
        $data['has_sale'] = $hasSale;
        $data['has_stock'] = $hasStock;
        if ($hasSale) {
            if (! preg_match('/^[1-9]\d*$/D', $data['jumlah_terjual']) || $data['jumlah_terjual'] > 2147483647) {
                throw new DomainException('Jumlah terjual harus bilangan bulat positif.');
            }
            $data['jumlah_terjual'] = (int) $data['jumlah_terjual'];
            $data['tanggal_transaksi'] = $this->date($data['tanggal_transaksi']);
            if (Carbon::parse($data['tanggal_transaksi'])->gt(now())) {
                throw new DomainException('Transaksi historis tidak boleh bertanggal masa depan.');
            }
            try {
                $data['harga_satuan_jual'] = Money::normalize($data['harga_satuan_jual']);
                $total = Money::total($data['harga_satuan_jual'], $data['jumlah_terjual']);
                if ($data['total_pendapatan'] !== '' && (! preg_match('/^\d{1,15}(\.\d{1,2})?$/D', $data['total_pendapatan']) || bccomp($data['total_pendapatan'], $total, 2) !== 0)) {
                    throw new DomainException('Total pendapatan tidak sama dengan jumlah × harga satuan jual.');
                }
                $data['total_pendapatan'] = $total;
            } catch (\InvalidArgumentException $e) {
                throw new DomainException($e->getMessage());
            }
            $data['fingerprint_baris'] = hash('sha256', json_encode([$user->id_user, mb_strtolower($data['kode_barang']), $data['kode_transaksi'], $data['tanggal_transaksi'], $data['jumlah_terjual'], $data['harga_satuan_jual'], $total]));
            if (TransaksiPenjualan::where('fingerprint_baris', $data['fingerprint_baris'])->exists()) {
                throw new DomainException('Transaksi duplikat (fingerprint sudah tersimpan).');
            }
        }
        if ($hasStock) {
            if (! preg_match('/^\d+$/D', $data['sisa_stok']) || $data['sisa_stok'] > 2147483647) {
                throw new DomainException('Sisa stok harus bilangan bulat nonnegatif.');
            }
            $data['sisa_stok'] = (int) $data['sisa_stok'];
            $data['tanggal_update'] = $this->date($data['tanggal_update']);
            if (Carbon::parse($data['tanggal_update'])->gt(now())) {
                throw new DomainException('Snapshot historis tidak boleh bertanggal masa depan.');
            }
            if ($product && $product->stok()->where('tanggal_update', $data['tanggal_update'])->where('sisa_stok', $data['sisa_stok'])->exists()) {
                throw new DomainException('Snapshot stok duplikat pada tanggal dan nilai yang sama.');
            }
        }

        return $data;
    }

    public function preview(User $user, array $file, array $mapping, string $type): array
    {
        $valid = [];
        $errors = [];
        $seen = [];
        foreach ($file['rows'] as $index => $row) {
            if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            $number = $index + 2;
            try {
                $data = $this->normalize($user, $row, $file['headers'], $mapping, $type);
                $keys = [];
                if ($data['has_sale']) {
                    $keys[] = $data['fingerprint_baris'];
                }
                if ($data['has_stock']) {
                    $keys[] = hash('sha256', 'stok|'.$data['kode_barang'].'|'.$data['tanggal_update'].'|'.$data['sisa_stok']);
                }
                foreach ($keys as $key) {
                    if (isset($seen[$key])) {
                        throw new DomainException('Baris duplikat di dalam file.');
                    }
                }
                foreach ($keys as $key) {
                    $seen[$key] = true;
                }
                $valid[] = ['row' => $number, 'data' => $data];
            } catch (DomainException $e) {
                $errors[] = ['baris' => $number, 'pesan' => $e->getMessage()];
            }
        }

        return ['valid' => $valid, 'errors' => $errors];
    }

    public function commit(User $user, RiwayatImpor $history, array $file, array $mapping, bool $partial): array
    {
        return DB::transaction(function () use ($user, $history, $file, $mapping, $partial) {
            // Serializes overlapping imports for this account, including new-product resolution.
            User::whereKey($user->id_user)->lockForUpdate()->firstOrFail();
            $history = RiwayatImpor::lockForUpdate()->findOrFail($history->id_impor);
            if ($history->status !== 'diproses') {
                throw new DomainException('Unggahan ini sudah dikonfirmasi.');
            }
            $preview = $this->preview($user, $file, $mapping, $history->jenis_data);
            if (! $partial && $preview['errors']) {
                $history->update(['status' => 'gagal', 'jumlah_baris' => count($preview['valid']) + count($preview['errors']), 'baris_gagal' => count($preview['errors']), 'rincian_error' => $preview['errors']]);

                return ['success' => 0, 'errors' => $preview['errors']];
            }
            $success = 0;
            $errors = $preview['errors'];
            foreach ($preview['valid'] as $entry) {
                try {
                    DB::transaction(function () use ($user, $history, $entry) {
                        $d = $entry['data'];
                        $p = $user->barang()->where('kode_barang', $d['kode_barang'])->lockForUpdate()->first();
                        if (! $p) {
                            $p = $user->barang()->create(['kode_barang' => $d['kode_barang'], 'nama_barang' => $d['nama_barang'], 'kategori' => $d['kategori'], 'harga_satuan' => $d['has_sale'] ? $d['harga_satuan_jual'] : '0.00']);
                        }
                        if ($d['has_sale']) {
                            $p->transaksi()->create(array_intersect_key($d, array_flip(['kode_transaksi', 'tanggal_transaksi', 'jumlah_terjual', 'harga_satuan_jual', 'total_pendapatan', 'fingerprint_baris'])) + ['id_impor' => $history->id_impor, 'sumber_data' => 'impor']);
                        }
                        // Historical snapshots already represent sales. Never decrement on import.
                        if ($d['has_stock']) {
                            if ($p->stok()->where('tanggal_update', $d['tanggal_update'])->where('sisa_stok', $d['sisa_stok'])->exists()) {
                                throw new DomainException('Snapshot duplikat.');
                            }
                            $p->stok()->create(['tanggal_update' => $d['tanggal_update'], 'sisa_stok' => $d['sisa_stok'], 'sumber_data' => 'impor', 'id_impor' => $history->id_impor, 'keterangan' => 'Snapshot historis, penjualan tidak dikurangi ulang.']);
                        }
                    });
                    $success++;
                } catch (\Throwable $e) {
                    if (! $partial) {
                        throw $e;
                    }
                    $errors[] = ['baris' => $entry['row'], 'pesan' => $e instanceof DomainException ? $e->getMessage() : 'Gagal menyimpan baris, periksa konflik kode atau duplikasi.'];
                    if (! $e instanceof DomainException) {
                        report($e);
                    }
                }
            }
            $history->update(['status' => $success ? ($errors ? 'parsial' : 'berhasil') : 'gagal', 'jumlah_baris' => count($preview['valid']) + count($preview['errors']), 'baris_berhasil' => $success, 'baris_gagal' => count($errors), 'rincian_error' => $errors]);

            return ['success' => $success, 'errors' => $errors];
        });
    }
}
