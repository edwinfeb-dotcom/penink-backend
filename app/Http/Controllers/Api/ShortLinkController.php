<?php

namespace App\Http\Controllers\Api;

use App\Models\ShortLink;
use App\Models\LinkHub;
use App\Models\LockedAlias;
use App\Services\AuditLogService;
use App\Models\ShortLinkClick;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ContentModerationService;

class ShortLinkController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $shortLinks = $request->user()
            ->shortLinks()
            ->with('lockedUnitKerja')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Data short link berhasil diambil',
            'data' => $shortLinks,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
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
            'original_url' => 'required|url',

            'type' => 'required|in:ASN,UMUM',

            'short_code' => [
                'nullable',
                'alpha_dash',
                'unique:short_links,short_code',

                function ($attribute, $value, $fail) {
                    if (!$value) return;

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
                            'Alias tersebut tidak dapat digunakan karena merupakan alias sistem.'
                        );
                        return;
                    }

                    if (LinkHub::where('short_code', $value)->exists()) {
                        $fail(
                            'Alias tersebut sudah digunakan oleh Link Hub. Silakan pilih alias lain.'
                        );
                        return;
                    }

                    $locked = LockedAlias::where('alias', $value)->first();
                    if ($locked) {
                        $userUnitKerjaId = auth()->user()->unit_kerja_id;
                        $isAdmin = in_array(
                            strtolower(auth()->user()->role),
                            ['admin', 'super_admin']
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

            'title' => 'nullable|string|max:255',

            'locked_unit_kerja_id' => 'nullable|exists:unit_kerjas,id',
        ], [
            'short_code.unique' =>
                'Alias tersebut sudah digunakan. Silakan pilih alias lain.',

            'type.required' => 'Jenis link wajib dipilih.',
            'type.in' => 'Jenis link harus ASN atau UMUM.',

            'locked_unit_kerja_id.exists' => 'Unit kerja yang dipilih tidak valid.',
        ]);

        // =====================================================
        // CEK KONTEN SENSITIF (JUDOL / PINJOL)
        // =====================================================
        $moderation = ContentModerationService::check($request->original_url);

        if ($moderation['blocked']) {
            AuditLogService::log(
                'shortlink.blocked',
                'ShortLink',
                0,
                'Link terdeteksi terlarang diblokir',
                [
                    'original_url' => $request->original_url,
                    'reason' => $moderation['reason'],
                    'user_id' => auth()->id(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => $moderation['message'],
                'error_code' => 'blocked_content',
            ], 422);
        }

        // =====================================================
        // SIMPAN SHORT LINK
        // =====================================================
        $shortLink = ShortLink::create([
            'user_id' => auth()->id(),
            'original_url' => $request->original_url,
            'short_code' => $request->short_code ?? $this->generateUniqueShortCode(),
            'type' => $request->type,
            'title' => $request->title,
            'locked_unit_kerja_id' => $request->locked_unit_kerja_id,
            'status' => true,
        ]);

        $shortLink->load('lockedUnitKerja');

        AuditLogService::log(
            'shortlink.created',
            'ShortLink',
            $shortLink->id,
            'Short Link berhasil dibuat',
            [
                'short_code' => $shortLink->short_code,
                'original_url' => $shortLink->original_url,
                'type' => $shortLink->type,
                'locked_unit_kerja_id' => $shortLink->locked_unit_kerja_id,
            ]
        );

        return response()->json([
            'message' => 'URL berhasil dipendekkan',
            'data' => $shortLink,
        ], 201);
    }

    /**
     * Redirect short link ke URL asli.
     */
    public function redirect(Request $request, string $shortCode)
    {
        $shortLink = ShortLink::with('lockedUnitKerja')
            ->where('short_code', $shortCode)
            ->firstOrFail();

        if (!$shortLink->status) {
            return response()->json([
                'message' => 'Short link ini sedang tidak aktif.'
            ], 403);
        }

        if ($shortLink->locked_unit_kerja_id) {
            $user = auth('sanctum')->user();

            if (!$user) {
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
                return redirect()->away(
                    $frontendUrl . '/?locked_link=' . $shortCode . '&unit='
                    . urlencode($shortLink->lockedUnitKerja?->nama_unit_kerja ?? '')
                );
            }

            $isAdmin = in_array(
                strtolower($user->role),
                ['admin', 'super_admin']
            );
            $isMatch = (int) $user->unit_kerja_id === (int) $shortLink->locked_unit_kerja_id;

            if (!$isMatch && !$isAdmin) {
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
                return redirect()->away(
                    $frontendUrl . '/?locked_link=' . $shortCode . '&unit='
                    . urlencode($shortLink->lockedUnitKerja?->nama_unit_kerja ?? '')
                );
            }
        }

        $source = $request->query('source') === 'qr'
            ? 'qr'
            : 'normal';

        $shortLink->clicks()->create([
            'clicked_at' => now(),
            'source' => $source,
        ]);

        $shortLink->increment('click_count');

        return redirect()->away($shortLink->original_url);
    }

    /**
     * Mengambil jumlah klik hari ini.
     */
    public function todayClicks(Request $request)
    {
        $query = ShortLinkClick::whereDate(
            'clicked_at',
            today()
        );

        $isAdmin = in_array(
            strtolower($request->user()->role),
            ['admin', 'super_admin']
        );

        if (!$isAdmin) {
            $query->whereHas('shortLink', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            });
        }

        $todayClicks = $query->count();

        return response()->json([
            'message' => 'Data klik hari ini berhasil diambil.',
            'total_clicks_today' => $todayClicks,
        ]);
    }

    public function monthClicks(Request $request)
    {
        $query = ShortLinkClick::whereMonth(
            'clicked_at',
            now()->month
        )
        ->whereYear(
            'clicked_at',
            now()->year
        );

        $isAdmin = in_array(
            strtolower($request->user()->role),
            ['admin', 'super_admin']
        );

        if (!$isAdmin) {
            $query->whereHas('shortLink', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            });
        }

        $monthClicks = $query->count();

        return response()->json([
            'message' => 'Data klik bulan ini berhasil diambil.',
            'total_clicks_this_month' => $monthClicks,
        ]);
    }

    public function qrScans(Request $request)
    {
        $query = ShortLinkClick::where('source', 'qr');

        $isAdmin = in_array(
            strtolower($request->user()->role),
            ['admin', 'super_admin']
        );

        if (!$isAdmin) {
            $query->whereHas('shortLink', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            });
        }

        $totalQrScans = $query->count();

        return response()->json([
            'message' => 'Total QR Scan berhasil diambil.',
            'total_qr_scans' => $totalQrScans,
        ]);
    }

    public function stats(Request $request)
    {
        $days = (int) $request->query('days', 7);

        if (!in_array($days, [7, 30])) {
            $days = 7;
        }

        $startDate = now()
            ->subDays($days - 1)
            ->startOfDay();

        $query = ShortLinkClick::where(
            'clicked_at',
            '>=',
            $startDate
        );

        $isAdmin = in_array(
            strtolower($request->user()->role),
            ['admin', 'super_admin']
        );

        if (!$isAdmin) {
            $query->whereHas('shortLink', function ($query) use ($request) {
                $query->where(
                    'user_id',
                    $request->user()->id
                );
            });
        }

        $clicks = $query
            ->selectRaw(
                'DATE(clicked_at) as date, COUNT(*) as total_clicks'
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $stats = [];

        for ($i = 0; $i < $days; $i++) {
            $date = now()
                ->subDays($days - 1 - $i)
                ->format('Y-m-d');

            $stats[] = [
                'date' => $date,
                'total_clicks' => isset($clicks[$date])
                    ? (int) $clicks[$date]->total_clicks
                    : 0,
            ];
        }

        return response()->json([
            'message' => 'Statistik klik berhasil diambil.',
            'period_days' => $days,
            'data' => $stats,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         // =====================================================
    // PAKSA SHORT_CODE JADI LOWERCASE
    // =====================================================
    if ($request->filled('short_code')) {
        $request->merge([
            'short_code' => strtolower($request->short_code),
        ]);
    }


        $shortLink = ShortLink::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $request->validate([
            'original_url' => 'required|url',

            'short_code' => [
                'required',
                'alpha_dash',
                'unique:short_links,short_code,' . $shortLink->id,

                function ($attribute, $value, $fail) {
                    $reservedAliases = [
                        'admin',
                        'login',
                        'register',
                        'api',
                        'dashboard',
                        'user',
                        'settings',
                    ];

                    if (in_array(strtolower($value), $reservedAliases, true)) {
                        $fail(
                            'Alias tersebut tidak dapat digunakan karena merupakan alias sistem.'
                        );
                    }
                    if (LinkHub::where('short_code', $value)->exists()) {
                        $fail(
                            'Alias tersebut sudah digunakan oleh Link Hub. Silakan pilih alias lain.'
                        );
                    }
                },
            ],

            'title' => 'nullable|string|max:255',

            'locked_unit_kerja_id' => 'nullable|exists:unit_kerjas,id',
        ], [
            'short_code.unique' =>
                'Alias tersebut sudah digunakan. Silakan pilih alias lain.',

            'locked_unit_kerja_id.exists' => 'Unit kerja yang dipilih tidak valid.',
        ]);

        // =====================================================
        // CEK KONTEN SENSITIF (JUDOL / PINJOL)
        // =====================================================
        $moderation = ContentModerationService::check($request->original_url);

        if ($moderation['blocked']) {
            return response()->json([
                'success' => false,
                'message' => $moderation['message'],
                'error_code' => 'blocked_content',
            ], 422);
        }

        // =====================================================
        // UPDATE SHORT LINK
        // =====================================================
        $shortLink->update([
            'original_url' => $request->original_url,
            'short_code' => $request->short_code,
            'title' => $request->title,
            'locked_unit_kerja_id' => $request->locked_unit_kerja_id,
        ]);

        $shortLink->load('lockedUnitKerja');

        AuditLogService::log(
            'shortlink.updated',
            'ShortLink',
            $shortLink->id,
            'Short Link berhasil diperbarui',
            [
                'short_code' => $shortLink->short_code,
                'original_url' => $shortLink->original_url,
                'title' => $shortLink->title,
                'locked_unit_kerja_id' => $shortLink->locked_unit_kerja_id,
            ]
        );

        return response()->json([
            'message' => 'Short link berhasil diperbarui.',
            'data' => $shortLink,
        ]);
    }

    /**
     * Aktifkan / nonaktifkan short link.
     */
    public function toggleStatus(string $id)
    {
        $shortLink = ShortLink::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $shortLink->update([
            'status' => !$shortLink->status,
        ]);

        $shortLink->load('lockedUnitKerja');

        return response()->json([
            'message' => $shortLink->status
                ? 'Short link berhasil diaktifkan.'
                : 'Short link berhasil dinonaktifkan.',
            'data' => $shortLink,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $shortLink = ShortLink::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $shortLink->delete();

        return response()->json([
            'message' => 'Short link berhasil dihapus.',
        ]);
    }

    private function generateUniqueShortCode()
    {
        do {
            $code = Str::random(3);
        } while (
            ShortLink::where('short_code', $code)->exists()
            || LinkHub::where('short_code', $code)->exists()
        );

        return $code;
    }

    /**
     * Resolve short link terkunci untuk user yang login.
     */
    public function resolve(Request $request, string $code)
    {
        $shortLink = ShortLink::with('lockedUnitKerja')
            ->where('short_code', $code)
            ->firstOrFail();

        if (!$shortLink->status) {
            return response()->json([
                'success' => false,
                'message' => 'Short link ini sedang tidak aktif.',
            ], 403);
        }

        if ($shortLink->locked_unit_kerja_id) {
            $user = $request->user();
            $isAdmin = in_array(strtolower($user->role), ['admin', 'super_admin']);
            $isMatch = (int) $user->unit_kerja_id === (int) $shortLink->locked_unit_kerja_id;

            if (!$isMatch && !$isAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link terkunci untuk unit kerja: '
                        . ($shortLink->lockedUnitKerja?->nama_unit_kerja ?? 'Tertentu'),
                    'error_code' => 'forbidden',
                ], 403);
            }
        }

        $shortLink->clicks()->create([
            'clicked_at' => now(),
            'source' => 'normal',
        ]);
        $shortLink->increment('click_count');

        return response()->json([
            'success' => true,
            'original_url' => $shortLink->original_url,
        ]);
    }
}