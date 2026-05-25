<?php

namespace App\Http\Controllers;

use App\Services\Omni\OmnichannelChatAnalyticsService;
use App\Support\OmnichannelAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OmnichannelChatAnalyticsController extends Controller
{
    public function index(Request $request, OmnichannelChatAnalyticsService $analytics): Response
    {
        $this->assertAccess($request);

        $data = $analytics->build(
            $request->user(),
            $request->get('date_from'),
            $request->get('date_to'),
            $request->get('channel'),
        );

        return Inertia::render('Crm/OmnichannelAnalytics/Index', [
            'filters' => $data['filters'],
            'summary' => $data['summary'],
            'series' => $data['series'],
            'channelOptions' => [
                ['value' => 'all', 'label' => 'Semua kanal'],
                ['value' => 'whatsapp', 'label' => 'WhatsApp'],
                ['value' => 'instagram', 'label' => 'Instagram'],
                ['value' => 'messenger', 'label' => 'Messenger'],
            ],
        ]);
    }

    /**
     * Data analisis chat untuk YMSoft App (approval-app API).
     */
    public function apiIndex(Request $request, OmnichannelChatAnalyticsService $analytics): JsonResponse
    {
        $this->assertAccess($request);

        $data = $analytics->build(
            $request->user(),
            $request->get('date_from'),
            $request->get('date_to'),
            $request->get('channel'),
        );

        return response()->json([
            'success' => true,
            'filters' => $data['filters'],
            'summary' => $data['summary'],
            'series' => $data['series'],
            'channel_options' => [
                ['value' => 'all', 'label' => 'Semua kanal'],
                ['value' => 'whatsapp', 'label' => 'WhatsApp'],
                ['value' => 'instagram', 'label' => 'Instagram'],
                ['value' => 'messenger', 'label' => 'Messenger'],
            ],
        ]);
    }

    private function assertAccess(Request $request): void
    {
        abort_unless(OmnichannelAuthorization::canViewInbox($request->user()), 403);
    }
}
