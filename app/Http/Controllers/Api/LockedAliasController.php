<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LockedAlias;
use Illuminate\Http\Request;

class LockedAliasController extends Controller
{
    // =========================
    // LIST ALIAS TERKUNCI
    // =========================
    public function index()
    {
        $aliases = LockedAlias::with(['unitKerja', 'creator'])
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Data alias terkunci berhasil diambil.',
            'data' => $aliases,
        ]);
    }

        // =========================
    // ALIAS TERKUNCI UNTUK USER
    // (Ambil alias yang dikunci untuk unit kerja user yang login)
    // =========================
    public function myAliases(Request $request)
    {
        $user = $request->user();

        if (!$user->unit_kerja_id) {
            return response()->json([
                'message' => 'User belum memiliki unit kerja.',
                'data' => [],
            ]);
        }

        $aliases = LockedAlias::where('unit_kerja_id', $user->unit_kerja_id)
            ->orderBy('alias')
            ->get(['id', 'alias']);

        return response()->json([
            'message' => 'Alias terkunci berhasil diambil.',
            'data' => $aliases,
        ]);
    }

    // =========================
    // TAMBAH ALIAS TERKUNCI
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'alias' => 'required|string|max:255|alpha_dash|unique:locked_aliases,alias',
            'unit_kerja_id' => 'required|exists:unit_kerjas,id',
        ], [
            'alias.unique' => 'Alias ini sudah dikunci sebelumnya.',
            'alias.alpha_dash' => 'Alias hanya boleh huruf, angka, tanda hubung (-), dan garis bawah (_).',
            'alias.required' => 'Alias wajib diisi.',
            'unit_kerja_id.required' => 'Unit kerja wajib dipilih.',
            'unit_kerja_id.exists' => 'Unit kerja tidak ditemukan.',
        ]);

        $lockedAlias = LockedAlias::create([
            'alias' => strtolower(trim($request->alias)),
            'unit_kerja_id' => $request->unit_kerja_id,
            'created_by' => $request->user()->id,
        ]);

        $lockedAlias->load(['unitKerja', 'creator']);

        return response()->json([
            'message' => 'Alias berhasil dikunci.',
            'data' => $lockedAlias,
        ], 201);
    }


    // =========================
    // HAPUS ALIAS TERKUNCI
    // =========================
    public function destroy($id)
    {
        $lockedAlias = LockedAlias::find($id);

        if (!$lockedAlias) {
            return response()->json([
                'message' => 'Alias terkunci tidak ditemukan.',
            ], 404);
        }

        $alias = $lockedAlias->alias;
        $lockedAlias->delete();

        return response()->json([
            'message' => "Alias '{$alias}' berhasil dibuka kuncinya.",
        ]);
    }
}