<?php

namespace Tests\Feature;

use App\Services\AnalysisService;
use App\Services\ClusterDiagnosticsService;
use PhpOffice\PhpSpreadsheet\IOFactory;

class WireframeRevisionTest extends DatabaseTestCase
{
    public function test_diagnostics_use_actual_standardized_distances(): void
    {
        $result = app(ClusterDiagnosticsService::class)->calculate([[0, 0], [0, 0], [10, 10], [10, 10]]);
        $this->assertEquals(8, $result['points'][0]['sse']);
        $this->assertNull($result['points'][0]['silhouette']);
        $this->assertEquals(0, $result['points'][1]['sse']);
        $this->assertEquals(1, $result['points'][1]['silhouette']);
        $this->assertSame([], app(ClusterDiagnosticsService::class)->calculate([[1, 1]])['points']);
    }

    public function test_analysis_category_filter_and_low_priority_list_are_independent(): void
    {
        $user = $this->owner('wireframe');
        foreach ([1, 2, 3, 10, 12, 15, 80, 100, 120] as $i => $quantity) {
            $product = $this->product($user, 'UI'.$i);
            $product->update(['kategori' => $i < 3 ? 'Minuman' : 'Sembako']);
            $this->history($product, [$quantity, 2 * $quantity, 3 * $quantity]);
            $product->stok()->create(['tanggal_update' => '2025-03-31', 'sisa_stok' => 0, 'sumber_data' => 'stok_awal']);
        }
        $process = app(AnalysisService::class)->run($user, '2025-01-01', '2025-03-31', false);
        $this->assertSame('berhasil', $process->status);
        $this->actingAs($user);
        $response = $this->get('/analisis?proses='.$process->id_proses)->assertOk()
            ->assertSee('AI Strategic Insights')->assertSee('Elbow Method')->assertSee('Silhouette Analysis')->assertSee('Metrik Perputaran Stok');
        $points = $response->viewData('diagnostics')['points'];
        $this->assertCount(8, $points);
        foreach (array_slice($points, 1) as $i => $point) {
            $this->assertLessThanOrEqual($points[$i]['sse'] + .00001, $point['sse']);
            $this->assertGreaterThanOrEqual(-1, $point['silhouette']);
            $this->assertLessThanOrEqual(1, $point['silhouette']);
        }
        $filtered = $this->get('/analisis?proses='.$process->id_proses.'&kategori=Minuman')->assertOk();
        $this->assertSame(['Minuman'], $filtered->viewData('categoryMetrics')->keys()->all());
        // Use existing real recommendations as fixtures for the compact low-priority presentation.
        $process->rekomendasi()->orderBy('id_rekomendasi')->limit(3)->update(['prioritas' => 'Rendah']);
        $response = $this->get('/rekomendasi')->assertOk()->assertSee('Rekomendasi Lainnya');
        $this->assertSame(3, $response->viewData('otherItems')->total());
        $this->assertSame(6, $response->viewData('items')->total());
        $response = $this->get('/rekomendasi?prioritas=Rendah')->assertOk();
        $this->assertSame(3, $response->viewData('otherItems')->total());
        $this->assertSame(0, $response->viewData('items')->total());
        $user->barang()->first()->update(['stok_minimum' => 10]);
        $response = $this->get('/analisis?proses='.$process->id_proses)->assertOk();
        $this->assertSame([], $response->viewData('diagnostics')['points']);
    }

    public function test_report_options_control_preview_csv_and_excel_with_scoped_raw_data(): void
    {
        $user = $this->owner('reportui');
        $product = $this->product($user, 'UI-REPORT');
        $this->history($product, [10, 20, 30]);
        $other = $this->owner('secret');
        $this->history($this->product($other, 'SECRET-RAW'), [100, 200, 300]);
        $this->actingAs($user);
        $range = '?mulai=2025-01-01&akhir=2025-03-31';
        $response = $this->get('/laporan'.$range)->assertOk()->assertSee('Unduh Draft')->assertDontSee('Perbarui Preview');
        $response = $this->get('/laporan'.$range.'&summary=0&detail=0&raw=1')->assertOk()
            ->assertSee('Lampiran Data Mentah')->assertDontSee('Detail Data Laporan')->assertDontSee('I. Ringkasan Eksekutif')->assertDontSee('SECRET-RAW');
        $this->assertCount(3, $response->viewData('report')['raw_rows']);
        $csv = $this->get('/laporan/export'.$range.'&summary=0&detail=0&raw=1&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Lampiran Data Mentah', $csv);
        $this->assertStringNotContainsString('Total / catatan', $csv);
        $this->assertStringNotContainsString('SECRET-RAW', $csv);
        foreach ([['summary' => 0, 'detail' => 0, 'raw' => 1], ['summary' => 1, 'detail' => 1, 'raw' => 0]] as $options) {
            $bytes = $this->get('/laporan/export'.$range.'&format=xlsx&'.http_build_query($options))->assertOk()->streamedContent();
            $path = tempnam(sys_get_temp_dir(), 'ui-report-');
            file_put_contents($path, $bytes);
            try {
                $book = IOFactory::load($path);
                if ($options['raw']) {
                    $this->assertSame(['Data Mentah'], $book->getSheetNames());
                    $this->assertSame(4, $book->getSheet(0)->getHighestRow());
                } else {
                    $this->assertSame(['Ringkasan', 'Data'], $book->getSheetNames());
                    $zip = new \ZipArchive;
                    $zip->open($path);
                    $this->assertStringContainsString('2563EB', $zip->getFromName('xl/charts/chart1.xml'));
                    $zip->close();
                }
                $this->assertSame('FF2563EB', $book->getSheet(0)->getStyle('A1')->getFill()->getStartColor()->getARGB());
                $book->disconnectWorksheets();
            } finally {
                unlink($path);
            }
        }
        $pdf = $this->get('/laporan/export'.$range.'&format=pdf&summary=0&detail=0&raw=1')->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->get('/laporan'.$range.'&raw=invalid')->assertSessionHasErrors('raw');
    }
}
