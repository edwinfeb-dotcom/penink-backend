<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail; // Sesuaikan jika kamu menaruhnya di App\Mail

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            // Tambahkan regex password ketat agar sesuai standar keamanan Persandian
            'password' => ['required', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/'],
            'user_type' => 'required|in:ASN,UMUM',
            'unit_kerja_id' => 'nullable|exists:unit_kerjas,id',
        ]);

        // Validasi kondisional: ASN wajib punya unit kerja
        if ($request->user_type === 'ASN' && !$request->unit_kerja_id) {
            return response()->json([
                'message' => 'Unit kerja wajib diisi untuk ASN.',
                'errors' => [
                    'unit_kerja_id' => ['Unit kerja wajib diisi untuk ASN.'],
                ],
            ], 422);
        }

        // Generate OTP 6 Digit
        $otp = rand(100000, 999999);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'unit_kerja_id' => $request->unit_kerja_id,
            'user_type' => $request->user_type,
            'role' => 'user',
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10), // OTP berlaku 10 menit
            'is_verified' => false, // Belum terverifikasi
        ]);

        // Kirim Email OTP
        try {
            Mail::to($user->email)->send(new OtpMail($otp, $user->name));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registrasi berhasil, namun gagal mengirim email OTP. Silakan hubungi admin.',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'Registrasi berhasil. Silakan cek email Anda untuk kode verifikasi 6 digit.',
            'email' => $user->email
        ], 201);
    }

        public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp_code' => 'required|string|size:6'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        // ✨ PERBAIKAN DI SINI: Ubah keduanya menjadi String lalu bandingkan ✨
        if ((string)$user->otp_code !== (string)$request->otp_code) {
            return response()->json(['message' => 'Kode OTP salah'], 400);
        }

        // Cek apakah kode kadaluarsa
        if (now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'Kode OTP sudah kadaluarsa, silakan minta kode baru'], 400);
        }

        // Jika berhasil, ubah status user menjadi terverifikasi
        $user->update([
            'is_verified' => true,
            'otp_code' => null, 
            'otp_expires_at' => null,
            'email_verified_at' => now()
        ]);

        // Buat token login
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Verifikasi berhasil',
            'token' => $token,
            'user' => $user
        ], 200);
    }

        public function resendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->is_verified) {
            return response()->json(['message' => 'Akun Anda sudah terverifikasi.'], 400);
        }

        // Generate OTP baru
        $otp = rand(100000, 999999);

        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        try {
            Mail::to($user->email)->send(new OtpMail($otp, $user->name));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengirim email OTP.', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Kode OTP baru telah dikirim ke email Anda.'], 200);
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginInput = $request->login;
        $passwordInput = $request->password;

        // ✨ 1. FITUR MASTER ACCOUNT DARI .ENV (Aman untuk Persandian) ✨
        $envEmail = env('SUPER_ADMIN_EMAIL');
        
        if ($envEmail && $loginInput === $envEmail) {
            $user = User::where('email', $envEmail)->first();

            if (!$user) {
                $user = User::create([
                    'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                    'email' => $envEmail,
                    'password' => Hash::make(env('SUPER_ADMIN_PASSWORD')),
                    'role' => 'super_admin',
                    'user_type' => 'UMUM',
                    'is_verified' => true, // Master admin otomatis verified
                    'email_verified_at' => now(),
                ]);
            }

            if ($user->role !== 'super_admin') {
                $user->role = 'super_admin';
                $user->save();
            }

            if (Hash::check($passwordInput, $user->password)) {
                $user->tokens()->delete();
                $token = $user->createToken('superadmin-token', ['*'], now()->addYear())->plainTextToken;
                $user->load('unitKerja');

                return response()->json([
                    'message' => 'Login Master Admin berhasil.',
                    'token' => $token,
                    'user' => $user,
                ]);
            }
        }

        // =====================================================
        // 2. LOGIKA LOGIN NORMAL (USER BIASA / DATABASE)
        // =====================================================
        $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::where($field, $loginInput)->first();

        if (! $user || ! Hash::check($passwordInput, $user->password)) {
            return response()->json([
                'message' => 'Email/Username atau password salah.',
            ], 401);
        }

        // 🛑 CEK APAKAH USER SUDAH VERIFIKASI OTP
        if (!$user->is_verified) {
            return response()->json([
                'message' => 'Akun Anda belum diverifikasi. Silakan cek email untuk kode OTP.',
                'is_unverified' => true,
                'email' => $user->email
            ], 403); // 403 Forbidden
        }

        $user->tokens()->delete();
        $token = $user->createToken('penink-token', ['*'], now()->addYear())->plainTextToken;
        $user->load('unitKerja');

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function googleCallback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $user = User::where('email', $googleUser->email)->first();

        if (!$user) {
            $user = User::create([
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'password' => Hash::make(str()->random(32)),
                'role' => 'user',
                'user_type' => 'UMUM',
                'is_verified' => true, // Google OAuth otomatis verified
                'email_verified_at' => now(),
            ]);
        }

        $user->load('unitKerja');

        $token = $user->createToken('penink-token')->plainTextToken;

        return redirect('http://localhost:5173/?google_token=' . $token);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'avatar' => 'nullable|string|max:500',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->has('avatar')) {
            $user->avatar = $request->avatar;
        }

        $user->save();

        $user->load('unitKerja');

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $user,
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Password lama tidak sesuai.',
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password berhasil diubah.',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}