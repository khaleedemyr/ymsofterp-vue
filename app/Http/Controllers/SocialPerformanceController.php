<?php

namespace App\Http\Controllers;

use App\Services\Meta\MetaSocialContentSyncService;
use App\Services\Omni\SocialPerformanceAnalyticsService;
use App\Support\OmnichannelAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SocialPerformanceController extends Controller
{
    public function index(Request $request, SocialPerformanceAnalyticsService $analytics): Response
    {
        $this->assertAccess($request);

        $data = $analytics->build(
            $request->get('date_from'),
            $request->get('date_to'),
            $request->get('platform'),
        );

        return Inertia::render('Crm/SocialPerformance/Index', [
            'filters' => $data['filters'],
            'sync' => $data['sync'],
            'content' => $data['content'],
            'highlights' => $data['highlights'],
            'series' => $data['series'],
            'top' => $data['top'],
            'totals' => $data['totals'],
            'platformOptions' => [
                ['value' => 'all', 'label' => 'Semua (IG + FB)'],
                ['value' => 'instagram', 'label' => 'Instagram'],
                ['value' => 'facebook', 'label' => 'Facebook'],
            ],
        ]);
    }

    public function sync(Request $request, MetaSocialContentSyncService $syncService): JsonResponse
    {
        $this->assertAccess($request);

        $result = $syncService->syncAll();

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Sync selesai: %d konten dari %d akun (%d error).',
                $result['synced'],
                $result['accounts'],
                $result['errors']
            ),
            'result' => $result,
        ]);
    }

    public function exportCsv(Request $request, SocialPerformanceAnalyticsService $analytics): StreamedResponse
    {
        $this->assertAccess($request);

        $data = $analytics->build(
            $request->get('date_from'),
            $request->get('date_to'),
            $request->get('platform'),
        );

        $filename = 'social-performance-'.$data['filters']['date_from'].'_'.$data['filters']['date_to'].'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'platform',
                'account',
                'posted_at',
                'title',
                'permalink',
                'likes',
                'comments',
                'shares',
                'saved',
                'impressions',
                'reach',
                'clicks',
                'engagement',
            ]);
            foreach ($data['content'] as $row) {
                fputcsv($out, [
                    $row['platform'],
                    $row['account_label'] ?: $row['account_id'],
                    $row['posted_at'],
                    $row['caption'] ?: $row['title'],
                    $row['permalink'],
                    $row['likes'],
                    $row['comments'],
                    $row['shares'],
                    $row['saved'],
                    $row['impressions'],
                    $row['reach'],
                    $row['clicks'],
                    $row['engagement'],
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function assertAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        $ok = OmnichannelAuthorization::userHasPermission((int) $user->id, 'social_performance_view')
            || OmnichannelAuthorization::canViewInbox($user);

        abort_unless($ok, 403);
    }
}
