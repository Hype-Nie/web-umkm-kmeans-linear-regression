<?php

namespace App\Services;

use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class KMeansService
{
    private function hitPythonApi(string $endpoint, array $payload): array
    {
        $apiUrl = config('services.ml.base_url', env('PYTHON_ML_API_URL', 'http://127.0.0.1:8000'));
        try {
            $response = Http::timeout(5.0)->post("{$apiUrl}{$endpoint}", $payload);

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

    public function distance(array $a, array $b): float
    {
        $sum = 0;
        foreach ($a as $i => $val) {
            $sum += ($val - ($b[$i] ?? 0)) ** 2;
        }

        return sqrt($sum);
    }

    public function calculate(array $features): array
    {
        if (count($features) < 3 || count(array_unique(array_map(fn ($p) => json_encode(array_values($p)), $features))) < 3) {
            throw new DomainException('K=3 memerlukan sedikitnya 3 produk dengan kombinasi fitur yang berbeda.');
        }

        // Pemrosesan klastering K-Means via API Python menggunakan model_kmeans.mod
        $formatted = [];
        foreach ($features as $id => $f) {
            $formatted[(string) $id] = array_map('floatval', array_values($f));
        }

        $endpoint = config('services.ml.endpoints.kmeans_cluster', env('ML_API_KMEANS_CLUSTER_ENDPOINT', '/cluster/kmeans'));
        $res = $this->hitPythonApi($endpoint, [
            'features' => $formatted,
        ]);

        $assignments = [];
        foreach ($res['assignments'] as $id => $c) {
            $assignments[$id] = (int) $c;
        }

        $distances = [];
        foreach ($res['distances'] as $id => $d) {
            $distances[$id] = (float) $d;
        }

        $centroids = [];
        foreach ($res['centroids'] as $cIdx => $coords) {
            $centroids[(int) $cIdx] = array_map('floatval', $coords);
        }

        $labels = [];
        foreach ($res['labels'] as $cIdx => $lbl) {
            $labels[(int) $cIdx] = (string) $lbl;
        }

        return [
            'assignments' => $assignments,
            'centroids' => $centroids,
            'labels' => $labels,
            'silhouette' => $res['silhouette'] !== null ? (float) $res['silhouette'] : null,
            'iterations' => (int) $res['iterations'],
            'mean' => array_map('floatval', $res['mean']),
            'sd' => array_map('floatval', $res['sd']),
            'distances' => $distances,
            'source' => 'python_ml_api',
            'mod_file' => $res['mod_file'] ?? 'model_kmeans.mod',
        ];
    }
}
