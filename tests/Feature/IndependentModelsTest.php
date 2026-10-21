<?php

namespace Tests\Feature;

use App\Services\AnalysisService;

class IndependentModelsTest extends DatabaseTestCase
{
    public function test_regression_is_available_even_when_products_do_not_support_three_clusters(): void
    {
        $user = $this->owner();
        $this->history($this->product($user), [10, 20, 30]);
        $process = app(AnalysisService::class)->run($user, '2025-01-01', '2025-03-31', false);
        $this->assertSame('berhasil', $process->status, $process->catatan);
        $this->assertSame(0, $process->klaster()->count());
        $this->assertSame(1, $process->prediksi()->count());
        $this->assertSame(40, (int) $process->prediksi()->first()->angka_ramalan_stok);
        $metadata = json_decode($process->catatan, true);
        $this->assertSame('data_tidak_cukup', $metadata['status_kmeans']);
        $this->actingAs($user)->get('/analisis?proses='.$process->id_proses)->assertOk()->assertSee('40.0000')->assertSee('K-Means belum tersedia');
    }
}
