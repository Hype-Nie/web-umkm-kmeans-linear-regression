<?php

namespace Tests\Unit;

use App\Services\KMeansService;
use App\Services\RegressionService;
use App\Support\Money;
use DomainException;
use PHPUnit\Framework\TestCase;

class AlgorithmsTest extends TestCase
{
    public function test_money_is_exact_and_does_not_trust_float_multiplication(): void
    {
        $this->assertSame('0.30', Money::total('0.10', 3));
        $this->assertSame('29999999999999.97', Money::total('9999999999999.99', 3));
        $this->assertSame('Rp 1.234.567,89', Money::format('1234567.89'));
    }

    public function test_negative_or_ambiguous_money_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::normalize('1.234,50');
    }

    public function test_regression_fits_line_and_validates_only_on_future_points(): void
    {
        $result = (new RegressionService)->forecast([10, 20, 30, 40, 50]);
        $this->assertEqualsWithDelta(0, $result['a'], 1e-9);
        $this->assertEqualsWithDelta(10, $result['b'], 1e-9);
        $this->assertSame(60, $result['units']);
        $this->assertEquals(0, $result['mse']);
        $this->assertEquals(0, $result['mae']);
        $this->assertEquals(1, $result['r_squared']);
        $this->assertSame(3, $result['validation_points']);
    }

    public function test_negative_forecast_preserves_raw_and_clamps_units(): void
    {
        $result = (new RegressionService)->forecast([30, 15, 0]);
        $this->assertEquals(-15, $result['raw']);
        $this->assertSame(0, $result['units']);
        $this->assertNull($result['r_squared']);
    }

    public function test_insufficient_regression_does_not_invent_forecast(): void
    {
        $this->expectException(DomainException::class);
        (new RegressionService)->forecast([2, 3]);
    }

    public function test_kmeans_labels_follow_centroids_and_silhouette_is_correct(): void
    {
        $result = (new KMeansService)->calculate([11 => [1000, 100], 12 => [1000, 100], 21 => [10, 1], 22 => [10, 1], 31 => [500, 50], 32 => [500, 50]]);
        $this->assertCount(3, $result['centroids']);
        $this->assertSame('Sangat Laris', $result['labels'][$result['assignments'][11]]);
        $this->assertSame('Kurang', $result['labels'][$result['assignments'][21]]);
        $this->assertSame('Cukup', $result['labels'][$result['assignments'][31]]);
        $this->assertEqualsWithDelta(1, $result['silhouette'], 1e-9);
        $this->assertLessThanOrEqual(300, $result['iterations']);
    }

    public function test_three_singleton_clusters_do_not_claim_valid_silhouette(): void
    {
        $this->assertNull((new KMeansService)->calculate([[1, 1], [2, 2], [3, 3]])['silhouette']);
    }

    public function test_identical_features_cannot_create_fake_clusters(): void
    {
        $this->expectException(DomainException::class);
        (new KMeansService)->calculate([[1, 1], [1, 1], [2, 2], [2, 2]]);
    }
}
