<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Storage;
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
    // =====================================================
    // HELPER ROLE
    // =====================================================
    private function isSuperAdmin($user)
    {
        return strtolower($user->role) === 'super_admin';
    }

    private function isAdminUnit($user)
    {
        return strtolower($user->role) === 'admin_unit';
    }

    private function isGlobalAdmin($user)
    {
        return in_array(strtolower($user->role), ['admin', 'super_admin']);
    }


    // =========================
    // DAFTAR USER
    // =========================
    public function index(Request $request)
    {
        $user = $request->user();

        $query = User::with('unitKerja');

        // admin_unit → hanya user dari OPD-nya
        if ($this->isAdminUnit($user)) {
            $query->where('unit_kerja_id', $user->unit_kerja_id);
        }

        // admin & super_admin → lihat semua user

        $users = $query->latest()->get();

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
        $user = $request->user();

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

            'role' => 'required|in:user,admin,admin_unit,super_admin',

            'unit_kerja_id' => [
                'nullable',
                'exists:unit_kerjas,id',
            ],
        ]);

        // Guard 1: cuma super_admin yang bisa buat super_admin
        if (
            $request->role === 'super_admin' &&
            !$this->isSuperAdmin($user)
        ) {
            return response()->json([
                'message' => 'Hanya Super Admin yang bisa membuat Super Admin.',
            ], 403);
        }

        // Guard 2: admin_unit hanya bisa buat user di OPD-nya sendiri
        if ($this->isAdminUnit($user)) {
            // Paksa unit_kerja_id jadi OPD-nya
            $validated['unit_kerja_id'] = $user->unit_kerja_id;

            // admin_unit tidak bisa buat admin atau super_admin
            if (in_array($request->role, ['admin', 'super_admin'])) {
                return response()->json([
                    'message' => 'Admin Unit hanya bisa membuat user biasa atau admin unit.',
                ], 403);
            }
        }

        $newUser = User::create($validated);

        $newUser->load('unitKerja');

        return response()->json([
            'message' => 'User berhasil ditambahkan.',
            'data' => $newUser,
        ], 201);
    }


    // =========================
    // EDIT USER
    // =========================
    public function update(Request $request, User $user)
    {
        $requester = $request->user();

        // Guard 1: admin_unit hanya bisa edit user dari OPD-nya
        if ($this->isAdminUnit($requester)) {
            if ($user->unit_kerja_id !== $requester->unit_kerja_id) {
                return response()->json([
                    'message' => 'Anda hanya bisa mengelola user dari unit kerja Anda.',
                ], 403);
            }
        }

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
                    'admin_unit',
                    'super_admin',
                ]),
            ],

            'unit_kerja_id' => [
                'nullable',
                'exists:unit_kerjas,id',
            ],
        ]);

        // Guard 2: cuma super_admin yang bisa ubah role jadi super_admin
        if (
            $request->role === 'super_admin' &&
            !$this->isSuperAdmin($requester)
        ) {
            return response()->json([
                'message' => 'Hanya Super Admin yang bisa mengubah role menjadi Super Admin.',
            ], 403);
        }

        // Guard 3: cuma super_admin yang bisa ubah akun super_admin
        if (
            strtolower($user->role) === 'super_admin' &&
            !$this->isSuperAdmin($requester)
        ) {
            return response()->json([
                'message' => 'Hanya Super Admin yang bisa mengubah akun Super Admin.',
            ], 403);
        }

        // Guard 4: admin_unit tidak bisa ubah role user atau pindah OPD
        if ($this->isAdminUnit($requester)) {
            // Paksa unit kerja tetap di OPD-nya
            $validated['unit_kerja_id'] = $requester->unit_kerja_id;

            // Tidak boleh naikkan role user ke admin/super_admin
            if (in_array($request->role, ['admin', 'super_admin'])) {
                return response()->json([
                    'message' => 'Admin Unit hanya bisa mengatur role user atau admin unit.',
                ], 403);
            }
        }

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
    public function statistics(Request $request)
    {
        $user = $request->user();

        $userQuery = User::query();
        $shortLinkQuery = ShortLink::query();
        $shortLinkClickQuery = ShortLinkClick::query();
        $linkHubQuery = LinkHub::query();

        // admin_unit → scope ke OPD-nya
        if ($this->isAdminUnit($user)) {
            $userQuery->where('unit_kerja_id', $user->unit_kerja_id);

            $shortLinkQuery->whereHas('user', function ($q) use ($user) {
                $q->where('unit_kerja_id', $user->unit_kerja_id);
            });

            $shortLinkClickQuery->whereHas('shortLink.user', function ($q) use ($user) {
                $q->where('unit_kerja_id', $user->unit_kerja_id);
            });

            $linkHubQuery->whereHas('user', function ($q) use ($user) {
                $q->where('unit_kerja_id', $user->unit_kerja_id);
            });
        }

        $totalUsers = $userQuery->count();

        $totalShortLinks = $shortLinkQuery->count();

        $totalLinkHubs = $linkHubQuery->count();

        $activeShortLinks = (clone $shortLinkQuery)->where('status', true)->count();

        $totalClicks = (int) $shortLinkQuery->sum('click_count');

        $totalUsersASN = (clone $userQuery)->where('user_type', 'ASN')->count();

        $totalUsersUmum = (clone $userQuery)->where('user_type', 'UMUM')->count();

        $todayClicks = $shortLinkClickQuery
            ->whereDate('clicked_at', Carbon::today())
            ->count();

        $monthClicks = (clone $shortLinkClickQuery)
            ->whereMonth('clicked_at', Carbon::now()->month)
            ->whereYear('clicked_at', Carbon::now()->year)
            ->count();


        // =========================
        // SHORT LINK BERDASARKAN UNIT KERJA
        // =========================
        $shortLinksByUnitQuery = ShortLink::with('user.unitKerja');

        if ($this->isAdminUnit($user)) {
            $shortLinksByUnitQuery->whereHas('user', function ($q) use ($user) {
                $q->where('unit_kerja_id', $user->unit_kerja_id);
            });
        }

        $shortLinksByUnit = $shortLinksByUnitQuery
            ->get()
            ->groupBy(function ($shortLink) {
                return $shortLink->user?->unitKerja?->id ?? 0;
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
    public function shortLinks(Request $request)
    {
        $user = $request->user();

        $query = ShortLink::with(['user.unitKerja', 'lockedUnitKerja']);

        // admin_unit → hanya shortlink dari user di OPD-nya
        if ($this->isAdminUnit($user)) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('unit_kerja_id', $user->unit_kerja_id);
            });
        }

        $shortLinks = $query->latest()->get();

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
        $requester = $request->user();

        $shortLink = ShortLink::with('user')->findOrFail($id);

        // Guard: admin_unit hanya bisa edit shortlink dari OPD-nya
        if ($this->isAdminUnit($requester)) {
            if ($shortLink->user?->unit_kerja_id !== $requester->unit_kerja_id) {
                return response()->json([
                    'message' => 'Anda hanya bisa mengelola short link dari unit kerja Anda.',
                ], 403);
            }
        }

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
    public function toggleShortLink(Request $request, $id)
    {
        $requester = $request->user();

        $shortLink = ShortLink::with('user')->findOrFail($id);

        // Guard: admin_unit hanya bisa toggle shortlink dari OPD-nya
        if ($this->isAdminUnit($requester)) {
            if ($shortLink->user?->unit_kerja_id !== $requester->unit_kerja_id) {
                return response()->json([
                    'message' => 'Anda hanya bisa mengelola short link dari unit kerja Anda.',
                ], 403);
            }
        }

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
    public function deleteShortLink(Request $request, $id)
    {
        $requester = $request->user();

        $shortLink = ShortLink::with('user')->findOrFail($id);

        // Guard: admin_unit hanya bisa hapus shortlink dari OPD-nya
        if ($this->isAdminUnit($requester)) {
            if ($shortLink->user?->unit_kerja_id !== $requester->unit_kerja_id) {
                return response()->json([
                    'message' => 'Anda hanya bisa mengelola short link dari unit kerja Anda.',
                ], 403);
            }
        }

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


    // =========================
    // HAPUS USER
    // =========================
       public function destroy(Request $request, $id)
    {
        $requester = $request->user();

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan.',
            ], 404);
        }

        // Cegah admin hapus dirinya sendiri
        if ($requester->id == $user->id) {
            return response()->json([
                'message' => 'Anda tidak bisa menghapus akun Anda sendiri.',
            ], 403);
        }

        // Guard: admin_unit hanya bisa hapus user dari OPD-nya
        if ($this->isAdminUnit($requester)) {
            if ($user->unit_kerja_id !== $requester->unit_kerja_id) {
                return response()->json([
                    'message' => 'Anda hanya bisa menghapus user dari unit kerja Anda.',
                ], 403);
            }
        }

        // Cegah admin biasa hapus super_admin
        if (
            strtolower($user->role) === 'super_admin' &&
            !$this->isSuperAdmin($requester)
        ) {
            return response()->json([
                'message' => 'Hanya Super Admin yang bisa menghapus akun Super Admin.',
            ], 403);
        }

        // =====================================================
        // CASCADE DELETE — Hapus semua data milik user ini
        // =====================================================

        // 1. Hapus semua CLICKS dari short link milik user
        \App\Models\ShortLinkClick::whereIn(
            'short_link_id',
            \App\Models\ShortLink::where('user_id', $user->id)->pluck('id')
        )->delete();

        // 2. Hapus semua SHORT LINK milik user
        $shortLinkCount = \App\Models\ShortLink::where('user_id', $user->id)->count();
        \App\Models\ShortLink::where('user_id', $user->id)->delete();

        // 3. ✨ Hapus FILE LOGO dari semua LinkHub milik user
        $userLinkHubs = \App\Models\LinkHub::where('user_id', $user->id)->get();
        foreach ($userLinkHubs as $hub) {
            if ($hub->logo_path && \Storage::disk('public')->exists($hub->logo_path)) {
                \Storage::disk('public')->delete($hub->logo_path);
            }
        }

        // 4. Hapus semua ITEMS dari link hub milik user
        \App\Models\LinkHubItem::whereIn(
            'link_hub_id',
            \App\Models\LinkHub::where('user_id', $user->id)->pluck('id')
        )->delete();

        // 5. Hapus semua LINK HUB milik user
        $linkHubCount = \App\Models\LinkHub::where('user_id', $user->id)->count();
        \App\Models\LinkHub::where('user_id', $user->id)->delete();

        // 6. Hapus semua FEEDBACK milik user
        \App\Models\Feedback::where('user_id', $user->id)->delete();

        // 7. Hapus semua TOKEN (personal access token)
        $user->tokens()->delete();

        // 8. Terakhir, hapus USER-nya
        $user->delete();

        return response()->json([
            'message' => 'User berhasil dihapus.',
            'deleted' => [
                'user' => $user->name,
                'email' => $user->email,
                'short_links_deleted' => $shortLinkCount,
                'link_hubs_deleted' => $linkHubCount,
            ],
        ]);
    }
}