<?php

namespace App\Services;

use DomainException;

class RegressionService
{
    public function fit(array $y): array
    {
        $n = count($y);
        if ($n < 2) {
            throw new DomainException('Regresi memerlukan minimal 2 titik waktu.');
        }
        $xMean = ($n + 1) / 2;
        $yMean = array_sum($y) / $n;
        $numerator = 0;
        $denominator = 0;
        foreach (array_values($y) as $i => $value) {
            $numerator += (($i + 1) - $xMean) * ($value - $yMean);
            $denominator += (($i + 1) - $xMean) ** 2;
        }
        $b = $numerator / $denominator;
        $a = $yMean - $b * $xMean;

        return ['a' => $a, 'b' => $b, 'raw' => $a + $b * ($n + 1)];
    }

    public function forecast(array $series): array
    {
        if (count($series) < 3) {
            throw new DomainException('Data tidak cukup: minimal 3 bulan lengkap untuk model dan evaluasi bergulir.');
        }
        $values = array_values($series);
        $fit = $this->fit($values);
        $errors = [];
        $actual = [];
        for ($i = 2; $i < count($values); $i++) {
            $prediction = max(0, $this->fit(array_slice($values, 0, $i))['raw']);
            $errors[] = $prediction - $values[$i];
            $actual[] = $values[$i];
        }
        $mse = array_sum(array_map(fn ($e) => $e * $e, $errors)) / count($errors);
        $mae = array_sum(array_map('abs', $errors)) / count($errors);
        $mean = array_sum($actual) / count($actual);
        $sst = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $actual));

        return $fit + ['units' => (int) ceil(max(0, $fit['raw'])), 'mse' => $mse, 'rmse' => sqrt($mse), 'mae' => $mae, 'r_squared' => count($actual) > 1 && $sst > 0 ? 1 - array_sum(array_map(fn ($e) => $e * $e, $errors)) / $sst : null, 'validation_points' => count($errors), 'validation_predictions' => array_map(fn ($i) => $actual[$i] + $errors[$i], array_keys($actual)), 'validation_actual' => $actual];
    }
}
