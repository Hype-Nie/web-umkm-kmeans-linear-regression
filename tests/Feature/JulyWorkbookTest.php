<?php

namespace Tests\Feature;

use App\Models\TransaksiPenjualan;
use App\Services\ImportService;
use App\Services\ReportService;
use App\Services\SpreadsheetService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class JulyWorkbookTest extends DatabaseTestCase
{
    private function importWorkbook(): \App\Models\User
    {
        Storage::fake('local');
        $user = $this->owner('juli');
        $this->actingAs($user);
        $path = resource_path('data_transaksi_uji_coba_juli_2026.xlsx');
        $data = app(SpreadsheetService::class)->read($path, 'xlsx');
        $mapping = array_combine($data['headers'], $data['headers']);
        $preview = app(ImportService::class)->preview($user, $data, $mapping, 'transaksi');
        $this->assertCount(113, $preview['valid']);
        $this->assertSame([], $preview['errors']);
        $this->post('/impor', ['file' => new UploadedFile($path, basename($path), null, null, true), 'jenis_data' => 'transaksi'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $history = $user->impor()->firstOrFail();
        $this->get('/impor/'.$history->id_impor.'/preview')->assertOk();
        $this->post('/impor/'.$history->id_impor.'/confirm', ['mapping' => $mapping, 'confirmed' => 1])
            ->assertRedirect('/impor')->assertSessionHasNoErrors();
        $this->assertSame('berhasil', $history->fresh()->status);
        $this->assertSame(113, (int) $history->fresh()->baris_berhasil);
        $this->assertSame(11, $user->barang()->count());
        $sales = TransaksiPenjualan::whereIn('id_barang', $user->barang()->select('id_barang'));
        $this->assertSame(113, (clone $sales)->count());
        $this->assertSame(402, (int) (clone $sales)->sum('jumlah_terjual'));
        $this->assertSame('9725500.00', (string) (clone $sales)->sum('total_pendapatan'));
        $this->assertSame(0, $user->barang()->whereHas('stok')->count());
        return $user;
    }

    public function test_real_workbook_import_analysis_exports_and_duplicate_protection(): void
    {
        $user = $this->importWorkbook();
        $range = '?mulai=2026-07-01&akhir=2026-07-31';
        $this->get('/dashboard'.$range)->assertOk()->assertSee('9.725.500');
        $this->get('/transaksi'.$range)->assertOk();
        $this->post('/analisis', ['mulai' => '2026-07-01', 'akhir' => '2026-07-31'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $process = $user->proses()->latest('id_proses')->firstOrFail();
        $this->assertSame('berhasil', $process->status, $process->catatan);
        $this->assertSame(11, $process->klaster()->count());
        $this->assertSame(3, $process->klaster()->distinct()->count('label_klaster'));
        $this->assertSame(0, $process->prediksi()->count());
        $this->assertSame(0, $process->rekomendasi()->count());
        $this->get('/analisis?proses='.$process->id_proses)->assertOk();
        $this->post('/rekomendasi', ['id_proses' => $process->id_proses])->assertSessionHas('warning');
        $this->get('/rekomendasi')->assertOk();
        $dataset = $this->get('/analisis/dataset?proses='.$process->id_proses)->assertOk()->streamedContent();
        $this->assertCount(114, preg_split('/\r?\n/', trim($dataset)));
        foreach (array_keys(ReportService::TYPES) as $type) {
            $this->get('/laporan'.$range.'&jenis='.$type)->assertOk();
            foreach (['csv', 'xlsx', 'pdf'] as $format) {
                $response = $this->get('/laporan/export'.$range.'&jenis='.$type.'&format='.$format)->assertOk();
                $bytes = $format === 'pdf' ? $response->getContent() : $response->streamedContent();
                $this->assertStringStartsWith(match ($format) { 'pdf' => '%PDF-', 'xlsx' => 'PK', default => "\xEF\xBB\xBF" }, $bytes);
            }
        }
        $report = app(ReportService::class)->build($user, 'penjualan', '2026-07-01', '2026-07-31');
        $this->assertCount(113, $report['rows']);
        $this->assertSame(402, array_sum(array_column($report['rows'], 3)));
        $this->assertEquals(9725500, array_sum(array_column($report['rows'], 5)));
        $path = resource_path('data_transaksi_uji_coba_juli_2026.xlsx');
        $this->post('/impor', ['file' => new UploadedFile($path, 'nama-lain.xlsx', null, null, true), 'jenis_data' => 'transaksi'])
            ->assertSessionHasErrors('file');
        file_put_contents(storage_path('logs/hasil-juli.json'), json_encode([
            'rows' => 113, 'products' => 11, 'units' => 402, 'revenue' => '9725500.00',
            'silhouette' => $process->silhouette_coefficient,
            'clusters' => $process->klaster()->with('barang')->get()->map(fn ($c) => ['code' => $c->barang->kode_barang, 'label' => $c->label_klaster])->all(),
            'metadata' => json_decode($process->catatan, true),
        ], JSON_PRETTY_PRINT));
    }

    public function test_real_workbook_with_explicit_synthetic_prior_months_and_stock(): void
    {
        $user = $this->importWorkbook();
        // These two months are synthetic test fixtures, not contents of the supplied workbook.
        foreach ($user->barang()->get() as $product) {
            $july = (int) $product->transaksi()->sum('jumlah_terjual');
            foreach (['2026-05-15', '2026-06-15'] as $date) {
                $product->transaksi()->create(['tanggal_transaksi' => $date, 'jumlah_terjual' => $july, 'harga_satuan_jual' => '1.00', 'total_pendapatan' => $july.'.00', 'sumber_data' => 'manual']);
            }
            $product->stok()->create(['tanggal_update' => '2026-07-31', 'sisa_stok' => 0, 'sumber_data' => 'stok_awal']);
        }
        $this->post('/analisis', ['mulai' => '2026-05-01', 'akhir' => '2026-07-31'])->assertSessionHasNoErrors();
        $process = $user->proses()->latest('id_proses')->firstOrFail();
        $this->assertSame('berhasil', $process->status, $process->catatan);
        $this->assertSame(11, $process->prediksi()->count());
        $this->assertSame(402, (int) $process->prediksi()->sum('angka_ramalan_stok'));
        $this->assertSame(11, $process->rekomendasi()->count());
        foreach ($process->prediksi as $prediction) {
            $this->assertSame('2026-08-01', $prediction->periode_prediksi->toDateString());
        }
        $this->post('/rekomendasi', ['id_proses' => $process->id_proses])->assertSessionHasNoErrors();
        $this->assertSame(11, $process->rekomendasi()->count());
        $rec = $process->rekomendasi()->firstOrFail();
        $this->patch('/rekomendasi/'.$rec->id_rekomendasi, ['status' => 'ditinjau'])->assertSessionHasNoErrors();
        $this->patch('/rekomendasi/'.$rec->id_rekomendasi, ['status' => 'selesai'])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $rec->fresh()->status);
        $this->assertSame(0, (int) $rec->barang->stokTerakhir->sisa_stok);
        $this->get('/rekomendasi?status=selesai&jenis=restock&prioritas=Tinggi')->assertOk()->assertSee($rec->judul);
        $product = $user->barang()->firstOrFail();
        $product->update(['stok_minimum' => 2]);
        $this->post('/rekomendasi', ['id_proses' => $process->id_proses])->assertSessionHasErrors('analisis');
    }
}
