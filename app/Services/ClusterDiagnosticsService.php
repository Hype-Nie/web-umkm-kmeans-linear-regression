<?php

namespace App\Services;

class ClusterDiagnosticsService
{
    /** Exploratory K comparison, independent of the saved production assignments. */
    public function calculate(array $features): array
    {
        $features = array_values($features);
        $n = count($features);
        if ($n < 3 || $n > 500) {
            return ['points' => [], 'reason' => 'Evaluasi pembanding tersedia untuk 3–500 produk.'];
        }
        $dimensions = count($features[0]);
        $means = $sd = [];
        for ($d = 0; $d < $dimensions; $d++) {
            $means[$d] = array_sum(array_column($features, $d)) / $n;
            $sd[$d] = sqrt(array_sum(array_map(fn ($p) => ($p[$d] - $means[$d]) ** 2, $features)) / $n) ?: 1;
        }
        $points = array_map(function ($p) use ($means, $sd) {
            return array_map(fn ($d) => ($p[$d] - $means[$d]) / $sd[$d], array_keys($means));
        }, $features);
        $unique = count(array_unique(array_map('serialize', $points)));
        $maxK = min(8, $unique, $n - 1);
        $centers = [array_fill(0, $dimensions, 0.0)];
        $results = [];
        for ($k = 1; $k <= $maxK; $k++) {
            if ($k > 1) {
                $distances = array_map(fn ($p) => min(array_map(fn ($c) => $this->squared($p, $c), $centers)), $points);
                $centers[] = $points[array_search(max($distances), $distances)];
            }
            $assignments = [];
            for ($iteration = 0; $iteration < 100; $iteration++) {
                $next = $groups = [];
                foreach ($points as $i => $p) {
                    $distances = array_map(fn ($c) => $this->squared($p, $c), $centers);
                    $cluster = array_search(min($distances), $distances);
                    $next[$i] = $cluster;
                    $groups[$cluster][] = $p;
                }
                foreach ($groups as $cluster => $group) {
                    $centers[$cluster] = array_map(fn ($d) => array_sum(array_column($group, $d)) / count($group), range(0, $dimensions - 1));
                }
                if ($assignments === $next) {
                    break;
                }
                $assignments = $next;
            }
            $sse = 0;
            foreach ($points as $i => $p) {
                $sse += $this->squared($p, $centers[$assignments[$i]]);
            }
            $scores = [];
            if ($k > 1 && count(array_unique($assignments)) === $k) {
                foreach ($points as $i => $p) {
                    $distances = [];
                    foreach ($points as $j => $q) {
                        if ($i !== $j) {
                            $distances[$assignments[$j]][] = sqrt($this->squared($p, $q));
                        }
                    }
                    $own = $assignments[$i];
                    if (empty($distances[$own])) {
                        $scores[] = 0;
                        continue;
                    }
                    $a = array_sum($distances[$own]) / count($distances[$own]);
                    unset($distances[$own]);
                    $b = min(array_map(fn ($g) => array_sum($g) / count($g), $distances));
                    $scores[] = max($a, $b) > 0 ? ($b - $a) / max($a, $b) : 0;
                }
            }
            $results[] = ['k' => $k, 'sse' => round($sse, 6), 'silhouette' => $scores ? round(array_sum($scores) / $n, 6) : null];
        }

        return ['points' => $results, 'reason' => null];
    }

    private function squared(array $a, array $b): float
    {
        return array_sum(array_map(fn ($d) => ($a[$d] - $b[$d]) ** 2, array_keys($a)));
    }
}
