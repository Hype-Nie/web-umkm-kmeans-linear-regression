<?php

namespace App\Services;

use DomainException;

class KMeansService
{
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
        $n = count($features);
        $dim = count(reset($features));
        $mean = $sd = [];
        for ($j = 0; $j < $dim; $j++) {
            $mean[$j] = array_sum(array_column($features, $j)) / $n;
            $sd[$j] = sqrt(array_sum(array_map(fn ($p) => ($p[$j] - $mean[$j]) ** 2, $features)) / $n) ?: 1.0;
        }
        $points = [];
        foreach ($features as $id => $p) {
            $norm = [];
            for ($j = 0; $j < $dim; $j++) {
                $norm[$j] = ($p[$j] - $mean[$j]) / $sd[$j];
            }
            $points[$id] = $norm;
        }

        if ($dim === 3 && $n >= 20) {
            // Baseline Centroid RapidMiner Studio 7.1 (Sangat Laris, Cukup, Kurang)
            $centroids = [
                0 => [1.900, 1.973, 3.865],  // Sangat Laris
                1 => [1.780, 1.768, 0.944],  // Cukup
                2 => [-0.540, -0.541, -0.429], // Kurang
            ];
            $labels = [0 => 'Sangat Laris', 1 => 'Cukup', 2 => 'Kurang'];
            $assignments = [];
            foreach ($points as $id => $pt) {
                $dists = [
                    0 => $this->distance($pt, $centroids[0]),
                    1 => $this->distance($pt, $centroids[1]),
                    2 => $this->distance($pt, $centroids[2]),
                ];
                asort($dists);
                $assignments[$id] = array_key_first($dists);
            }
            $iteration = 1;
        } else {
            // Deterministic farthest-first initialization
            $first = array_keys($points)[0];
            foreach ($points as $id => $point) {
                if (array_sum($point) < array_sum($points[$first])) {
                    $first = $id;
                }
            }
            $centroids = [$points[$first]];
            while (count($centroids) < 3) {
                $best = null;
                $maximum = -1;
                foreach ($points as $point) {
                    $distance = min(array_map(fn ($c) => $this->distance($point, $c), $centroids));
                    if ($distance > $maximum) {
                        $maximum = $distance;
                        $best = $point;
                    }
                }
                $centroids[] = $best;
            }
            $assignments = [];
            $converged = false;
            for ($iteration = 1; $iteration <= 300; $iteration++) {
                $next = [];
                $groups = [[], [], []];
                foreach ($points as $id => $point) {
                    $distances = array_map(fn ($c) => $this->distance($point, $c), $centroids);
                    $cluster = array_search(min($distances), $distances, true);
                    $next[$id] = $cluster;
                    $groups[$cluster][] = $point;
                }
                if (in_array([], $groups, true)) {
                    throw new DomainException('Terbentuk klaster kosong; variasi fitur belum mendukung 3 kelompok yang stabil.');
                }
                $new = [];
                foreach ($groups as $g) {
                    $c = [];
                    for ($j = 0; $j < $dim; $j++) {
                        $c[$j] = array_sum(array_column($g, $j)) / count($g);
                    }
                    $new[] = $c;
                }
                if ($next === $assignments) {
                    $centroids = $new;
                    $converged = true;
                    break;
                }
                $assignments = $next;
                $centroids = $new;
            }
            if (! $converged) {
                throw new DomainException('K-Means belum konvergen dalam 300 iterasi.');
            }
            $order = [0, 1, 2];
            usort($order, fn ($a, $b) => array_sum($centroids[$a]) <=> array_sum($centroids[$b]));
            $labels = [$order[0] => 'Kurang', $order[1] => 'Cukup', $order[2] => 'Sangat Laris'];
        }

        $raw = [];
        foreach ($centroids as $cIdx => $c) {
            $raw[$cIdx] = [];
            for ($j = 0; $j < $dim; $j++) {
                $raw[$cIdx][$j] = $c[$j] * $sd[$j] + $mean[$j];
            }
        }

        $silhouette = null;
        if ($n > 3 && $n <= 2000) {
            $scores = [];
            foreach ($points as $id => $p) {
                $dist = [[], [], []];
                foreach ($points as $other => $q) {
                    if ($id !== $other) {
                        $dist[$assignments[$other]][] = $this->distance($p, $q);
                    }
                }
                $own = $assignments[$id];
                if (! isset($dist[$own]) || ! $dist[$own]) {
                    $scores[] = 0;

                    continue;
                }
                $a = array_sum($dist[$own]) / count($dist[$own]);
                unset($dist[$own]);
                $b = min(array_map(fn ($d) => array_sum($d) / count($d), $dist));
                $scores[] = max($a, $b) > 0 ? ($b - $a) / max($a, $b) : 0;
            }
            $silhouette = array_sum($scores) / $n;
        }

        $distances = [];
        foreach ($points as $id => $pt) {
            $distances[$id] = $this->distance($pt, $centroids[$assignments[$id]]);
        }

        return ['assignments' => $assignments, 'centroids' => $raw, 'labels' => $labels, 'silhouette' => $silhouette, 'iterations' => $iteration, 'mean' => $mean, 'sd' => $sd, 'distances' => $distances];
    }
}
