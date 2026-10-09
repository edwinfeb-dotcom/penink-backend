<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\ContentModerationService;
use App\Models\LinkHub;
use App\Models\ShortLink;
use App\Models\LockedAlias;
use Illuminate\Http\Request;

class LinkHubController extends Controller
{
    /**
     * Helper: cek apakah user adalah admin / super admin
     */
    private function isAdmin($user)
    {
        return in_array(
            strtolower($user->role),
            ['admin', 'super_admin', 'admin_unit']
        );
    }

    /**
     * Helper: cek apakah user adalah admin_unit
     */
    private function isAdminUnit($user)
    {
        return strtolower($user->role) === 'admin_unit';
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if ($this->isAdmin($user)) {
            $query = LinkHub::with(['items', 'lockedUnitKerja', 'user.unitKerja']);

            // admin_unit → scope ke OPD-nya
            if ($this->isAdminUnit($user)) {
                $query->whereHas('user', function ($q) use ($user) {
                    $q->where('unit_kerja_id', $user->unit_kerja_id);
                });
            }
            // admin & super_admin → lihat semua

            $linkHubs = $query->latest()->get();
        } else {
            $linkHubs = LinkHub::with(['items', 'lockedUnitKerja'])
                ->where('user_id', $user->id)
                ->latest()
                ->get();
        }

        return response()->json([
            'message' => 'Data Link Hub berhasil diambil.',
            'data' => $linkHubs,
        ]);
    }

    public function store(Request $request)
    {
        // =====================================================
        // PAKSA SHORT_CODE JADI LOWERCASE
        // =====================================================
        if ($request->filled('short_code')) {
            $request->merge([
                'short_code' => strtolower($request->short_code),
            ]);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',

            'type' => 'required|in:ASN,UMUM',

            'short_code' => [
                'required',
                'alpha_dash',
                'unique:link_hubs,short_code',

                function ($attribute, $value, $fail) {
                    $value = strtolower($value);

                    $reservedAliases = [
                        'admin',
                        'login',
                        'register',
                        'api',
                        'dashboard',
                        'user',
                        'settings',
                    ];

                    if (in_array($value, $reservedAliases, true)) {
                        $fail(
                            'Alias ini tidak dapat digunakan karena termasuk alias yang dicadangkan.'
                        );
                        return;
                    }

                    if (ShortLink::where('short_code', $value)->exists()) {
                        $fail(
                            'Alias tersebut sudah digunakan oleh Short Link. Silakan pilih alias lain.'
                        );
                        return;
                    }

                    $locked = LockedAlias::where('alias', $value)->first();
                    if ($locked) {
                        $userUnitKerjaId = auth()->user()->unit_kerja_id;
                        $isAdmin = in_array(
                            strtolower(auth()->user()->role),
                            ['admin', 'super_admin', 'admin_unit']
                        );

                        if (!$isAdmin && $locked->unit_kerja_id != $userUnitKerjaId) {
                            $namaOpd = $locked->unitKerja?->nama_unit_kerja
                                ?? 'OPD lain';

                            $fail(
                                "Alias '{$value}' dikunci untuk {$namaOpd}. Silakan pakai alias lain."
                            );
                        }
                    }
                },
            ],

            'locked_unit_kerja_id' => 'nullable|exists:unit_kerjas,id',
        ], [
            'title.required' => 'Judul Link Hub wajib diisi.',
            'short_code.required' => 'Alias Link Hub wajib diisi.',
            'short_code.alpha_dash' => 'Alias hanya boleh berisi huruf, angka, tanda hubung (-), dan garis bawah (_).',
            'short_code.unique' => 'Alias Link Hub sudah digunakan.',
            'type.required' => 'Jenis Link Hub wajib dipilih.',
            'type.in' => 'Jenis Link Hub harus ASN atau UMUM.',

            'locked_unit_kerja_id.exists' => 'Unit kerja yang dipilih tidak valid.',
        ]);

