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
}