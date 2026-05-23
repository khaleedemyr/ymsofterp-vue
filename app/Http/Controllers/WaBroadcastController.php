<?php

namespace App\Http\Controllers;

use App\Models\WaBroadcastCampaign;
use App\Services\Meta\MetaWhatsAppClient;
use App\Services\Wa\WaBroadcastCampaignService;
use App\Services\Wa\WaBroadcastDailyLimitService;
use App\Services\Wa\WaBroadcastRecipientResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaBroadcastController extends Controller
{
    public function index(WaBroadcastDailyLimitService $dailyLimit): Response
    {
        return Inertia::render('Crm/WaBroadcast/Index', [
            'campaigns' => $this->mapCampaignsForList(),
            'dailyCap' => $dailyLimit->cap(),
            'dailySent' => $dailyLimit->todayCount(),
            'dailyRemaining' => $dailyLimit->remainingToday(),
        ]);
    }

    public function create(WaBroadcastDailyLimitService $dailyLimit): Response
    {
        return Inertia::render('Crm/WaBroadcast/Create', [
            'dailyCap' => $dailyLimit->cap(),
            'dailySent' => $dailyLimit->todayCount(),
            'dailyRemaining' => $dailyLimit->remainingToday(),
            'memberLevels' => ['Silver', 'Loyal', 'Elite'],
            'metaTemplatesUrl' => 'https://business.facebook.com/latest/whatsapp_manager/message_templates',
        ]);
    }

    public function store(Request $request, WaBroadcastCampaignService $service): JsonResponse|RedirectResponse
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

        if ($request->header('X-Inertia') || ! $request->expectsJson()) {
            return redirect()
                ->route('crm.wa-broadcast.index')
                ->with('success', $request->boolean('start_now')
                    ? 'Campaign dibuat dan sedang diproses.'
                    : 'Draft campaign disimpan.');
        }

        return response()->json([
            'campaign' => $campaign->fresh(),
            'message' => $request->boolean('start_now')
                ? 'Campaign dibuat dan mulai membangun daftar penerima.'
                : 'Draft campaign disimpan.',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapCampaignsForList(): array
    {
        return WaBroadcastCampaign::query()
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (WaBroadcastCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'status' => $c->status,
                'message_type' => $c->message_type,
                'template_name' => $c->template_name,
                'template_language' => $c->template_language,
                'recipient_count_estimated' => $c->recipient_count_estimated,
                'recipient_count_total' => $c->recipient_count_total,
                'recipient_count_sent' => $c->recipient_count_sent,
                'recipient_count_failed' => $c->recipient_count_failed,
                'recipient_count_skipped' => $c->recipient_count_skipped,
                'started_at' => $c->started_at?->toIso8601String(),
                'finished_at' => $c->finished_at?->toIso8601String(),
                'created_at' => $c->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function previewRecipients(Request $request, WaBroadcastRecipientResolver $resolver): JsonResponse
    {
        $filters = $request->input('filter_definition', []);
        if (! is_array($filters)) {
            $filters = [];
        }

        try {
            $count = $resolver->count($filters);
            $sample = $resolver->resolve($filters, 20)->values();
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Gagal menghitung penerima: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'count' => $count,
            'sample' => $sample,
        ]);
    }

    public function templates(MetaWhatsAppClient $wa): JsonResponse
    {
        $all = collect($wa->listMessageTemplates())
            ->filter(fn ($t) => is_array($t))
            ->map(fn ($t) => [
                'name' => $t['name'] ?? '',
                'language' => $t['language'] ?? 'id',
                'category' => $t['category'] ?? '',
                'status' => $t['status'] ?? '',
            ])
            ->values();

        $approved = $all
            ->filter(fn ($t) => ($t['status'] ?? '') === 'APPROVED')
            ->values();

        return response()->json([
            'templates' => $approved,
            'all' => $all,
        ]);
    }

    public function storeTemplate(Request $request, MetaWhatsAppClient $wa): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:128',
            'category' => 'required|in:MARKETING,UTILITY,AUTHENTICATION',
            'language' => 'required|string|max:16',
            'body' => 'required|string|max:1024',
            'body_examples' => 'nullable|array',
            'body_examples.*' => 'nullable|string|max:500',
        ]);

        $name = strtolower(preg_replace('/[^a-z0-9]+/', '_', trim($data['name'])) ?? '');
        $name = trim($name, '_');
        if ($name === '' || strlen($name) < 3) {
            return response()->json(['message' => 'Nama template minimal 3 karakter (huruf/angka, pakai underscore).'], 422);
        }

        $body = trim($data['body']);
        $examples = array_values(array_filter(
            array_map('strval', $data['body_examples'] ?? []),
            fn (string $s) => $s !== ''
        ));

        if (preg_match('/\{\{\d+\}\}/', $body) !== 0) {
            preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);
            $maxVar = (int) max(array_map('intval', $matches[1] ?? [0]));
            if (count($examples) < $maxVar) {
                return response()->json([
                    'message' => "Body memakai variabel sampai {{{$maxVar}}}. Isi {$maxVar} contoh variabel (satu per baris).",
                ], 422);
            }
            $examples = array_slice($examples, 0, $maxVar);
        }

        try {
            $result = $wa->createMessageTemplate(
                $name,
                $data['category'],
                $data['language'],
                $body,
                $examples
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Template diajukan ke Meta. Status awal biasanya PENDING — muat ulang daftar setelah disetujui.',
            'template' => [
                'name' => $name,
                'language' => $data['language'],
                'category' => $data['category'],
                'status' => 'PENDING',
            ],
            'meta' => $result,
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
