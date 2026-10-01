<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'unit_kerja_id' => 'required|exists:unit_kerjas,id',
            'user_type' => 'required|in:ASN,UMUM',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'unit_kerja_id' => $request->unit_kerja_id,
            'user_type' => $request->user_type,
            'role' => 'user',
        ]);

        return response()->json([
            'message' => 'Registrasi berhasil. Silakan login.',
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        $user->load('unitKerja');

        $token = $user->createToken('penink-token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function googleCallback()
    {
    $googleUser = Socialite::driver('google')->user();

    $user = User::where('email', $googleUser->email)->first();

    if (!$user) {
        $user = User::create([
            'name' => $googleUser->name,
            'email' => $googleUser->email,
            'password' => Hash::make(str()->random(32)),
            'role' => 'user',
            'user_type' => 'UMUM',
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