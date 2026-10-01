<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UnitKerja;
use App\Services\AuditLogService;
use App\Models\ShortLink;
use App\Models\ShortLinkClick;
use App\Models\LinkHub;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    // =========================
    // DAFTAR USER
    // =========================
    public function index()
    {
        $users = User::with('unitKerja')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Data user berhasil diambil.',
            'data' => $users,
        ]);
    }


    // =========================
    // DAFTAR UNIT KERJA
    // =========================
    public function unitKerjas()
    {
        $unitKerjas = UnitKerja::orderBy('id')->get();

        return response()->json([
            'message' => 'Data unit kerja berhasil diambil.',
            'data' => $unitKerjas,
        ]);
    }


    // =========================
    // TAMBAH USER
    // =========================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
            ],

            'user_type' => [
                'required',
                Rule::in([
                    'UMUM',
                    'ASN',
                ]),
            ],

            'role' => [
                'required',
                Rule::in([
                    'user',
                    'admin',
                    ]),
                    ],

            'unit_kerja_id' => [
                'nullable',
                'exists:unit_kerjas,id',
            ],
        ]);

        $user = User::create($validated);

        $user->load('unitKerja');

        return response()->json([
            'message' => 'User berhasil ditambahkan.',
            'data' => $user,
        ], 201);
    }


    // =========================
    // EDIT USER
    // =========================
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'password' => [
                'nullable',
                'string',
                'min:8',
            ],

            'user_type' => [
                'required',
                Rule::in([
                    'UMUM',
                    'ASN',
                ]),
            ],

            'role' => [
                'required',
                Rule::in([
                    'user',
                    'admin',
                    ]),
                    ],

            'unit_kerja_id' => [
                'nullable',
                'exists:unit_kerjas,id',
            ],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        $user->load('unitKerja');

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'data' => $user,
        ]);
    }


    // =========================
    // STATISTIK ADMIN
    // =========================
    public function statistics()
    {
        $totalUsers = User::count();

        $totalShortLinks = ShortLink::count();

        $totalLinkHubs = LinkHub::count();

        $activeShortLinks = ShortLink::where('status', true)->count();

        $totalClicks = (int) ShortLink::sum('click_count');

        $totalUsersASN = User::where('user_type', 'ASN')->count();

        $totalUsersUmum = User::where('user_type', 'UMUM')->count();

        $todayClicks = ShortLinkClick::whereDate(
            'clicked_at',
            Carbon::today()
        )->count();

        $monthClicks = ShortLinkClick::whereMonth(
            'clicked_at',
            Carbon::now()->month
        )
        ->whereYear(
            'clicked_at',
            Carbon::now()->year
        )
        ->count();


        // =========================
        // SHORT LINK BERDASARKAN UNIT KERJA
        // =========================
        $shortLinksByUnit = ShortLink::with('user.unitKerja')
        ->get()
        ->groupBy(function ($shortLink) {
            return $shortLink->user?->unitKerja?->id
            ?? 0;
            })
            ->map(function ($links, $unitId) {
                $unit = $links->first()->user?->unitKerja;
                
                return [
                    'unit_kerja_id' => (int) $unitId,
                    'unit_kerja' => $unit?->nama_unit_kerja
                    ?? 'Belum Memilih Unit Kerja',
                    'jumlah' => $links->count(),
                    ];
                    })
                    ->values();


        return response()->json([
            'message' => 'Statistik admin berhasil diambil.',
            'data' => [
                'total_users' => $totalUsers,
                'total_short_links' => $totalShortLinks,
                'total_link_hubs' => $totalLinkHubs,
                'active_short_links' => $activeShortLinks,
                'total_clicks' => $totalClicks,
                'today_clicks' => $todayClicks,
                'month_clicks' => $monthClicks,
                'total_users_asn' => $totalUsersASN,
                'total_users_umum' => $totalUsersUmum,

                // Statistik Short Link berdasarkan Unit Kerja
                'short_links_by_unit' => $shortLinksByUnit,
            ],
        ]);
    }


    // =========================
    // DAFTAR SHORT LINK
    // =========================
    public function shortLinks()
    {
        $shortLinks = ShortLink::with(['user.unitKerja'])
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Data short link berhasil diambil.',
            'data' => $shortLinks,
        ]);
    }


    // =========================
    // EDIT SHORT LINK OLEH ADMIN
    // =========================
    public function updateShortLink(Request $request, $id)
    {
        $shortLink = ShortLink::findOrFail($id);

        $request->validate([
            'original_url' => 'required|url',

            'short_code' =>
                'required|alpha_dash|unique:short_links,short_code,' .
                $shortLink->id .
                '|not_in:admin,login,register,api,dashboard,user,settings',

            'title' => 'nullable|string|max:255',

        ], [
            'short_code.not_in' =>
                'Alias ini tidak dapat digunakan karena termasuk alias yang dicadangkan.',

            'short_code.unique' =>
                'Alias ini sudah digunakan oleh shortlink lain.',

            'short_code.required' =>
                'Alias wajib diisi.',

            'short_code.alpha_dash' =>
                'Alias hanya boleh berisi huruf, angka, tanda hubung (-), dan garis bawah (_).',
        ]);


        $shortLink->update([
            'original_url' => $request->original_url,
            'short_code' => $request->short_code,
            'title' => $request->title,
        ]);


        return response()->json([
            'message' => 'Short link berhasil diperbarui oleh admin.',
            'data' => $shortLink,
        ]);
    }


    // =========================
    // UBAH STATUS SHORT LINK
    // =========================
   public function toggleShortLink($id)
   {
    $shortLink = ShortLink::findOrFail($id);

    $shortLink->update([
        'status' => !$shortLink->status,
    ]);

    AuditLogService::log(
        'shortlink.status_changed',
        'ShortLink',
        $shortLink->id,
        $shortLink->status
            ? 'Short Link berhasil diaktifkan'
            : 'Short Link berhasil dinonaktifkan',
        [
            'short_code' => $shortLink->short_code,
            'status' => $shortLink->status,
        ]
    );

    return response()->json([
        'message' => 'Status short link berhasil diubah.',
        'data' => $shortLink,
    ]);
    }


    // =========================
    // HAPUS SHORT LINK
    // =========================
    public function deleteShortLink($id)
    {
    $shortLink = ShortLink::findOrFail($id);

    AuditLogService::log(
        'shortlink.deleted',
        'ShortLink',
        $shortLink->id,
        'Short Link berhasil dihapus oleh admin',
        [
            'short_code' => $shortLink->short_code,
            'original_url' => $shortLink->original_url,
            'title' => $shortLink->title,
        ]
    );

    $shortLink->delete();

    return response()->json([
        'message' => 'Short link berhasil dihapus oleh admin.',
    ]);
    }

    public function destroy(Request $request, $id)
    {
    $user = \App\Models\User::find($id);

    if (!$user) {
        return response()->json([
            'message' => 'User tidak ditemukan.',
        ], 404);
    }

    // Cegah admin hapus dirinya sendiri
    if ($request->user()->id == $user->id) {
        return response()->json([
            'message' => 'Anda tidak bisa menghapus akun Anda sendiri.',
        ], 403);
    }

    $user->delete();

    return response()->json([
        'message' => 'User berhasil dihapus.',
    ]);
    }
}