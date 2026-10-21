<?php

namespace Tests\Feature;

use App\Models\TransaksiPenjualan;
use App\Models\User;
use App\Services\AnalysisService;
use App\Services\ImportService;
use App\Services\RapidMinerService;
use App\Services\RecommendationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ApplicationTest extends DatabaseTestCase
{
    public function test_guest_pages_and_authentication_redirects(): void
    {
        foreach (['/login', '/register', '/forgot-password', '/ketentuan'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['/dashboard', '/barang', '/impor', '/transaksi', '/analisis', '/laporan', '/rekomendasi'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->get('/')->assertRedirect('/login');
    }

    public function test_registration_hashes_password_and_uses_custom_key(): void
    {
        $this->post('/register', ['nama_pemilik' => 'Ani', 'nama_toko' => 'Toko Ani', 'username' => 'ani', 'email' => 'ani@example.test', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'terms' => '1'])->assertRedirect('/dashboard');
        $user = User::where('username', 'ani')->firstOrFail();
        $this->assertNotNull($user->id_user);
        $this->assertTrue(Hash::check('Password123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_duplicate_and_confirmation_validation(): void
    {
        $user = $this->owner();
        $this->post('/register', ['nama_pemilik' => 'Ani', 'nama_toko' => 'Ani', 'username' => $user->username, 'email' => $user->email, 'password' => 'Password123', 'password_confirmation' => 'wrong'])->assertSessionHasErrors(['username', 'email', 'password', 'terms']);
    }

    public function test_login_accepts_username_and_email_logout_invalidates_session(): void
    {
        $user = $this->owner();
        $this->post('/login', ['identity' => $user->username, 'password' => 'wrong'])->assertSessionHasErrors('identity');
        $this->post('/login', ['identity' => $user->username, 'password' => 'Rahasia123', 'remember' => 1])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['identity' => $user->email, 'password' => 'Rahasia123'])->assertRedirect('/dashboard');
    }

    public function test_password_reset_token_really_resets_password_and_no_smtp_is_honest(): void
    {
        $user = $this->owner();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasErrors('email');
        $token = Password::createToken($user);
        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))->assertOk();
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'NewPassword456', 'password_confirmation' => 'NewPassword456'])->assertSessionHasErrors('email');
    }

    public function test_all_authenticated_empty_pages_render_without_dummy_data(): void
    {
        $this->actingAs($this->owner());
        foreach (['/dashboard', '/barang', '/barang/create', '/transaksi', '/transaksi/create', '/impor', '/analisis', '/rekomendasi', '/laporan'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/dashboard')->assertSee('Belum ada penjualan pada periode ini')->assertDontSee('452.800.000');
    }

    public function test_barang_crud_uniqueness_and_history_protection(): void
    {
        $user = $this->owner();
        $this->actingAs($user);
        $data = ['kode_barang' => 'NEW', 'nama_barang' => 'Produk Baru', 'kategori' => 'Uji', 'harga_satuan' => '10.25', 'harga_beli' => '8.00', 'stok_minimum' => 2];
        $this->post('/barang', $data)->assertRedirect();
        $p = $user->barang()->firstOrFail();
        $this->put('/barang/'.$p->id_barang, $data + [])->assertSessionHasNoErrors();
        $this->post('/barang', $data)->assertSessionHasErrors('kode_barang');
        $this->get('/barang/'.$p->id_barang.'/edit')->assertOk();
        $this->delete('/barang/'.$p->id_barang)->assertSessionHasNoErrors();
        $this->assertNull($p->fresh());
        $p = $this->product($user);
        $p->stok()->create(['tanggal_update' => '2025-01-01', 'sisa_stok' => 10, 'sumber_data' => 'stok_awal']);
        $this->delete('/barang/'.$p->id_barang)->assertSessionHasErrors('barang');
        $this->assertNotNull($p->fresh());
    }

    public function test_stock_latest_snapshot_uses_date_then_id_instead_of_sum(): void
    {
        $u = $this->owner();
        $p = $this->product($u);
        $this->actingAs($u);
        foreach ([['2025-01-02 10:00:00', 40], ['2025-01-01 10:00:00', 100], ['2025-01-02 10:00:00', 3]] as [$date,$qty]) {
            $p->stok()->create(['tanggal_update' => $date, 'sisa_stok' => $qty, 'sumber_data' => 'penyesuaian']);
        }
        $this->assertSame(3, (int) $p->fresh()->stokTerakhir->sisa_stok);
        $this->get('/barang?kritis=1')->assertOk()->assertSee('Produk P1');
        $this->get('/dashboard')->assertOk()->assertSee('3 Unit');
        $this->post('/barang/'.$p->id_barang.'/stok', ['jenis' => 'restock', 'jumlah' => 7, 'tanggal_update' => now()->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();
        $this->assertSame(10, (int) $p->fresh()->stokTerakhir->sisa_stok);
    }

    public function test_manual_sale_decrements_atomically_and_rejects_negative_stock(): void
    {
        $u = $this->owner();
        $p = $this->product($u);
        $this->actingAs($u);
        $p->stok()->create(['tanggal_update' => '2025-01-01', 'sisa_stok' => 5, 'sumber_data' => 'stok_awal']);
        $data = ['id_barang' => $p->id_barang, 'tanggal_transaksi' => now()->format('Y-m-d H:i:s'), 'jumlah_terjual' => 3, 'harga_satuan_jual' => '0.10', 'operasional' => 1, 'total_pendapatan' => '999'];
        $this->post('/transaksi', $data)->assertRedirect('/transaksi');
        $this->assertSame(2, (int) $p->fresh()->stokTerakhir->sisa_stok);
        $this->assertSame('0.30', $p->transaksi()->first()->total_pendapatan);
        $this->post('/transaksi', $data)->assertSessionHasErrors('stok');
        $this->assertSame(1, $p->transaksi()->count());
        $this->assertSame(2, (int) $p->fresh()->stokTerakhir->sisa_stok);
    }

    public function test_historical_transactions_crud_does_not_decrement_snapshot(): void
    {
        $u = $this->owner();
        $p = $this->product($u);
        $this->actingAs($u);
        $p->stok()->create(['tanggal_update' => '2025-01-01', 'sisa_stok' => 20, 'sumber_data' => 'impor']);
        $data = ['id_barang' => $p->id_barang, 'tanggal_transaksi' => '2025-01-15 10:00:00', 'jumlah_terjual' => 2, 'harga_satuan_jual' => '10.25'];
        $this->post('/transaksi', $data)->assertRedirect('/transaksi');
        $t = $p->transaksi()->firstOrFail();
        $this->get('/transaksi/'.$t->id_transaksi.'/edit')->assertOk();
        $data['jumlah_terjual'] = 4;
        $this->put('/transaksi/'.$t->id_transaksi, $data)->assertRedirect('/transaksi');
        $this->assertSame('41.00', $t->fresh()->total_pendapatan);
        $this->assertSame(20, (int) $p->fresh()->stokTerakhir->sisa_stok);
        $this->delete('/transaksi/'.$t->id_transaksi)->assertSessionHasNoErrors();
    }

    public function test_owner_isolation_blocks_foreign_product_transactions_analysis_and_recommendations(): void
    {
        $u = $this->owner();
        $other = $this->owner('b');
        $p = $this->product($other, 'SECRET');
        $this->history($p, [3, 4, 5]);
        $this->actingAs($u);
        $this->get('/barang/'.$p->id_barang.'/edit')->assertNotFound();
        $this->delete('/barang/'.$p->id_barang)->assertNotFound();
        $t = $p->transaksi()->first();
        $this->get('/transaksi/'.$t->id_transaksi.'/edit')->assertNotFound();
        $this->post('/transaksi', ['id_barang' => $p->id_barang, 'tanggal_transaksi' => '2025-01-01', 'jumlah_terjual' => 1, 'harga_satuan_jual' => '1'])->assertNotFound();
        $process = $other->proses()->create(['status' => 'berhasil']);
        $this->get('/analisis?proses='.$process->id_proses)->assertNotFound();
        $this->post('/rekomendasi', ['id_proses' => $process->id_proses])->assertNotFound();
        $this->get('/dashboard?mulai=2025-01-01&akhir=2025-12-31')->assertDontSee('SECRET');
        $this->get('/laporan?mulai=2025-01-01&akhir=2025-12-31')->assertDontSee('SECRET');
    }

    public function test_csv_upload_mapping_preview_confirm_and_duplicate_rejection(): void
    {
        Storage::fake('local');
        $u = $this->owner();
        $p = $this->product($u);
        $this->actingAs($u);
        $csv = "transaction_id,tanggal_transaksi,kode_barang,jumlah_terjual,harga_satuan_jual,amount_value\nT-01,2025-01-15,P1,3,0.10,0.30\n";
        $file = UploadedFile::fake()->createWithContent('riwayat.csv', $csv);
        $this->post('/impor', ['file' => $file, 'jenis_data' => 'transaksi'])->assertRedirect();
        $h = $u->impor()->firstOrFail();
        $this->get('/impor/'.$h->id_impor.'/preview')->assertOk()->assertSee('0.30');
        $mapping = ['kode_transaksi' => 'transaction_id', 'tanggal_transaksi' => 'tanggal_transaksi', 'kode_barang' => 'kode_barang', 'jumlah_terjual' => 'jumlah_terjual', 'harga_satuan_jual' => 'harga_satuan_jual', 'total_pendapatan' => 'amount_value'];
        $this->post('/impor/'.$h->id_impor.'/confirm', ['mapping' => $mapping, 'confirmed' => 1])->assertRedirect('/impor');
        $this->assertSame(1, $p->transaksi()->count());
        $this->assertSame('berhasil', $h->fresh()->status);
        $this->post('/impor', ['file' => UploadedFile::fake()->createWithContent('renamed.csv', $csv), 'jenis_data' => 'transaksi'])->assertSessionHasErrors('file');
        $this->post('/impor/'.$h->id_impor.'/confirm', ['mapping' => $mapping, 'confirmed' => 1])->assertSessionHasErrors('impor');
        $this->assertSame(1, $p->transaksi()->count());
    }

    public function test_user_can_cancel_pending_import_and_file_is_removed(): void
    {
        $u = $this->owner();
        $this->actingAs($u);
        $file = UploadedFile::fake()->createWithContent('cancel_me.csv', "kode_barang,tanggal_transaksi,jumlah_terjual,harga_satuan_jual\nP1,2025-01-15,1,10.00\n");
        $this->post('/impor', ['file' => $file, 'jenis_data' => 'transaksi'])->assertRedirect();
        $h = $u->impor()->where('status', 'diproses')->firstOrFail();
        $this->delete('/impor/'.$h->id_impor)->assertRedirect('/impor')->assertSessionHas('success');
        $this->assertDatabaseMissing('riwayat_impor', ['id_impor' => $h->id_impor]);
    }

    public function test_completed_import_cannot_be_deleted(): void
    {
        $u = $this->owner();
        $this->actingAs($u);
        $h = $u->impor()->create(['nama_file_asli' => 'done.csv', 'status' => 'berhasil', 'jenis_data' => 'transaksi']);
        $this->delete('/impor/'.$h->id_impor)->assertSessionHasErrors('impor');
        $this->assertDatabaseHas('riwayat_impor', ['id_impor' => $h->id_impor]);
    }

    public function test_import_partial_mode_records_errors_and_stock_is_not_decremented(): void
    {
        $u = $this->owner();
        $p = $this->product($u);
        $service = app(ImportService::class);
        $file = ['headers' => ImportService::FIELDS, 'rows' => [['A', '2025-01-15', 'P1', '', '', 3, '0.10', '0.30', '2025-01-15', 20], ['B', '2025-01-16', 'P1', '', '', -1, '0.10', '0.10', '', ''], ['C', '2025-01-16', 'P1', '', '', 3, '0.10', '99.00', '', '']]];
        $mapping = array_combine(ImportService::FIELDS, ImportService::FIELDS);
        $h = $u->impor()->create(['nama_file_asli' => 'test.csv', 'jenis_data' => 'gabungan']);
        $result = $service->commit($u, $h, $file, $mapping, true);
        $this->assertSame(1, $result['success']);
        $this->assertCount(2, $result['errors']);
        $this->assertSame('parsial', $h->fresh()->status);
        $this->assertSame(20, (int) $p->fresh()->stokTerakhir->sisa_stok);
        $h = $u->impor()->create(['nama_file_asli' => 'again.csv', 'jenis_data' => 'gabungan']);
        $result = $service->commit($u, $h, $file, $mapping, true);
        $this->assertSame(0, $result['success']);
        $this->assertSame(1, $p->transaksi()->count());
    }

    public function test_strict_import_saves_nothing_if_one_row_is_invalid(): void
    {
        $u = $this->owner();
        $this->product($u);
        $service = app(ImportService::class);
        $headers = ['kode_barang', 'tanggal_transaksi', 'jumlah_terjual', 'harga_satuan_jual'];
        $file = ['headers' => $headers, 'rows' => [['P1', '2025-01-01', 2, '10.00'], ['P1', '31/02/2025', 2, '10.00']]];
        $h = $u->impor()->create(['nama_file_asli' => 'test.csv', 'jenis_data' => 'transaksi']);
        $result = $service->commit($u, $h, $file, array_combine($headers, $headers), false);
        $this->assertSame(0, $result['success']);
        $this->assertSame(0, TransaksiPenjualan::count());
        $this->assertSame('gagal', $h->fresh()->status);
    }

    public function test_analysis_persists_real_results_forecasts_and_recommendations_idempotently(): void
    {
        $u = $this->owner();
        foreach ([[10, 20, 30], [100, 200, 300], [1, 2, 3]] as $i => $qty) {
            $p = $this->product($u, 'P'.$i);
            $this->history($p, $qty);
            $p->stok()->create(['tanggal_update' => '2025-04-01', 'sisa_stok' => $i === 1 ? 0 : 50, 'sumber_data' => 'stok_awal']);
        }
        $process = app(AnalysisService::class)->run($u, '2025-01-01', '2025-03-31', false);
        $this->assertSame('berhasil', $process->status, $process->catatan);
        $this->assertCount(3, $process->klaster);
        $this->assertCount(3, $process->prediksi);
        $this->assertSame('backend_php', $process->sumber_proses);
        $forecast = $process->prediksi->firstWhere('angka_ramalan_stok', 400);
        $this->assertNotNull($forecast);
        $this->assertSame('2025-04-01', $forecast->periode_prediksi->toDateString());
        $service = app(RecommendationService::class);
        $count = $service->generate($process);
        $this->assertGreaterThan(0, $count);
        $this->assertSame($count, $service->generate($process));
        $restock = $process->rekomendasi()->where('jenis_rekomendasi', 'restock')->firstOrFail();
        $this->assertSame(405, (int) $restock->jumlah_restock_disarankan);
        $this->assertSame('Tinggi', $restock->prioritas);
        $this->actingAs($u);
        $this->get('/analisis?proses='.$process->id_proses)
            ->assertOk()
            ->assertSee('400.0000')
            ->assertSee('kop-surat')
            ->assertSee('LAPORAN HASIL ANALISIS SPK')
            ->assertSee('signature-table');
        $this->get('/rekomendasi')->assertOk()->assertSee($restock->judul)->assertSee('+405 Unit');
        $this->patch('/rekomendasi/'.$restock->id_rekomendasi, ['status' => 'selesai'])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $restock->fresh()->status);
    }

    public function test_missing_months_require_explicit_zero_confirmation(): void
    {
        $u = $this->owner();
        foreach ([1, 10, 100] as $i => $quantity) {
            $p = $this->product($u, 'P'.$i);
            $this->history($p, [$quantity, $quantity]);
        }
        $service = app(AnalysisService::class);
        $process = $service->run($u, '2025-01-01', '2025-03-31', false);
        $this->assertSame('berhasil', $process->status);
        $this->assertSame(0, $process->prediksi()->count());
        $process = $service->run($u, '2025-01-01', '2025-03-31', true);
        $this->assertSame(3, $process->prediksi()->count());
    }

    public function test_analysis_with_insufficient_features_records_failed_process_without_fake_output(): void
    {
        $u = $this->owner();
        $this->history($this->product($u), [10, 20]);
        $process = app(AnalysisService::class)->run($u, '2025-01-01', '2025-03-31', false);
        $this->assertSame('gagal', $process->status);
        $this->assertSame(0, $process->klaster()->count());
        $this->assertStringContainsString('K=3', $process->catatan);
    }

    public function test_rapidminer_preserves_source_and_labels_by_centroid(): void
    {
        $u = $this->owner();
        $rows = [];
        foreach ([[1, 100, 10], [2, 1, 1], [3, 50, 5]] as [$cluster,$units,$freq]) {
            $p = $this->product($u, 'R'.$cluster);
            $rows[] = [$p->kode_barang, '2025-01-01', '2025-03-31', $cluster, $units, $freq, $units, $freq, 0, '2025-04-01', $units + 5, '', '', '', '', ''];
        }
        $process = app(RapidMinerService::class)->import($u, ['headers' => RapidMinerService::HEADERS, 'rows' => $rows], 'rapidminer.csv');
        $this->assertSame('berhasil', $process->status, $process->catatan);
        $this->assertSame('rapidminer', $process->sumber_proses);
        $this->assertSame('Sangat Laris', $process->klaster()->where('nomor_klaster', 1)->firstOrFail()->label_klaster);
        $this->assertNull($process->mse);
        $this->assertSame(3, $process->prediksi()->count());
        $this->actingAs($u)->get('/analisis?proses='.$process->id_proses)->assertOk();
    }

    public function test_pdf_xlsx_csv_reports_and_templates_are_real_downloads(): void
    {
        $u = $this->owner();
        $p = $this->product($u);
        $this->history($p, [10, 20, 30]);
        $this->actingAs($u);
        $query = '?mulai=2025-01-01&akhir=2025-03-31';
        $this->get('/laporan'.$query)->assertOk()->assertSee('6.006,00');
        $pdf = $this->get('/laporan/export'.$query.'&format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $xlsx = $this->get('/laporan/export'.$query.'&format=xlsx')->assertOk();
        $bytes = $xlsx->streamedContent();
        $this->assertStringStartsWith('PK', $bytes);
        $path = tempnam(sys_get_temp_dir(), 'spk-xlsx-');
        file_put_contents($path, $bytes);
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setIncludeCharts(true);
            $book = $reader->load($path);
            $this->assertStringContainsString('Produk P1', json_encode($book->getSheetByName('Data')->toArray()));
            $this->assertCount(1, $book->getSheetByName('Ringkasan')->getChartCollection());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $csv = $this->get('/laporan/export'.$query.'&format=csv')->assertOk();
        $this->assertStringContainsString('Produk P1', $csv->streamedContent());
        foreach (['/impor/template?jenis=stok', '/impor/template?jenis=transaksi', '/analisis/template'] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertStringStartsWith('PK', $response->streamedContent());
        }
        foreach (['stok', 'kmeans', 'prediksi', 'rekomendasi'] as $type) {
            $this->get('/laporan'.$query.'&jenis='.$type)->assertOk();
        }
    }

    public function test_invalid_files_and_date_filters_are_rejected(): void
    {
        $this->actingAs($this->owner());
        $this->get('/dashboard?mulai=2025-05-01&akhir=2025-01-01')->assertSessionHasErrors('akhir');
        $this->post('/impor', ['file' => UploadedFile::fake()->create('payload.php', 1, 'text/plain'), 'jenis_data' => 'transaksi'])->assertSessionHasErrors('file');
        $this->post('/analisis', ['mulai' => '2025-01-01', 'akhir' => '2024-12-31'])->assertSessionHasErrors('akhir');
    }
}
