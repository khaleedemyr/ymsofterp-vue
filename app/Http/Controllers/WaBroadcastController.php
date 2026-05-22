<?php

namespace App\Http\Controllers;

use App\Models\WaBroadcastCampaign;
use App\Services\Meta\MetaWhatsAppClient;
use App\Services\Wa\WaBroadcastCampaignService;
use App\Services\Wa\WaBroadcastDailyLimitService;
use App\Services\Wa\WaBroadcastRecipientResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaBroadcastController extends Controller
{
    public function index(WaBroadcastDailyLimitService $dailyLimit): Response
    {
        $campaigns = WaBroadcastCampaign::query()
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (WaBroadcastCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'status' => $c->status,
                'message_type' => $c->message_type,
                'template_name' => $c->template_name,
                'recipient_count_estimated' => $c->recipient_count_estimated,
                'recipient_count_total' => $c->recipient_count_total,
                'recipient_count_sent' => $c->recipient_count_sent,
                'recipient_count_failed' => $c->recipient_count_failed,
                'started_at' => $c->started_at?->toIso8601String(),
                'finished_at' => $c->finished_at?->toIso8601String(),
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Crm/WaBroadcast/Index', [
            'campaigns' => $campaigns,
            'dailyCap' => $dailyLimit->cap(),
            'dailySent' => $dailyLimit->todayCount(),
            'dailyRemaining' => $dailyLimit->remainingToday(),
            'memberLevels' => ['Silver', 'Loyal', 'Elite'],
        ]);
    }

    public function previewRecipients(Request $request, WaBroadcastRecipientResolver $resolver): JsonResponse
    {
        $filters = $request->input('filter_definition', []);
        if (! is_array($filters)) {
            $filters = [];
        }

        $count = $resolver->count($filters);
        $sample = $resolver->resolve($filters, 20)->values();

        return response()->json([
            'count' => $count,
            'sample' => $sample,
        ]);
    }

    public function templates(MetaWhatsAppClient $wa): JsonResponse
    {
        $rows = collect($wa->listMessageTemplates())
            ->filter(fn ($t) => is_array($t) && ($t['status'] ?? '') === 'APPROVED')
            ->map(fn ($t) => [
                'name' => $t['name'] ?? '',
                'language' => $t['language'] ?? 'id',
                'category' => $t['category'] ?? '',
            ])
            ->values();

        return response()->json(['templates' => $rows]);
    }

    public function store(Request $request, WaBroadcastCampaignService $service): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'message_type' => 'required|in:template,session_text',
            'template_name' => 'nullable|string|max:128',
            'template_language' => 'nullable|string|max:16',
            'template_body_params' => 'nullable|array',
            'template_body_params.*' => 'nullable|string|max:500',
            'session_text' => 'nullable|string|max:4096',
            'filter_definition' => 'required|array',
            'scheduled_at' => 'nullable|date',
            'start_now' => 'nullable|boolean',
        ]);

        $campaign = $service->createDraft($data, (int) $request->user()->id);

        if ($request->boolean('start_now')) {
            $service->start($campaign);
        }

        return response()->json([
            'campaign' => $campaign->fresh(),
            'message' => $request->boolean('start_now')
                ? 'Campaign dibuat dan mulai membangun daftar penerima.'
                : 'Draft campaign disimpan.',
        ]);
    }

    public function start(WaBroadcastCampaign $campaign, WaBroadcastCampaignService $service): JsonResponse
    {
        $service->start($campaign);

        return response()->json(['message' => 'Campaign dimulai.', 'campaign' => $campaign->fresh()]);
    }

    public function pause(WaBroadcastCampaign $campaign, WaBroadcastCampaignService $service): JsonResponse
    {
        $service->pause($campaign);

        return response()->json(['message' => 'Campaign dijeda.', 'campaign' => $campaign->fresh()]);
    }

    public function show(WaBroadcastCampaign $campaign): JsonResponse
    {
        $campaign->loadCount([
            'recipients as pending_count' => fn ($q) => $q->whereIn('status', ['pending', 'queued']),
        ]);

        return response()->json(['campaign' => $campaign]);
    }
}
