<?php

namespace App\Services;

use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class RegressionService
{
    private function hitPythonApi(string $endpoint, array $payload): array
    {
        $apiUrl = config('services.ml.base_url', env('PYTHON_ML_API_URL', 'http://127.0.0.1:8000'));
        try {
            $response = Http::timeout(3.0)->post("{$apiUrl}{$endpoint}", $payload);

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data) && ($data['status'] ?? '') === 'success') {
                    return $data;
                }
            }

            throw new DomainException(
                'Layanan Machine Learning Python mengembalikan respons tidak valid: '.
                ($response->body() ?: 'Kode HTTP '.$response->status())
            );
        } catch (ConnectionException) {
            throw new DomainException(
                "Server Machine Learning Python belum aktif di {$apiUrl}. ".
                "Silakan jalankan perintah 'python finals/script/ml_api.py' di terminal sebelum melakukan analisis."
            );
        } catch (\Throwable $e) {
            if ($e instanceof DomainException) {
                throw $e;
            }
            throw new DomainException(
                "Gagal menghubungkan ke Server Machine Learning Python ({$apiUrl}): ".$e->getMessage().
                '. Pastikan server Python telah aktif.'
            );
        }
    }

    public function fit(array $y): array
    {
        $n = count($y);
        if ($n < 2) {
            throw new DomainException('Regresi memerlukan minimal 2 titik waktu.');
        }

        // Inferensi parameter garis regresi linear via API Python
        $endpoint = config('services.ml.endpoints.regression_predict', env('ML_API_REGRESSION_PREDICT_ENDPOINT', '/predict/linear-regression'));
        $res = $this->hitPythonApi($endpoint, [
            'series' => array_values($y),
        ]);

        return [
            'a' => (float) $res['intercept'],
            'b' => (float) $res['slope'],
            'raw' => (float) $res['raw_prediction'],
            'source' => 'python_ml_api',
        ];
    }

    public function forecast(array $series): array
    {
        if (count($series) < 3) {
            throw new DomainException('Data tidak cukup: minimal 3 bulan lengkap untuk model dan evaluasi bergulir.');
        }

        // Peramalan dan evaluasi regresi linear via API Python
        $endpoint = config('services.ml.endpoints.regression_forecast', env('ML_API_REGRESSION_FORECAST_ENDPOINT', '/forecast/linear-regression'));
        $res = $this->hitPythonApi($endpoint, [
            'series' => array_values($series),
        ]);

        return [
            'a' => (float) $res['a'],
            'b' => (float) $res['b'],
            'raw' => (float) $res['raw'],
            'units' => (int) $res['units'],
            'mse' => (float) $res['mse'],
            'rmse' => (float) $res['rmse'],
            'mae' => (float) $res['mae'],
            'r_squared' => $res['r_squared'] !== null ? (float) $res['r_squared'] : null,
            'validation_points' => (int) $res['validation_points'],
            'validation_predictions' => $res['validation_predictions'],
            'validation_actual' => $res['validation_actual'],
            'source' => 'python_ml_api',
        ];
    }
}