        $linkHub = LinkHub::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'short_code' => $request->short_code,
            'type' => $request->type,
            'status' => true,
            'locked_unit_kerja_id' => $request->locked_unit_kerja_id,
        ]);

        $linkHub->load('lockedUnitKerja');

        AuditLogService::log(
            'linkhub.created',
            'LinkHub',
            $linkHub->id,
            'Link Hub berhasil dibuat',
            [
                'title' => $linkHub->title,
                'short_code' => $linkHub->short_code,
                'type' => $linkHub->type,
                'locked_unit_kerja_id' => $linkHub->locked_unit_kerja_id,
            ]
        );

        return response()->json([
            'message' => 'Link Hub berhasil dibuat.',
            'data' => $linkHub,
        ], 201);
    }

    /**
     * Helper: ambil LinkHub dengan guard admin_unit
     */
    private function findLinkHub(Request $request, $id)
    {
        $user = $request->user();

        if ($this->isAdminUnit($user)) {
            $linkHub = LinkHub::with('user')
                ->where('id', $id)
                ->firstOrFail();

            if ($linkHub->user?->unit_kerja_id !== $user->unit_kerja_id) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Anda hanya bisa mengelola Link Hub dari unit kerja Anda.',
                ], 403));
            }

            return $linkHub;
        }

        if ($this->isAdmin($user)) {
            return LinkHub::where('id', $id)->firstOrFail();
        }

        return LinkHub::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    public function addItem(Request $request, $id)
    {
        $linkHub = $this->findLinkHub($request, $id);

        $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|url',
        ], [
            'title.required' => 'Judul link wajib diisi.',
            'url.required' => 'URL link wajib diisi.',
            'url.url' => 'URL yang dimasukkan tidak valid.',
        ]);

        // =====================================================
        // CEK KONTEN SENSITIF (JUDOL / PINJOL)
        // =====================================================
        $moderation = ContentModerationService::check($request->url);

        if ($moderation['blocked']) {
            return response()->json([
                'success' => false,
                'message' => 'URL yang Anda masukkan terdeteksi sebagai konten yang tidak diizinkan. '
                    . 'PENINK melarang penambahan link ke situs judi online / pinjaman ilegal.',
                'error_code' => 'blocked_content',
            ], 422);
        }

        $item = $linkHub->items()->create([
            'title' => $request->title,
            'url' => $request->url,
            'sort_order' => ($linkHub->items()->max('sort_order') ?? 0) + 1,
            'status' => true,
        ]);

        AuditLogService::log(
            'linkhub.item.created',
            'LinkHubItem',
            $item->id,
            'Link berhasil ditambahkan ke Link Hub',
            [
                'link_hub_id' => $linkHub->id,
                'hub_title' => $linkHub->title,
                'title' => $item->title,
                'url' => $item->url,
                'sort_order' => $item->sort_order,
            ]
        );

        return response()->json([
            'message' => 'Link berhasil ditambahkan ke Link Hub.',
            'data' => $item,
        ], 201);
    }

    public function updateItem(Request $request, $hubId, $itemId)
    {
        $linkHub = $this->findLinkHub($request, $hubId);

        $item = $linkHub->items()
            ->where('id', $itemId)
            ->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|url',
            'sort_order' => 'sometimes|integer|min:0',
        ], [
            'title.required' => 'Judul link wajib diisi.',
            'url.required' => 'URL link wajib diisi.',
            'url.url' => 'URL yang dimasukkan tidak valid.',
        ]);

        // =====================================================
        // CEK KONTEN SENSITIF (JUDOL / PINJOL)
        // =====================================================
        $moderation = ContentModerationService::check($request->url);

        if ($moderation['blocked']) {
            return response()->json([
                'success' => false,
                'message' => 'URL yang Anda masukkan terdeteksi sebagai konten yang tidak diizinkan. '
                    . 'PENINK melarang penambahan link ke situs judi online / pinjaman ilegal.',
                'error_code' => 'blocked_content',
            ], 422);
        }

        $item->update([
            'title' => $request->title,
            'url' => $request->url,
            'sort_order' => $request->has('sort_order')
                ? $request->sort_order
                : $item->sort_order,
        ]);

        AuditLogService::log(
            'linkhub.item.updated',
            'LinkHubItem',
            $item->id,
            'Link berhasil diperbarui',
            [
                'link_hub_id' => $linkHub->id,
                'hub_title' => $linkHub->title,
                'title' => $item->title,
                'url' => $item->url,
                'sort_order' => $item->sort_order,
            ]
        );

        return response()->json([
            'message' => 'Link berhasil diperbarui.',
            'data' => $item,
        ]);
    }

    public function deleteItem(Request $request, $hubId, $itemId)
    {
        $linkHub = $this->findLinkHub($request, $hubId);

        $item = $linkHub->items()->where('id', $itemId)->firstOrFail();

        $auditData = [
            'link_hub_id' => $linkHub->id,
            'hub_title' => $linkHub->title,
            'title' => $item->title,
            'url' => $item->url,
            'sort_order' => $item->sort_order,
            'status' => $item->status,
        ];

        $item->delete();

        AuditLogService::log(
            'linkhub.item.deleted',
            'LinkHubItem',
            $itemId,
            'Link berhasil dihapus',
            $auditData
        );

        return response()->json([
            'message' => 'Link berhasil dihapus.',
        ]);
    }

    public function toggleItemStatus(Request $request, $hubId, $itemId)
    {
        $linkHub = $this->findLinkHub($request, $hubId);

        $item = $linkHub->items()
            ->where('id', $itemId)
            ->firstOrFail();

        $item->status = !$item->status;
        $item->save();

        AuditLogService::log(
            'linkhub.item.status_changed',
            'LinkHubItem',
            $item->id,
            $item->status
                ? 'Link berhasil diaktifkan'
                : 'Link berhasil dinonaktifkan',
            [
                'link_hub_id' => $linkHub->id,
                'hub_title' => $linkHub->title,
                'title' => $item->title,
                'url' => $item->url,
                'status' => $item->status,
            ]
        );

        return response()->json([
            'message' => $item->status
                ? 'Link berhasil diaktifkan.'
                : 'Link berhasil dinonaktifkan.',
            'data' => $item,
        ]);
    }

    public function update(Request $request, $id)
    {
        // =====================================================
        // PAKSA SHORT_CODE JADI LOWERCASE
        // =====================================================
        if ($request->filled('short_code')) {
            $request->merge([
                'short_code' => strtolower($request->short_code),
            ]);
        }

        $linkHub = $this->findLinkHub($request, $id);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',

            'type' => 'required|in:ASN,UMUM',

            'short_code' => [
                'required',
                'alpha_dash',
                'unique:link_hubs,short_code,' . $linkHub->id,

                function ($attribute, $value, $fail) use ($linkHub) {
                    $value = strtolower($value);

                    $reservedAliases = [
                        'admin',
                        'login',
                        'register',
                        'api',
                        'dashboard',
                        'user',
                        'settings',
                    ];

                    if (in_array($value, $reservedAliases, true)) {
                        $fail(
                            'Alias ini tidak dapat digunakan karena termasuk alias yang dicadangkan.'
                        );
                        return;
                    }

                    $existingShortLink = ShortLink::where('short_code', $value)->first();
                    if ($existingShortLink) {
                        $fail(
                            'Alias tersebut sudah digunakan oleh Short Link. Silakan pilih alias lain.'
                        );
                        return;
                    }

                    $locked = LockedAlias::where('alias', $value)->first();
                    if ($locked) {
                        $userUnitKerjaId = auth()->user()->unit_kerja_id;
                        $isAdmin = in_array(
                            strtolower(auth()->user()->role),
                            ['admin', 'super_admin', 'admin_unit']
                        );

                        if (!$isAdmin && $locked->unit_kerja_id != $userUnitKerjaId) {
                            $namaOpd = $locked->unitKerja?->nama_unit_kerja
                                ?? 'OPD lain';

                            $fail(
                                "Alias '{$value}' dikunci untuk {$namaOpd}. Silakan pakai alias lain."
                            );
                        }
                    }
                },
            ],

            'locked_unit_kerja_id' => 'nullable|exists:unit_kerjas,id',
        ], [
            'title.required' => 'Judul Link Hub wajib diisi.',
            'short_code.required' => 'Alias Link Hub wajib diisi.',
            'short_code.alpha_dash' => 'Alias hanya boleh berisi huruf, angka, tanda hubung (-), dan garis bawah (_).',
            'short_code.unique' => 'Alias Link Hub sudah digunakan.',
            'type.required' => 'Jenis Link Hub wajib dipilih.',
            'type.in' => 'Jenis Link Hub harus ASN atau UMUM.',

            'locked_unit_kerja_id.exists' => 'Unit kerja yang dipilih tidak valid.',
        ]);

        $linkHub->update([
            'title' => $request->title,
            'description' => $request->description,
            'short_code' => $request->short_code,
            'type' => $request->type,
            'locked_unit_kerja_id' => $request->locked_unit_kerja_id,
        ]);

        $linkHub->load('lockedUnitKerja');

        AuditLogService::log(
            'linkhub.updated',
            'LinkHub',
            $linkHub->id,
            'Link Hub berhasil diperbarui',
            [
                'title' => $linkHub->title,
                'short_code' => $linkHub->short_code,
                'type' => $linkHub->type,
                'locked_unit_kerja_id' => $linkHub->locked_unit_kerja_id,
            ]
        );

        return response()->json([
            'message' => 'Link Hub berhasil diperbarui.',
            'data' => $linkHub,
        ]);
    }

    public function toggleStatus(Request $request, $id)
    {
        $linkHub = $this->findLinkHub($request, $id);

        $linkHub->status = !$linkHub->status;
        $linkHub->save();

        AuditLogService::log(
            'linkhub.status_changed',
            'LinkHub',
            $linkHub->id,
            $linkHub->status
                ? 'Link Hub berhasil diaktifkan'
                : 'Link Hub berhasil dinonaktifkan',
            [
                'title' => $linkHub->title,
                'short_code' => $linkHub->short_code,
                'status' => $linkHub->status,
            ]
        );

        return response()->json([
            'message' => $linkHub->status
                ? 'Link Hub berhasil diaktifkan.'
                : 'Link Hub berhasil dinonaktifkan.',
            'data' => $linkHub,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $linkHub = $this->findLinkHub($request, $id);

        $auditData = [
            'title' => $linkHub->title,
            'short_code' => $linkHub->short_code,
            'description' => $linkHub->description,
        ];

        $linkHub->items()->delete();
        $linkHub->delete();

        AuditLogService::log(
            'linkhub.deleted',
            'LinkHub',
            $id,
            'Link Hub berhasil dihapus',
            $auditData
        );

        return response()->json([
            'message' => 'Link Hub berhasil dihapus.',
        ]);
    }

    /**
     * Public show — menampilkan Link Hub untuk akses publik.
     */
    public function publicShow(Request $request, $shortCode)
    {
        $linkHub = LinkHub::with([
            'items' => function ($query) {
                $query->where('status', true)
                    ->orderBy('sort_order');
            },
            'lockedUnitKerja',
        ])
            ->where('short_code', $shortCode)
            ->where('status', true)
            ->firstOrFail();

        // Cek apakah link hub terkunci
        if ($linkHub->locked_unit_kerja_id) {
            $user = auth('sanctum')->user();

            // Kalau tidak ada user (akses publik tanpa login)
            if (!$user) {
                return response()->json([
                    'message' => 'Link Hub ini terkunci. Silakan login terlebih dahulu.',
                    'error_code' => 'login_required',
                    'locked_unit_kerja' => $linkHub->lockedUnitKerja?->nama_unit_kerja,
                ], 401);
            }

            // Admin, super_admin, admin_unit → bypass
            $isAdmin = in_array(
                strtolower($user->role),
                ['admin', 'super_admin', 'admin_unit']
            );
            $isMatch = (int) $user->unit_kerja_id === (int) $linkHub->locked_unit_kerja_id;

            if (!$isMatch && !$isAdmin) {
                return response()->json([
                    'message' => 'Link Hub ini hanya dapat diakses oleh unit kerja: '
                        . ($linkHub->lockedUnitKerja?->nama_unit_kerja ?? 'Tertentu'),
                    'error_code' => 'forbidden',
                    'locked_unit_kerja' => $linkHub->lockedUnitKerja?->nama_unit_kerja,
                ], 403);
            }
        }

        return response()->json([
            'message' => 'Link Hub berhasil diambil.',
            'data' => $linkHub,
        ]);
    }
}