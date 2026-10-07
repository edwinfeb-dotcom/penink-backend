<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * GET /api/settings
     * Bisa diakses semua user yang sudah login
     */
    public function index()
    {
        $settings = AppSetting::all()->mapWithKeys(function ($item) {
            return [$item->key => [
                'value' => $item->value,
                'label' => $item->label,
            ]];
        });

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * PUT /api/admin/super/settings/{key}
     * Hanya super_admin
     */
    public function update(Request $request, string $key)
    {
        $request->validate([
            'value' => 'required|string|max:255',
        ]);

        $setting = AppSetting::where('key', $key)->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting tidak ditemukan.',
            ], 404);
        }

        $oldValue = $setting->value;
        $setting->value = $request->value;
        $setting->save();

        return response()->json([
            'success' => true,
            'message' => "Setting '{$setting->label}' berhasil diperbarui.",
            'data' => [
                'key' => $setting->key,
                'value' => $setting->value,
                'label' => $setting->label,
            ],
        ]);
    }
}