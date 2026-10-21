<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransaksiController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));
Route::view('/ketentuan', 'auth.terms')->name('terms');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::view('/forgot-password', 'auth.forgot')->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', fn (string $token) => view('auth.reset', compact('token')))->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('barang', BarangController::class)->except('show');
    Route::post('/barang/{barang}/stok', [BarangController::class, 'stock'])->name('barang.stock');
    Route::resource('transaksi', TransaksiController::class)->except('show');
    Route::get('/impor', [ImportController::class, 'index'])->name('impor.index');
    Route::get('/impor/template', [ImportController::class, 'template'])->name('impor.template');
    Route::post('/impor', [ImportController::class, 'upload'])->middleware('throttle:10,1')->name('impor.upload');
    Route::match(['get', 'post'], '/impor/{impor}/preview', [ImportController::class, 'preview'])->name('impor.preview');
    Route::post('/impor/{impor}/confirm', [ImportController::class, 'confirm'])->name('impor.confirm');
    Route::delete('/impor/{impor}', [ImportController::class, 'destroy'])->name('impor.destroy');
    Route::get('/analisis', [AnalysisController::class, 'index'])->name('analisis.index');
    Route::post('/analisis', [AnalysisController::class, 'run'])->middleware('throttle:5,1')->name('analisis.run');
    Route::post('/analisis/rapidminer', [AnalysisController::class, 'rapidminer'])->middleware('throttle:5,1')->name('analisis.rapidminer');
    Route::get('/analisis/template', [AnalysisController::class, 'template'])->name('analisis.template');
    Route::get('/analisis/dataset', [AnalysisController::class, 'dataset'])->name('analisis.dataset');
    Route::get('/rekomendasi', [RecommendationController::class, 'index'])->name('rekomendasi.index');
    Route::post('/rekomendasi', [RecommendationController::class, 'generate'])->name('rekomendasi.generate');
    Route::patch('/rekomendasi/{rekomendasi}', [RecommendationController::class, 'update'])->name('rekomendasi.update');
    Route::get('/laporan', [ReportController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/export',[ReportController::class, 'export'])->name('laporan.export');
    Route::get('/profil', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/password', [ProfileController::class, 'password'])->name('profile.password');
});
