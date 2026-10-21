<?php

namespace Tests\Feature;

use App\Services\AnalysisService;
use App\Services\ImportService;
use App\Services\RapidMinerService;
use App\Services\ReportService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class IntegrityTest extends DatabaseTestCase
{
    public function test_csrf_protection_is_active_outside_testing_environment(): void
    {
        $this->app->instance('env', 'local');
        try {
            $this->post('/login', ['identity' => 'unknown', 'password' => 'anything'])->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['identity' => 'throttle_test', 'password' => 'wrong'])->assertSessionHasErrors('identity');
        }
        $this->post('/login', ['identity' => 'throttle_test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_other_accounts_cannot_access_import_preview_or_recommendation(): void
    {
        $a = $this->owner();
        $b = $this->owner('b');
        $p = $this->product($b);
        $import = $b->impor()->create(['nama_file_asli' => 'private.csv', 'jenis_data' => 'stok', 'path_file' => 'private.csv']);
        $process = $b->proses()->create(['status' => 'berhasil']);
        $rec = $process->rekomendasi()->create(['id_barang' => $p->id_barang, 'tanggal_rekomendasi' => now(), 'jenis_rekomendasi' => 'restock', 'prioritas' => 'Tinggi', 'judul' => 'Secret', 'deskripsi' => 'Secret']);
        $this->actingAs($a);
        $this->get('/impor/'.$import->id_impor.'/preview')->assertNotFound();
        $this->post('/impor/'.$import->id_impor.'/confirm', ['mapping' => ['kode_barang' => 'kode'], 'confirmed' => 1])->assertNotFound();
        $this->patch('/rekomendasi/'.$rec->id_rekomendasi, ['status' => 'selesai'])->assertNotFound();
        $this->post('/barang/'.$p->id_barang.'/stok', ['jenis' => 'penyesuaian', 'jumlah' => 0, 'tanggal_update' => now()->format('Y-m-d H:i:s')])->assertNotFound();
    }

    public function test_new_product_import_is_scoped_to_owner_and_validated_before_creating(): void
    {
        $a = $this->owner();
        $b = $this->owner('b');
        $foreign = $this->product($b, 'NEW');
        $headers = ['kode_barang', 'nama_barang', 'kategori', 'tanggal_transaksi', 'jumlah_terjual', 'harga_satuan_jual'];
        $file = ['headers' => $headers, 'rows' => [['NEW', 'Produk Milik A', 'Uji', '2025-01-01', 2, '10.50'], ['BAD', '', '', '2025-01-01', 2, '10.50']]];
        $import = $a->impor()->create(['nama_file_asli' => 'new.csv', 'jenis_data' => 'transaksi']);
        $result = app(ImportService::class)->commit($a, $import, $file, array_combine($headers, $headers), true);
        $this->assertSame(1, $result['success']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('Produk Milik A', $a->barang()->where('kode_barang', 'NEW')->firstOrFail()->nama_barang);
        $this->assertSame('Produk NEW', $foreign->fresh()->nama_barang);
        $this->assertSame(0, $a->barang()->where('kode_barang', 'BAD')->count());
    }

    public function test_mapped_xlsx_numeric_dates_can_be_uploaded_and_imported(): void
    {
        $a = $this->owner();
        $p = $this->product($a);
        $this->actingAs($a);
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['ProductCode', 'DateOfSale', 'Units', 'Price'], ['P1', Date::PHPToExcel(new \DateTime('2025-01-15 10:30:00')), 3, 10.25]]);
        $path = tempnam(sys_get_temp_dir(), 'spk-upload-');
        (new Xlsx($book))->save($path);
        try {
            $bytes = file_get_contents($path);
        } finally {
            unlink($path);
            $book->disconnectWorksheets();
        }
        $this->post('/impor', ['jenis_data' => 'transaksi', 'file' => UploadedFile::fake()->createWithContent('real.xlsx', $bytes)])->assertRedirect();
        $h = $a->impor()->firstOrFail();
        $mapping = ['kode_barang' => 'ProductCode', 'tanggal_transaksi' => 'DateOfSale', 'jumlah_terjual' => 'Units', 'harga_satuan_jual' => 'Price'];
        $this->post('/impor/'.$h->id_impor.'/preview', ['mapping' => $mapping])->assertOk()->assertSee('30.75');
        $this->post('/impor/'.$h->id_impor.'/confirm', ['mapping' => $mapping, 'confirmed' => 1])->assertRedirect('/impor');
        $t = $p->transaksi()->firstOrFail();
        $this->assertSame('2025-01-15 10:30:00', $t->tanggal_transaksi->format('Y-m-d H:i:s'));
        $this->assertSame('30.75', $t->total_pendapatan);
    }

    public function test_duplicate_rows_inside_file_and_future_dates_are_detected(): void
    {
        $a = $this->owner();
        $this->product($a);
        $headers = ['kode_barang', 'tanggal_transaksi', 'jumlah_terjual', 'harga_satuan_jual'];
        $row = ['P1', '2025-01-01', 3, '10.25'];
        $file = ['headers' => $headers, 'rows' => [$row, $row, ['P1', now()->addDay()->toDateString(), 1, '10.25'], ['', '', '', '']]];
        $preview = app(ImportService::class)->preview($a, $file, array_combine($headers, $headers), 'transaksi');
        $this->assertCount(1, $preview['valid']);
        $this->assertCount(2, $preview['errors']);
    }

    public function test_stock_mutations_reject_missing_initial_stock_or_backdated_operational_write(): void
    {
        $a = $this->owner();
        $p = $this->product($a);
        $this->actingAs($a);
        $this->post('/barang/'.$p->id_barang.'/stok', ['jenis' => 'restock', 'jumlah' => 5, 'tanggal_update' => '2025-01-01'])->assertSessionHasErrors('stok');
        $p->stok()->create(['tanggal_update' => '2025-02-01', 'sisa_stok' => 5, 'sumber_data' => 'stok_awal']);
        $this->post('/barang/'.$p->id_barang.'/stok', ['jenis' => 'stok_awal', 'jumlah' => 5, 'tanggal_update' => '2025-03-01'])->assertSessionHasErrors('stok');
        $this->post('/barang/'.$p->id_barang.'/stok', ['jenis' => 'restock', 'jumlah' => 5, 'tanggal_update' => '2025-01-01'])->assertSessionHasErrors('tanggal_update');
        $this->assertSame(5, (int) $p->fresh()->stokTerakhir->sisa_stok);
    }

    public function test_linked_operational_transactions_are_protected_from_edit_delete(): void
    {
        $a = $this->owner();
        $p = $this->product($a);
        $this->actingAs($a);
        $p->stok()->create(['tanggal_update' => '2025-01-01', 'sisa_stok' => 10, 'sumber_data' => 'stok_awal']);
        $data = ['id_barang' => $p->id_barang, 'tanggal_transaksi' => '2025-02-01', 'jumlah_terjual' => 2, 'harga_satuan_jual' => '1.00', 'operasional' => 1];
        $this->post('/transaksi', $data)->assertSessionHasNoErrors();
        $t = $p->transaksi()->firstOrFail();
        $this->put('/transaksi/'.$t->id_transaksi, $data)->assertSessionHasErrors('transaksi');
        $this->delete('/transaksi/'.$t->id_transaksi)->assertSessionHasErrors('transaksi');
        $this->assertSame(1, $p->transaksi()->count());
        $this->assertSame(8, (int) $p->fresh()->stokTerakhir->sisa_stok);
    }

    public function test_partial_calendar_months_are_excluded_from_regression(): void
    {
        $a = $this->owner();
        foreach ([[10, 20, 30, 40, 50], [20, 40, 60, 80, 100], [100, 200, 300, 400, 500]] as $i => $quantity) {
            $this->history($this->product($a, 'P'.$i), $quantity);
        }
        $process = app(AnalysisService::class)->run($a, '2025-01-15', '2025-05-15', false);
        $this->assertSame('berhasil', $process->status, $process->catatan);
        $metadata = json_decode($process->catatan, true);
        $this->assertSame(['2025-02', '2025-03', '2025-04'], $metadata['evaluasi_per_produk']['P0']['months']);
        $this->assertSame('2025-05-01', $process->prediksi()->first()->periode_prediksi->toDateString());
    }

    public function test_data_changes_with_same_totals_still_invalidate_analysis(): void
    {
        $a = $this->owner();
        foreach ([[10, 20, 30], [20, 40, 60], [100, 200, 300]] as $i => $quantity) {
            $this->history($this->product($a, 'P'.$i), $quantity);
        }
        $service = app(AnalysisService::class);
        $process = $service->run($a, '2025-01-01', '2025-03-31', false);
        $metadata = json_decode($process->catatan, true);
        $p = $a->barang()->first();
        $p->transaksi()->first()->update(['tanggal_transaksi' => '2025-01-16 12:00:00']);
        $this->assertNotSame($metadata['data_signature'], $service->signature($a));
        $this->actingAs($a)->post('/rekomendasi', ['id_proses' => $process->id_proses])->assertSessionHasErrors('analisis');
        $this->get('/analisis?proses='.$process->id_proses)->assertOk()->assertSee('Data barang, transaksi, atau stok berubah');
    }

    public function test_inconsistent_rapidminer_centroids_roll_back_all_results(): void
    {
        $a = $this->owner();
        $rows = [];
        foreach ([[1, 10, 1], [1, 20, 2], [2, 30, 3], [3, 40, 4]] as $i => [$cluster,$qty,$freq]) {
            $p = $this->product($a, 'R'.$i);
            $rows[] = [$p->kode_barang, '2025-01-01', '2025-03-31', $cluster, $qty, $freq, $qty, $freq, '', '', '', '', '', '', '', ''];
        }
        $process = app(RapidMinerService::class)->import($a, ['headers' => RapidMinerService::HEADERS, 'rows' => $rows], 'invalid.csv');
        $this->assertSame('gagal', $process->status);
        $this->assertSame(0, $process->klaster()->count());
        $this->assertSame(0, $process->prediksi()->count());
    }

    public function test_daily_weekly_monthly_dashboard_and_pagination_filters_render(): void
    {
        $a = $this->owner();
        $p = $this->product($a);
        $this->history($p, [3, 4, 5]);
        $this->actingAs($a);
        foreach (['hari', 'minggu', 'bulan'] as $interval) {
            $this->get('/dashboard?mulai=2025-01-01&akhir=2025-03-31&interval='.$interval)->assertOk()->assertSee('1.201,20')->assertSee('sales-chart');
        }
        $this->get('/barang?sort=harga_satuan&direction=desc&kategori=Kategori%20Uji')->assertOk();
        $this->get('/transaksi?q=P1&mulai=2025-01-01&akhir=2025-03-31')->assertOk();
    }

    public function test_stock_report_uses_latest_snapshot_at_cutoff_date(): void
    {
        $a = $this->owner();
        $p = $this->product($a);
        $this->actingAs($a);
        foreach ([['2025-01-01', 10], ['2025-02-01', 7], ['2025-03-01', 30]] as [$date,$qty]) {
            $p->stok()->create(['tanggal_update' => $date, 'sisa_stok' => $qty, 'sumber_data' => 'penyesuaian']);
        }
        $report = app(ReportService::class)->build($a, 'stok', '2025-01-01', '2025-02-15');
        $this->assertSame(7, (int) $report['rows'][0][4]);
    }

    public function test_exported_text_cannot_execute_spreadsheet_formula(): void
    {
        $a = $this->owner();
        $p = $this->product($a);
        $p->update(['nama_barang' => '=HYPERLINK("https://example.test","unsafe")']);
        $this->history($p,[2, 3, 4]);
        $this->actingAs($a);
        $response = $this->get('/laporan/export?mulai=2025-01-01&akhir=2025-03-31&format=csv')->assertOk();
        $this->assertStringContainsString("'=HYPERLINK",$response->streamedContent());
    }

    public function test_recommendation_pagination_preserves_global_executive_summary_counts(): void
    {
        $u = $this->owner();
        $process = $u->proses()->create([
            'status' => 'berhasil',
            'tanggal_mulai_data' => '2025-01-01',
            'tanggal_akhir_data' => '2025-03-31',
            'sumber_proses' => 'backend_php',
        ]);

        for ($i = 1; $i <= 15; $i++) {
            $p = $this->product($u, 'PROD'.$i);
            $isRestock = $i <= 12;
            $process->rekomendasi()->create([
                'id_barang' => $p->id_barang,
                'tanggal_rekomendasi' => '2025-04-01',
                'jenis_rekomendasi' => $isRestock ? 'restock' : 'promosi',
                'prioritas' => 'Tinggi',
                'judul' => 'Rekomendasi '.$i,
                'deskripsi' => 'Deskripsi '.$i,
                'jumlah_restock_disarankan' => $isRestock ? 10 : 0,
                'stok_saat_analisis' => 5,
                'status' => 'baru',
            ]);
        }

        $this->actingAs($u);

        // Page 1 should see +120 Unit
        $this->get('/rekomendasi?page=1')
            ->assertOk()
            ->assertSee('+120 Unit')
            ->assertSee('15 Produk');

        // Page 2 (which only contains the 3 promosi items with 0 restock units)
        // MUST STILL display the global +120 Unit summary in the Executive Summary Card!
        $this->get('/rekomendasi?page=2')
            ->assertOk()
            ->assertSee('+120 Unit')
            ->assertSee('15 Produk');
    }
}
