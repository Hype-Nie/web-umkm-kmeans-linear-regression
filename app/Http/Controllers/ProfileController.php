<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $stats = [
            'total_barang' => $user->barang()->count(),
            'total_transaksi' => \App\Models\TransaksiPenjualan::whereIn('id_barang', $user->barang()->select('id_barang'))->count(),
            'total_proses' => $user->proses()->count(),
            'proses_terakhir' => $user->proses()->where('status', 'berhasil')->latest('created_at')->first(),
        ];

        return view('profile.index', compact('user', 'stats'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'nama_pemilik' => ['required', 'string', 'max:191'],
            'nama_toko' => ['required', 'string', 'max:191'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users')->ignore($user->id_user, 'id_user')],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users')->ignore($user->id_user, 'id_user')],
        ]);

        $user->update($validated);

        return back()->with('success', 'Informasi profil dan identitas usaha berhasil disimpan.');
    }

    public function password(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'Kata sandi saat ini tidak cocok dengan data akun Anda.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak sesuai.',
            'password.min' => 'Kata sandi baru minimal terdiri dari 8 karakter.',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Kata sandi akun Anda berhasil diperbarui.');
    }
}
