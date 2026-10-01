<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LinkHub;
use App\Models\ShortLink;
use App\Models\ShortLinkClick;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StatisticsApiController extends Controller
{
    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | PERIODE
        |--------------------------------------------------------------------------
        | Default: 7 hari terakhir
        | Bisa diubah dengan ?period=30
        */

        $period = (int) $request->query('period', 7);

        if (!in_array($period, [7, 30])) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter period hanya boleh 7 atau 30.',
            ], 422);
        }

        $startDate = Carbon::today()->subDays($period - 1);
        $endDate = Carbon::today();

        /*
        |--------------------------------------------------------------------------
        | STATISTIK UMUM
        |--------------------------------------------------------------------------
        */

        $totalUsers = User::count();

        $totalShortLinks = ShortLink::count();

        $totalLinkHubs = LinkHub::count();

        $totalClicks = (int) ShortLink::sum('click_count');

        /*
        |--------------------------------------------------------------------------
        | STATISTIK USER BERDASARKAN JENIS
        |--------------------------------------------------------------------------
        */

        $usersByType = [
            'ASN' => User::where('user_type', 'ASN')->count(),
            'UMUM' => User::where('user_type', 'UMUM')->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | STATISTIK BERDASARKAN UNIT KERJA
        |--------------------------------------------------------------------------
        */

        $statisticsByUnit = User::with('unitKerja')
        ->get()
        ->groupBy(function ($user) {
            return $user->unitKerja?->nama_unit_kerja
            ?? 'Belum Memilih Unit Kerja';
            })
            ->map(function ($users, $unitName) {
                $userIds = $users->pluck('id');

                $shortLinks = ShortLink::whereIn('user_id', $userIds)->get();
                return [
                    'unit_kerja' => $unitName,
                    'users' => $users->count(),
                    'shortlinks'=> $shortLinks->count(),
                    'clicks' => (int) $shortLinks->sum('click_count'),
                    ];
                    })
                    
                    ->values();

        /*
        |--------------------------------------------------------------------------
        | STATISTIK KLIK BERDASARKAN PERIODE
        |--------------------------------------------------------------------------
        */

        $clicksByPeriod = ShortLinkClick::selectRaw(
            'DATE(clicked_at) as tanggal, COUNT(*) as clicks'
        )
            ->whereBetween('clicked_at', [
                $startDate->startOfDay(),
                $endDate->endOfDay(),
            ])
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get()
            ->map(function ($item) {
                return [
                    'tanggal' => $item->tanggal,
                    'clicks' => (int) $item->clicks,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Statistics API PENINK berhasil diambil.',
            'data' => [
                'period' => $period,

                'period_start' => $startDate->toDateString(),

                'period_end' => $endDate->toDateString(),

                'total_users' => $totalUsers,

                'total_shortlinks' => $totalShortLinks,

                'total_link_hubs' => $totalLinkHubs,

                'total_clicks' => $totalClicks,

                'users_by_type' => $usersByType,

                'statistics_by_unit' => $statisticsByUnit,

                'statistics_by_period' => $clicksByPeriod,
            ],
        ]);
    }
}