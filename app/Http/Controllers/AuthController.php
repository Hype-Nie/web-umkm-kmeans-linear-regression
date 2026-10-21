<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'nama_pemilik' => 'required|string|max:191', 'nama_toko' => 'required|string|max:191',
            'username' => ['required', 'alpha_dash:ascii', 'max:100', 'unique:users,username'],
            'email' => 'required|email|max:191|unique:users,email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()], 'terms' => 'accepted',
        ]);
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Akun berhasil dibuat. Mulai dengan menambahkan barang atau mengimpor data.');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['identity' => 'required|string|max:191', 'password' => 'required|string', 'remember' => 'nullable|boolean']);
        $field = filter_var($data['identity'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        if (! Auth::attempt([$field => $data['identity'], 'password' => $data['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['identity' => 'Email/username atau kata sandi tidak sesuai.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        if (config('mail.default') !== 'smtp' || ! config('mail.mailers.smtp.host') || ! config('mail.from.address')) {
            return back()->withErrors(['email' => 'SMTP belum dikonfigurasi. Atur MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD dan MAIL_FROM_ADDRESS pada .env, lalu jalankan php artisan config:clear. Email belum dikirim.']);
        }
        try {
            $status = Password::sendResetLink($data);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['email' => 'Pengiriman email gagal. Periksa koneksi dan konfigurasi SMTP.']);
        }
        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['email' => 'Tunggu sebentar sebelum meminta tautan lagi.']);
        }

        return back()->with('success', 'Jika email terdaftar, tautan reset kata sandi telah dikirim. Tautan berlaku 60 menit.');
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()]]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Kata sandi diperbarui. Silakan masuk.')
            : back()->withErrors(['email' => 'Token tidak valid atau sudah kedaluwarsa. Minta tautan reset baru.']);
    }
}
