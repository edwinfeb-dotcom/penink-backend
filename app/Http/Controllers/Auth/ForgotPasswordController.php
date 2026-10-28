<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        // 1. Validasi input email
        $request->validate(['email' => 'required|email']);

        // 2. Kirim link reset password
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // 3. Berikan respons yang sesuai
        if ($status == Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Link reset password telah dikirim ke email Anda.'], 200);
        }

        return response()->json(['message' => 'Gagal mengirim link reset password. Pastikan email terdaftar.'], 400);
    }

        public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed', // Pastikan frontend mengirim password_confirmation
        ]);

        $status = \Illuminate\Support\Facades\Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => \Illuminate\Support\Facades\Hash::make($password)
                ])->setRememberToken(\Illuminate\Support\Str::random(60));
                $user->save();
            }
        );

        if ($status == \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password berhasil direset.'], 200);
        }

        return response()->json(['message' => 'Gagal mereset password. Token tidak valid atau sudah kedaluwarsa.'], 400);
    }
}