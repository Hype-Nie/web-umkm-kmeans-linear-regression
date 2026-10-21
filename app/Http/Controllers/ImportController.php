<?php

namespace App\Http\Controllers;

use App\Models\RiwayatImpor;
use App\Services\ImportService;
use App\Services\SpreadsheetService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    public function index(Request $request)
    {
        return view('impor.index', ['history' => $request->user()->impor()->latest('id_impor')->paginate(10)]);
    }

    public function upload(Request $request, SpreadsheetService $sheets)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,csv,txt|max:10240', 'jenis_data' => 'required|in:transaksi,stok,gabungan']);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['file' => 'Hanya CSV dan XLSX yang diterima.']);
        }
        try {
            $sheets->read($file->getRealPath(), $ext);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['file' => $e instanceof DomainException ? $e->getMessage() : 'File tidak dapat dibaca. Pastikan format XLSX/CSV benar.']);
        }
        $hash = hash_file('sha256', $file->getRealPath());
        $path = 'imports/'.$request->user()->id_user.'/'.$hash.'.'.$ext;
        // Content-addressed private storage provides a stable file identity without altering SQL.
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
        $duplicate = $request->user()->impor()->where('path_file', $path)->where('jenis_data', $request->jenis_data)->whereIn('status', ['berhasil', 'parsial'])->exists();
        if ($duplicate) {
            return back()->withErrors(['file' => 'Berkas identik sudah pernah diimpor. Gunakan file yang diperbaiki; fingerprint per baris tetap mencegah duplikasi.']);
        }
        $history = $request->user()->impor()->create(['nama_file_asli' => $file->getClientOriginalName(), 'path_file' => $path, 'jenis_data' => $request->jenis_data, 'status' => 'diproses']);

        return redirect()->route('impor.preview', $history);
    }

    public function preview(Request $request, string $impor, SpreadsheetService $sheets, ImportService $service)
    {
        $request->validate(['mapping' => 'nullable|array', 'mapping.*' => 'nullable|string|max:255']);
        $history = $request->user()->impor()->findOrFail($impor);
        try {
            $file = $sheets->read(Storage::disk('local')->path($history->path_file), pathinfo($history->path_file, PATHINFO_EXTENSION));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('impor.index')->withErrors(['file' => 'Berkas unggahan tidak tersedia atau tidak dapat dibaca. Unggah ulang berkas asli.']);
        }
        $mapping = $request->input('mapping') ?: [];
        foreach (ImportService::FIELDS as $field) {
            if (! array_key_exists($field, $mapping)) {
                $mapping[$field] = '';
                foreach ($file['headers'] as $header) {
                    $normalized = strtolower(str_replace(' ', '_', trim($header)));
                    if ((ImportService::ALIASES[$normalized] ?? $normalized) === $field) {
                        $mapping[$field] = $header;
                        break;
                    }
                }
            }
        }

        return view('impor.preview', ['history' => $history, 'file' => $file, 'mapping' => $mapping, 'fields' => ImportService::FIELDS, 'preview' => $service->preview($request->user(), $file, $mapping, $history->jenis_data)]);
    }

    public function confirm(Request $request, string $impor, SpreadsheetService $sheets, ImportService $service)
    {
        $request->validate(['mapping' => 'required|array', 'mapping.*' => 'nullable|string|max:255', 'partial' => 'nullable|boolean', 'confirmed' => 'accepted']);
        $history = $request->user()->impor()->findOrFail($impor);
        try {
            $file = $sheets->read(Storage::disk('local')->path($history->path_file), pathinfo($history->path_file, PATHINFO_EXTENSION));
            $result = $service->commit($request->user(), $history, $file, $request->mapping, $request->boolean('partial'));
        } catch (\Throwable $e) {
            if ($e instanceof DomainException) {
                return back()->withErrors(['impor' => $e->getMessage()]);
            }
            report($e);
            $history->update(['status' => 'gagal', 'rincian_error' => [['pesan' => 'Penyimpanan dibatalkan. Periksa koneksi database dan format file.']]]);

            return back()->withErrors(['impor' => 'Impor dibatalkan dengan aman. Periksa data lalu unggah ulang.']);
        }

        return redirect()->route('impor.index')->with($result['success'] ? 'success' : 'warning', $result['success'].' baris tersimpan; '.count($result['errors']).' baris gagal. Lihat rincian pada riwayat.');
    }

    public function template(Request $request, SpreadsheetService $sheets)
    {
        $request->validate(['jenis' => 'nullable|in:transaksi,stok,gabungan', 'format' => 'nullable|in:xlsx,csv']);
        $fields = match ($request->input('jenis', 'gabungan')) {
            'transaksi' => array_slice(ImportService::FIELDS, 0, 8),
            'stok' => ['kode_barang', 'nama_barang', 'kategori', 'tanggal_update', 'sisa_stok'],
            default => ImportService::FIELDS,
        };

        return $sheets->download($fields, [], 'template-'.$request->input('jenis', 'gabungan'), $request->input('format', 'xlsx'));
    }

    public function destroy(Request $request, string $impor)
    {
        $history = $request->user()->impor()->findOrFail($impor);

        if ($history->status === 'berhasil' || $history->status === 'parsial') {
            return back()->withErrors(['impor' => 'Berkas impor yang sudah berhasil tersimpan tidak dapat dibatalkan demi menjaga keutuhan riwayat transaksi.']);
        }

        if ($history->path_file && Storage::disk('local')->exists($history->path_file)) {
            $usedElsewhere = RiwayatImpor::where('path_file', $history->path_file)
                ->where('id_impor', '!=', $history->id_impor)
                ->exists();
            if (! $usedElsewhere) {
                Storage::disk('local')->delete($history->path_file);
            }
        }

        $history->delete();

        return redirect()->route('impor.index')->with('success', 'Unggahan berkas impor berhasil dibatalkan dan dihapus.');
    }
}
