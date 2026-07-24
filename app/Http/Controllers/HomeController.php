<?php

namespace App\Http\Controllers;

use App\Services\CvccRegionalCapaHomeService;
use App\Services\RegionalVisitAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function show()
    {
        return Inertia::render('Home', [
            // ...props lain jika ada
        ]);
    }

    /**
     * Ringkasan target & pencapaian kunjungan outlet untuk user Regional Management (Home widget).
     */
    public function regionalVisitSummary(Request $request, RegionalVisitAnalyticsService $analytics)
    {
        $userId = (int) ($request->user()->id ?? 0);
        $summary = $analytics->homeVisitSummaryForUser($userId);

        if ($summary === null) {
            return response()->json([
                'success' => true,
                'is_regional' => false,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'is_regional' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Kasus CVCC yang di-tag ke user login dan masih perlu CAPA (belum isi + approved).
     */
    public function cvccRegionalCapaPending(Request $request, CvccRegionalCapaHomeService $service)
    {
        $userId = (int) ($request->user()->id ?? 0);
        $payload = $service->pendingForUser($userId);

        return response()->json([
            'success' => true,
            'count' => $payload['count'],
            'items' => $payload['items'],
        ]);
    }
}
