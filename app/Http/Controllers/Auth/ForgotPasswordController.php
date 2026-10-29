<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class ForgotPasswordController extends Controller
{
    // Fungsi untuk mengirim link reset ke email
    public function sendResetLinkEmail(Request $request)
    {
        // 1. Validasi input email
        $request->validate(['email' => 'required|email']);

        // 2. Cek dulu apakah email ini benar-benar ada di database
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'message' => 'Gagal. Email tersebut belum terdaftar di sistem kami.'
            ], 400);
        }

        // 3. Kirim link reset via Laravel Password Broker
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status == Password::RESET_LINK_SENT) {
                return response()->json([
                    'message' => 'Link reset password berhasil dikirim ke email Anda. Silakan cek kotak masuk.'
                ], 200);
            }

            // Jika Laravel menolak (misal karena terlalu sering request)
            Log::warning('Reset Password Failed: ' . $status);
            return response()->json([
                'message' => 'Gagal mengirim link. Terlalu banyak permintaan, silakan tunggu 1 menit lagi.'
            ], 429);

        } catch (\Exception $e) {
            // Tangkap error jika SMTP/Email gagal
            Log::error('SMTP Error Reset Password: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengirim email. Periksa konfigurasi server.',
                'error_asli' => $e->getMessage()
            ], 500);
        }
    }

    // Fungsi untuk menyimpan password baru (dipanggil dari halaman React)
    public function resetPassword(Request $request)
    {
        // 1. Validasi input
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        // 2. Proses reset password
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        // 3. Cek apakah berhasil
        if ($status == Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password berhasil diubah. Silakan login dengan password baru Anda.'
            ], 200);
        }

        // Jika token salah atau kadaluarsa
        return response()->json([
            'message' => 'Gagal mereset password. Token tidak valid atau sudah kadaluarsa.'
        ], 400);
    }
}