<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function normalize(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d{1,13}(\.\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Nilai uang harus angka nonnegatif, maksimal 13 digit dan 2 desimal (contoh 12500.50).');
        }

        return bcadd($value, '0', 2);
    }

    public static function total(mixed $price, int $quantity): string
    {
        $total = bcmul(self::normalize($price), (string) $quantity, 2);
        if (bccomp($total, '999999999999999.99', 2) > 0) {
            throw new InvalidArgumentException('Total pendapatan melebihi kapasitas kolom database.');
        }

        return $total;
    }

    public static function format(mixed $value): string
    {
        [$whole, $fraction] = explode('.', bcadd((string) ($value ?? '0'), '0', 2));

        return 'Rp '.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.$fraction;
    }
}
