<?php

namespace App\Http\Controllers\Api;

use App\Models\ShortLink;
use App\Models\LinkHub;
use App\Services\AuditLogService;
use App\Models\ShortLinkClick;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShortLinkController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $shortLinks = $request->user()
            ->shortLinks()
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
        $request->validate([
            'original_url' => 'required|url',

            'short_code' => [
                'nullable',
                'alpha_dash',
                'unique:short_links,short_code',
                
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
        ], [
            'short_code.unique' =>
                'Alias tersebut sudah digunakan. Silakan pilih alias lain.',
        ]);

        $shortLink = ShortLink::create([
            'user_id' => auth()->id(),
            'original_url' => $request->original_url,
            'short_code' => $request->short_code ?? $this->generateUniqueShortCode(),
            'title' => $request->title,
        ]);

        AuditLogService::log(
            'shortlink.created',
            'ShortLink',
            $shortLink->id,
            'Short Link berhasil dibuat',
            [
                'short_code' => $shortLink->short_code,
                'original_url' => $shortLink->original_url,
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
    $shortLink = ShortLink::where(
        'short_code',
        $shortCode
    )->firstOrFail();

    if (!$shortLink->status) {
        return response()->json([
            'message' => 'Short link ini sedang tidak aktif.'
        ], 403);
    }

    // Tentukan sumber akses
    $source = $request->query('source') === 'qr'
        ? 'qr'
        : 'normal';

    // Catat setiap klik
    $shortLink->clicks()->create([
        'clicked_at' => now(),
        'source' => $source,
    ]);

    // Tambahkan total klik
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

    // Jika ADMIN, hitung semua klik
    if ($request->user()->role !== 'ADMIN') {
        // Jika USER, hanya hitung klik dari Short Link miliknya
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

    // Jika ADMIN, hitung semua klik
    if ($request->user()->role !== 'ADMIN') {
        // Jika USER, hanya hitung klik dari Short Link miliknya
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

    // Jika bukan ADMIN, hanya hitung QR scan milik user tersebut
    if ($request->user()->role !== 'ADMIN') {
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

    // Batasi pilihan periode agar aman
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

    // Jika bukan ADMIN, hanya hitung klik milik user tersebut
    if ($request->user()->role !== 'ADMIN') {
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
        ], [
            'short_code.unique' =>
                'Alias tersebut sudah digunakan. Silakan pilih alias lain.',
        ]);

        $shortLink->update([
            'original_url' => $request->original_url,
            'short_code' => $request->short_code,
            'title' => $request->title,
            ]);
            
            AuditLogService::log(
                'shortlink.updated',
                'ShortLink',
                $shortLink->id,
                'Short Link berhasil diperbarui',
                [
                    'short_code' => $shortLink->short_code,
                    'original_url' => $shortLink->original_url,
                    'title' => $shortLink->title,
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
}