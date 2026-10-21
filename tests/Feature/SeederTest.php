<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AnalysisService;
use Database\Seeders\TestingDataSeeder;
use Illuminate\Support\Facades\Hash;

class SeederTest extends DatabaseTestCase
{
    public function test_testing_seeder_is_complete_idempotent_and_keeps_other_owners_untouched(): void
    {
        $other = $this->owner();
        $product = $this->product($other);
        $this->seed();
        $user = User::where('email', TestingDataSeeder::EMAIL)->firstOrFail();
        $this->assertTrue(Hash::check(TestingDataSeeder::PASSWORD, $user->password));
        $this->assertSame(9, $user->barang()->count());
        $process = $user->proses()->latest('id_proses')->firstOrFail();
        $this->assertSame('backend_php', $process->sumber_proses);
        $this->assertSame(9, $process->klaster()->count());
        $this->assertSame(9, $process->prediksi()->count());
        $this->assertGreaterThan(0, $process->rekomendasi()->count());
        $this->assertSame('data_uji_seeder', json_decode($process->catatan, true)['dataset']);
        $signature = app(AnalysisService::class)->signature($user);
        $this->seed();
        $this->assertSame($signature, app(AnalysisService::class)->signature($user));
        $this->assertSame(1, $user->proses()->count());
        $this->assertSame(1, $other->barang()->count());
        $this->assertSame($product->nama_barang, $product->fresh()->nama_barang);
    }

    public function test_testing_seeder_refuses_production(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->expectException(\RuntimeException::class);
            app(TestingDataSeeder::class)->run();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
