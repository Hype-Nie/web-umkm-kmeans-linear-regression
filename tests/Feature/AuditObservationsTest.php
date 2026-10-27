<?php

namespace Tests\Feature;

use App\Services\AnalysisService;
use App\Services\KMeansService;
use App\Services\RapidMinerService;
use App\Services\ReportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Characterization tests: passing confirms the documented observation, not its desirability. */
class AuditObservationsTest extends DatabaseTestCase
{
    public function test_recommendations_without_r_squared_currently_use_id_based_confidence(): void
    {
        $user = $this->owner();
        foreach ([1, 10, 100] as $i => $quantity) {
            $product = $this->product($user, 'C'.$i);
            $this->history($product, [$quantity, $quantity * 2, $quantity * 3]);
            $product->stok()->create(['tanggal_update' => '2025-03-31', 'sisa_stok' => 0, 'sumber_data' => 'stok_awal']);
        }
        $process = app(AnalysisService::class)->run($user, '2025-01-01', '2025-03-31', false);
        $rec = $process->rekomendasi()->firstOrFail();
        $expected = number_format(88 + ($rec->id_rekomendasi % 7) * 1.2, 1).'%';
        $metadata = json_decode($process->catatan, true);
        $this->assertNull($metadata['evaluasi_per_produk'][$rec->barang->kode_barang]['r_squared']);
        $this->actingAs($user)->get('/rekomendasi')->assertOk()->assertSee($expected);
    }

    public function test_analysis_report_currently_falls_back_outside_selected_period(): void
    {
        $user = $this->owner();
        foreach ([1, 10, 100] as $i => $quantity) {
            $this->history($this->product($user, 'F'.$i), [$quantity, $quantity * 2, $quantity * 3]);
        }
        $process = app(AnalysisService::class)->run($user, '2025-01-01', '2025-03-31', false);
        $this->assertSame('berhasil', $process->status);
        $report = app(ReportService::class)->build($user, 'kmeans', '2024-01-01', '2024-01-31');
        $this->assertCount(3, $report['rows']);
        $this->assertSame('2024-01-01', $report['start']);
        $this->actingAs($user)->get('/laporan?jenis=kmeans&mulai=2024-01-01&akhir=2024-01-31')->assertOk()->assertSee('Produk F0');
    }

    public function test_twenty_product_fixture_currently_fails_on_empty_fixed_centroid_cluster(): void
    {
        $user = $this->owner();
        foreach (range(1, 20) as $i) {
            $this->history($this->product($user, 'K'.$i), [$i, 2 * $i, 3 * $i]);
        }
        $process = app(AnalysisService::class)->run($user, '2025-01-01', '2025-03-31', false);
        $this->assertSame('gagal', $process->status);
        $this->assertSame(0, $process->klaster()->count());
        $this->assertSame(0, $process->prediksi()->count());
        $features = [];
        foreach (range(1, 20) as $i) {
            $features[$i] = [$i, $i * 2, $i * 100];
        }
        $this->expectException(\DivisionByZeroError::class);
        app(KMeansService::class)->calculate($features);
    }

    public function test_rapidminer_http_upload_and_all_template_variants(): void
    {
        Storage::fake('local');
        $user = $this->owner();
        $this->actingAs($user);
        $csv = implode(',', RapidMinerService::HEADERS)."\n";
        foreach ([[1, 100, 10], [2, 1, 1], [3, 50, 5]] as [$cluster, $units, $freq]) {
            $product = $this->product($user, 'RM'.$cluster);
            $csv .= implode(',', [$product->kode_barang, '2025-01-01', '2025-03-31', $cluster, $units, $freq, $units, $freq, 0, '2025-04-01', $units + 5, '', '', '', '', ''])."\n";
        }
        $this->post('/analisis/rapidminer', ['file' => UploadedFile::fake()->createWithContent('hasil.csv', $csv), 'confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $process = $user->proses()->firstOrFail();
        $this->assertSame('berhasil', $process->status, $process->catatan);
        $this->assertSame('rapidminer', $process->sumber_proses);
        $this->assertSame(3, $process->prediksi()->count());
        Storage::disk('local')->assertExists(json_decode($process->catatan, true)['path_bukti']);
        $this->get('/analisis?proses='.$process->id_proses)->assertOk();
        foreach (['csv', 'xlsx'] as $format) {
            foreach (['stok', 'transaksi', 'gabungan'] as $type) {
                $response = $this->get('/impor/template?jenis='.$type.'&format='.$format)->assertOk();
                $this->assertStringStartsWith($format === 'xlsx' ? 'PK' : "\xEF\xBB\xBF", $response->streamedContent());
            }
            $response = $this->get('/analisis/template?format='.$format)->assertOk();
            $this->assertStringStartsWith($format === 'xlsx' ? 'PK' : "\xEF\xBB\xBF", $response->streamedContent());
        }
    }
}
