<?php

namespace App\Http\Controllers;

use App\Exports\FeedbackCapaExcelExport;
use App\Models\Outlet;
use App\Models\User;
use App\Services\FeedbackCapaService;
use App\Services\FeedbackCaseIngestionService;
use App\Services\NotificationService;
use App\Support\FeedbackCapaExportFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class CustomerVoiceCommandCenterController extends Controller
{
    public function __construct(
        private FeedbackCapaService $capaService
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('CustomerVoiceCommandCenter/Index', $this->buildPayload($request));
    }

    /**
     * Kasus selesai (Done) atau ulasan positif — untuk modal arsip di halaman index.
     */
    public function archiveCasesJson(Request $request)
    {
        $perPage = min(50, max(10, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));

        $query = DB::table('feedback_cases as c')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'c.id_outlet')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'c.assigned_to')
            ->leftJoin('tbl_data_jabatan as aj', 'aj.id_jabatan', '=', 'assignee.id_jabatan')
            ->select([
                'c.id',
                'c.source_type',
                'c.source_ref',
                'c.id_outlet',
                'o.nama_outlet',
                'c.author_name',
                'c.customer_contact',
                'c.meta',
                'c.topics',
                'c.event_at',
                'c.severity',
                'c.summary_id',
                'c.raw_text',
                'c.risk_score',
                'c.status',
                'c.follow_up_status',
                'c.assigned_to',
                'assignee.nama_lengkap as assigned_to_name',
                'aj.nama_jabatan as assigned_to_jabatan',
                'c.due_at',
                'c.resolved_at',
                'c.created_at',
            ])
            ->where(function ($q) {
                $q->whereIn('c.status', $this->voiceCaseCompletedStatuses())
                    ->orWhere('c.severity', 'positive');
            });

        $this->applyArchiveListFilters($query, $request);

        $paginator = $query
            ->orderByDesc('c.event_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $rows = collect($paginator->items())->map(fn ($row) => $this->presentVoiceCaseRow($row));

        return response()->json([
            'success' => true,
            'cases' => $rows->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Daftar kasus CAPA yang menunggu verifikasi bagian G oleh user login (dipilih sebagai verifikator).
     */
    public function pendingCapaVerificationsJson(Request $request)
    {
        $userId = (int) ($request->user()->id ?? 0);
        if ($userId <= 0) {
            return response()->json(['success' => true, 'count' => 0, 'items' => []]);
        }

        $rows = DB::table('feedback_cases as c')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'c.id_outlet')
            ->where(function ($q) {
                $q->whereRaw('JSON_EXTRACT(c.meta, "$.capa.g.result") IS NULL')
                    ->orWhereRaw(
                        'LOWER(TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(c.meta, "$.capa.g.result")), ""))) NOT IN (?, ?)',
                        ['effective', 'not_effective']
                    );
            })
            ->where(function ($q) use ($userId) {
                $q->whereRaw(
                    'CAST(JSON_UNQUOTE(JSON_EXTRACT(c.meta, "$.capa.g.verified_by_user_id")) AS UNSIGNED) = ?',
                    [$userId]
                )->orWhereRaw(
                    'CAST(JSON_UNQUOTE(JSON_EXTRACT(c.meta, "$.capa_divisions.service.g.verified_by_user_id")) AS UNSIGNED) = ?',
                    [$userId]
                )->orWhereRaw(
                    'CAST(JSON_UNQUOTE(JSON_EXTRACT(c.meta, "$.capa_divisions.kitchen.g.verified_by_user_id")) AS UNSIGNED) = ?',
                    [$userId]
                )->orWhereRaw(
                    'CAST(JSON_UNQUOTE(JSON_EXTRACT(c.meta, "$.capa_divisions.bar.g.verified_by_user_id")) AS UNSIGNED) = ?',
                    [$userId]
                );
            })
            ->orderByDesc('c.updated_at')
            ->limit(30)
            ->get([
                'c.id',
                'c.event_at',
                'c.summary_id',
                'c.status',
                'c.follow_up_status',
                'c.severity',
                'o.nama_outlet',
                'c.meta',
            ]);

        $items = $rows->map(function ($r) use ($userId) {
            $meta = [];
            if (! empty($r->meta)) {
                $meta = json_decode((string) $r->meta, true) ?: [];
            }
            $pendingDivisions = [];
            foreach (['service', 'kitchen', 'bar'] as $division) {
                $capa = null;
                if (isset($meta['capa_divisions'][$division]) && is_array($meta['capa_divisions'][$division])) {
                    $capa = $meta['capa_divisions'][$division];
                } elseif (($meta['capa_active_division'] ?? 'service') === $division && isset($meta['capa']) && is_array($meta['capa'])) {
                    $capa = $meta['capa'];
                }
                if (! is_array($capa)) {
                    continue;
                }
                $g = isset($capa['g']) && is_array($capa['g']) ? $capa['g'] : [];
                $verifierId = (int) ($g['verified_by_user_id'] ?? 0);
                $result = strtolower(trim((string) ($g['result'] ?? '')));
                $isDone = in_array($result, ['effective', 'not_effective'], true);
                if ($verifierId === $userId && ! $isDone) {
                    $pendingDivisions[] = $division;
                }
            }

            return [
                'id' => (int) $r->id,
                'event_at' => $r->event_at,
                'summary_id' => $r->summary_id,
                'status' => (string) ($r->status ?? ''),
                'follow_up_status' => (string) ($r->follow_up_status ?? 'new'),
                'severity' => (string) ($r->severity ?? ''),
                'nama_outlet' => (string) ($r->nama_outlet ?? ''),
                'pending_divisions' => $pendingDivisions,
            ];
        })->filter(fn ($x) => ! empty($x['pending_divisions']))->values()->all();

        return response()->json([
            'success' => true,
            'count' => count($items),
            'items' => $items,
        ]);
    }

    /**
     * Data satu kasus (shape sama seperti baris daftar) untuk deep link / preload panel detail.
     */
    public function caseBriefJson(Request $request, int $id)
    {
        $payload = $this->prepareCapaExport($id);
        if ($payload === null) {
            return response()->json([
                'success' => false,
                'message' => 'Case tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'case' => $payload['presented'],
        ]);
    }

    public function apiIndex(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->buildPayload($request),
        ]);
    }

    public function sync(Request $request, FeedbackCaseIngestionService $ingestion): RedirectResponse
    {
        $result = $ingestion->ingestAll(2500);

        $message = 'Sync selesai. '
            .'Google/Instagram: '.$result['google_instagram']['upserted'].' baris, '
            .'Guest Comment: '.$result['guest_comment']['upserted'].' baris.';

        return redirect()
            ->route('customer-voice-command-center.index', $this->voiceIndexFiltersFromRequest($request))
            ->with('success', $message);
    }

    public function apiSync(Request $request, FeedbackCaseIngestionService $ingestion)
    {
        $result = $ingestion->ingestAll(2500);

        $message = 'Sync selesai. '
            .'Google/Instagram: '.$result['google_instagram']['upserted'].' baris, '
            .'Guest Comment: '.$result['guest_comment']['upserted'].' baris.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'result' => $result,
        ]);
    }

    public function updateCase(Request $request, int $id): RedirectResponse
    {
        $result = $this->runFeedbackCaseRowUpdate($request, $id);
        if (! $result['success']) {
            return $this->redirectToVoiceIndex($request)->with('error', $result['message'] ?? 'Gagal memperbarui case.');
        }

        return $this->redirectToVoiceIndex($request)->with('success', $result['message'] ?? 'Case diperbarui.');
    }

    public function apiUpdateCase(Request $request, int $id)
    {
        $response = $this->runFeedbackCaseRowUpdate($request, $id);

        return response()->json($response, $response['success'] ? 200 : 404);
    }

    public function addNote(Request $request, int $id): RedirectResponse
    {
        $payload = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $exists = DB::table('feedback_cases')->where('id', $id)->exists();
        if (! $exists) {
            return redirect()->route('customer-voice-command-center.index')
                ->with('error', 'Case tidak ditemukan.');
        }

        DB::table('feedback_case_activities')->insert([
            'case_id' => $id,
            'activity_type' => 'note',
            'actor_user_id' => $request->user()->id ?? null,
            'from_status' => null,
            'to_status' => null,
            'note' => trim((string) $payload['note']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->redirectToVoiceIndex($request)->with('success', 'Catatan tersimpan.');
    }

    public function saveCapa(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $request->validate([
            'capa' => 'required|array',
            'capa_division' => 'nullable|string',
        ]);

        $row = DB::table('feedback_cases')->where('id', $id)->first();
        if (! $row) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case tidak ditemukan.',
                ], 404);
            }

            return redirect()->route('customer-voice-command-center.index')
                ->with('error', 'Case tidak ditemukan.');
        }

        $meta = [];
        if (! empty($row->meta)) {
            $meta = json_decode((string) $row->meta, true) ?: [];
        }

        $incoming = $request->input('capa');
        if (! is_array($incoming)) {
            $incoming = [];
        }
        $division = $this->normalizeCapaDivision($request->input('capa_division'));
        $oldVerifierId = $this->extractCapaVerifierUserId($meta);
        $preservedEvidence = [];
        $existingDivisionCapa = $this->getDivisionCapaFromMeta($meta, $division);
        if (! empty($existingDivisionCapa['evidence']) && is_array($existingDivisionCapa['evidence'])) {
            $preservedEvidence = $this->capaService->sanitizeEvidenceList($existingDivisionCapa['evidence']);
        }
        unset($incoming['evidence']);

        $sanitized = $this->capaService->sanitizeCapa($incoming);
        $sanitized['evidence'] = $preservedEvidence;
        $meta = $this->mergeDivisionCapaIntoMeta($meta, $division, $sanitized);
        $newVerifierId = $this->extractCapaVerifierUserId($meta);
        $meta = $this->stampCapaAuditMeta(
            $meta,
            $request->user()->id ?? null,
            $now = now(),
            $this->extractCapaVerificationResult($meta)
        );

        DB::transaction(function () use ($id, $meta, $request, $now) {
            DB::table('feedback_cases')->where('id', $id)->update([
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);

            DB::table('feedback_case_activities')->insert([
                'case_id' => $id,
                'activity_type' => 'capa_updated',
                'actor_user_id' => $request->user()->id ?? null,
                'from_status' => null,
                'to_status' => null,
                'note' => 'Form CAPA diperbarui.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $summaryRow = DB::table('feedback_cases')->where('id', $id)->first(['summary_id']);
        $this->notifyCapaVerifierIfNew($request, $id, $oldVerifierId, $newVerifierId, $summaryRow?->summary_id ?? null);

        $message = 'Form CAPA tersimpan.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return $this->redirectToVoiceIndex($request)->with('success', $message);
    }

    /**
     * Hapus seluruh data CAPA tersimpan untuk kasus (termasuk lampiran file di storage).
     */
    public function destroyCapa(Request $request, int $id): RedirectResponse
    {
        $result = $this->clearCapaForCase($request, $id);

        if ($result === 'not_found') {
            return redirect()->route('customer-voice-command-center.index', $this->voiceIndexFiltersFromRequest($request))
                ->with('error', 'Case tidak ditemukan.');
        }

        return $this->redirectToVoiceIndex($request)->with(
            'success',
            $result === 'already_empty'
                ? 'CAPA pada kasus ini sudah kosong.'
                : 'Data CAPA telah dihapus.'
        );
    }

    public function apiDestroyCapa(Request $request, int $id)
    {
        $result = $this->clearCapaForCase($request, $id);

        if ($result === 'not_found') {
            return response()->json([
                'success' => false,
                'message' => 'Case tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $result === 'already_empty'
                ? 'CAPA pada kasus ini sudah kosong.'
                : 'Data CAPA telah dihapus.',
        ]);
    }

    /**
     * @return 'cleared'|'already_empty'|'not_found'
     */
    private function clearCapaForCase(Request $request, int $id): string
    {
        $row = DB::table('feedback_cases')->where('id', $id)->first();
        if ($row === null) {
            return 'not_found';
        }

        $meta = [];
        if (! empty($row->meta)) {
            $meta = json_decode((string) $row->meta, true) ?: [];
        }

        $hasSingle = isset($meta['capa']) && is_array($meta['capa']);
        $hasDivisions = isset($meta['capa_divisions']) && is_array($meta['capa_divisions']);
        if (! $hasSingle && ! $hasDivisions) {
            return 'already_empty';
        }

        $prefix = 'feedback_case_capa/'.$id.'/';
        $allCapas = [];
        if ($hasSingle) {
            $allCapas[] = $meta['capa'];
        }
        if ($hasDivisions) {
            foreach (['service', 'kitchen', 'bar'] as $d) {
                if (isset($meta['capa_divisions'][$d]) && is_array($meta['capa_divisions'][$d])) {
                    $allCapas[] = $meta['capa_divisions'][$d];
                }
            }
        }
        foreach ($allCapas as $capa) {
            if (empty($capa['evidence']) || ! is_array($capa['evidence'])) {
                continue;
            }
            foreach ($capa['evidence'] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $path = isset($item['path']) ? (string) $item['path'] : '';
                if ($path === '' || ! str_starts_with($path, $prefix)) {
                    continue;
                }
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        unset($meta['capa']);
        unset($meta['capa_divisions']);
        unset($meta['capa_active_division']);
        unset($meta['capa_meta']);

        $now = now();
        DB::transaction(function () use ($id, $meta, $request, $now) {
            DB::table('feedback_cases')->where('id', $id)->update([
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);

            DB::table('feedback_case_activities')->insert([
                'case_id' => $id,
                'activity_type' => 'capa_cleared',
                'actor_user_id' => $request->user()->id ?? null,
                'from_status' => null,
                'to_status' => null,
                'note' => 'Form CAPA dihapus (reset).',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return 'cleared';
    }

    public function apiSaveCapa(Request $request, int $id)
    {
        $request->validate([
            'capa' => 'required|array',
            'capa_division' => 'nullable|string',
        ]);

        $row = DB::table('feedback_cases')->where('id', $id)->first();
        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Case tidak ditemukan.',
            ], 404);
        }

        $meta = [];
        if (! empty($row->meta)) {
            $meta = json_decode((string) $row->meta, true) ?: [];
        }

        $incoming = $request->input('capa');
        if (! is_array($incoming)) {
            $incoming = [];
        }
        $division = $this->normalizeCapaDivision($request->input('capa_division'));
        $oldVerifierId = $this->extractCapaVerifierUserId($meta);
        $preservedEvidence = [];
        $existingDivisionCapa = $this->getDivisionCapaFromMeta($meta, $division);
        if (! empty($existingDivisionCapa['evidence']) && is_array($existingDivisionCapa['evidence'])) {
            $preservedEvidence = $this->capaService->sanitizeEvidenceList($existingDivisionCapa['evidence']);
        }
        unset($incoming['evidence']);

        $sanitized = $this->capaService->sanitizeCapa($incoming);
        $sanitized['evidence'] = $preservedEvidence;
        $meta = $this->mergeDivisionCapaIntoMeta($meta, $division, $sanitized);
        $newVerifierId = $this->extractCapaVerifierUserId($meta);
        $meta = $this->stampCapaAuditMeta(
            $meta,
            $request->user()->id ?? null,
            $now = now(),
            $this->extractCapaVerificationResult($meta)
        );

        DB::transaction(function () use ($id, $meta, $request, $now) {
            DB::table('feedback_cases')->where('id', $id)->update([
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);

            DB::table('feedback_case_activities')->insert([
                'case_id' => $id,
                'activity_type' => 'capa_updated',
                'actor_user_id' => $request->user()->id ?? null,
                'from_status' => null,
                'to_status' => null,
                'note' => 'Form CAPA diperbarui.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $summaryRow = DB::table('feedback_cases')->where('id', $id)->first(['summary_id']);
        $this->notifyCapaVerifierIfNew($request, $id, $oldVerifierId, $newVerifierId, $summaryRow?->summary_id ?? null);

        $message = 'Form CAPA tersimpan.';

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function uploadCapaEvidence(Request $request, int $id)
    {
        $request->validate([
            'file' => 'required|file|max:15360',
        ]);

        $row = DB::table('feedback_cases')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Case tidak ditemukan.'], 404);
        }

        $meta = [];
        if (! empty($row->meta)) {
            $meta = json_decode((string) $row->meta, true) ?: [];
        }

        $capa = isset($meta['capa']) && is_array($meta['capa']) ? $meta['capa'] : $this->capaService->emptyTemplate();
        $existing = $this->capaService->sanitizeEvidenceList($capa['evidence'] ?? []);
        if (count($existing) >= 20) {
            return response()->json(['success' => false, 'message' => 'Maksimal 20 lampiran per kasus.'], 422);
        }

        $file = $request->file('file');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        if (! in_array($ext, $allowedExt, true)) {
            return response()->json(['success' => false, 'message' => 'Tipe file tidak diperbolehkan.'], 422);
        }

        $dir = 'feedback_case_capa/'.$id;
        $path = $file->store($dir, 'public');
        if (! $path) {
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan file.'], 500);
        }

        $item = [
            'id' => (string) Str::uuid(),
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_at' => now()->toIso8601String(),
        ];

        $existing[] = $item;
        $capa['evidence'] = $this->capaService->sanitizeEvidenceList($existing);
        $meta['capa'] = $capa;

        $now = now();
        DB::table('feedback_cases')->where('id', $id)->update([
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);

        $item['url'] = Storage::disk('public')->url($path);

        return response()->json([
            'success' => true,
            'message' => 'Lampiran berhasil diunggah.',
            'item' => $item,
        ]);
    }

    public function deleteCapaEvidence(Request $request, int $id, string $evidenceId)
    {
        $row = DB::table('feedback_cases')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Case tidak ditemukan.'], 404);
        }

        $meta = [];
        if (! empty($row->meta)) {
            $meta = json_decode((string) $row->meta, true) ?: [];
        }

        $capa = isset($meta['capa']) && is_array($meta['capa']) ? $meta['capa'] : [];
        $evidence = $capa['evidence'] ?? [];
        $removedPath = null;
        $next = [];
        foreach ($evidence as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['id'] ?? '') === $evidenceId) {
                $removedPath = isset($item['path']) ? (string) $item['path'] : '';

                continue;
            }
            $next[] = $item;
        }

        if ($removedPath === null || $removedPath === '') {
            return response()->json(['success' => false, 'message' => 'Lampiran tidak ditemukan.'], 404);
        }

        $prefix = 'feedback_case_capa/'.$id.'/';
        if (! str_starts_with($removedPath, $prefix)) {
            return response()->json(['success' => false, 'message' => 'Path tidak valid.'], 422);
        }

        if (Storage::disk('public')->exists($removedPath)) {
            Storage::disk('public')->delete($removedPath);
        }

        $capa['evidence'] = $this->capaService->sanitizeEvidenceList($next);
        $meta['capa'] = $capa;

        DB::table('feedback_cases')->where('id', $id)->update([
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lampiran dihapus.',
        ]);
    }

    public function apiUploadCapaEvidence(Request $request, int $id)
    {
        return $this->uploadCapaEvidence($request, $id);
    }

    public function apiDeleteCapaEvidence(Request $request, int $id, string $evidenceId)
    {
        return $this->deleteCapaEvidence($request, $id, $evidenceId);
    }

    public function exportPdf(Request $request)
    {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '180');

        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $maxRows = 600;
        $query = DB::table('feedback_cases as c')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'c.id_outlet')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'c.assigned_to')
            ->select([
                'c.id',
                'c.source_type',
                'c.source_ref',
                'c.id_outlet',
                'o.nama_outlet',
                'c.author_name',
                'c.customer_contact',
                'c.meta',
                'c.topics',
                'c.event_at',
                'c.severity',
                'c.summary_id',
                'c.raw_text',
                'c.risk_score',
                'c.status',
                'c.follow_up_status',
                'c.assigned_to',
                'assignee.nama_lengkap as assigned_to_name',
                'c.due_at',
                'c.resolved_at',
                'c.created_at',
            ]);

        $this->applyFilters($query, $request);

        $totalMatching = (clone $query)->count();

        $cases = $query
            ->orderByDesc('c.event_at')
            ->limit($maxRows)
            ->get();

        $presentedRows = $cases->map(fn ($case) => $this->presentVoiceCaseRow($case))->values();

        $regionalUserIds = [];
        foreach ($presentedRows as $row) {
            $ids = isset($row['regional_user_ids']) && is_array($row['regional_user_ids'])
                ? $row['regional_user_ids']
                : [];
            foreach ($ids as $id) {
                $n = (int) $id;
                if ($n > 0) {
                    $regionalUserIds[$n] = $n;
                }
            }
        }
        $regionalUserNames = $regionalUserIds === []
            ? []
            : User::query()->whereIn('id', array_values($regionalUserIds))->pluck('nama_lengkap', 'id')->all();

        $casesForPdf = $presentedRows->map(function (array $row) use ($regionalUserNames) {
            $regionalIds = isset($row['regional_user_ids']) && is_array($row['regional_user_ids'])
                ? $row['regional_user_ids']
                : [];
            $regionalLabels = [];
            foreach ($regionalIds as $id) {
                $n = (int) $id;
                if ($n > 0) {
                    $regionalLabels[] = $regionalUserNames[$n] ?? ('#'.$n);
                }
            }

            return (object) [
                'id' => (int) ($row['id'] ?? 0),
                'event_at' => $row['event_at'] ?? null,
                'nama_outlet' => (string) ($row['nama_outlet'] ?? ''),
                'regional' => implode(', ', array_values(array_unique($regionalLabels))),
                'source_type' => (string) ($row['source_type'] ?? ''),
                'author_name' => (string) ($row['author_name'] ?? ''),
                'customer_contact' => (string) ($row['customer_contact'] ?? ''),
                'customer_email' => (string) ($row['customer_email'] ?? ''),
                'follow_up_target' => $row['follow_up_target'] ?? null,
                'severity' => (string) ($row['severity'] ?? ''),
                'complaint_type_labels' => isset($row['complaint_type_labels']) && is_array($row['complaint_type_labels'])
                    ? $row['complaint_type_labels']
                    : [],
                'summary_short' => Str::limit((string) ($row['summary_id'] ?? ''), 90),
                'raw_short' => Str::limit(preg_replace('/\s+/u', ' ', (string) ($row['raw_text'] ?? '')), 220),
                'risk_score' => $row['risk_score'] ?? 0,
                'capa_filled' => ! empty($row['capa_filled']),
                'capa_verification' => is_array($row['capa_verification'] ?? null) ? $row['capa_verification'] : null,
                'due_at' => $row['due_at'] ?? null,
                'status' => (string) ($row['status'] ?? ''),
                'assigned_to_name' => (string) ($row['assigned_to_name'] ?? ''),
            ];
        });

        unset($cases);

        $pdf = \PDF::loadView('exports.customer_voice_cases_pdf', [
            'cases' => $casesForPdf,
            'dateFrom' => (string) $request->input('date_from'),
            'dateTo' => (string) $request->input('date_to'),
            'generatedAt' => now()->format('Y-m-d H:i'),
            'totalExported' => $casesForPdf->count(),
            'totalMatching' => $totalMatching,
            'maxRows' => $maxRows,
        ])->setPaper('a4', 'landscape');

        $filename = 'customer-voice-'.$request->input('date_from').'_to_'.$request->input('date_to').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export satu kasus: form CAPA lengkap (PDF).
     */
    public function exportCapaPdf(Request $request, int $id)
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '120');

        $payload = $this->prepareCapaExport($id);
        if ($payload === null) {
            abort(404);
        }

        $presented = $payload['presented'];
        $capaEvidencePdfImages = $payload['capa_evidence_pdf_images'] ?? [];

        $pdf = \PDF::loadView('exports.feedback_capa_pdf', [
            'caseId' => $presented['id'],
            'outlet' => (string) ($presented['nama_outlet'] ?? ''),
            'generatedAt' => $payload['generated_at'],
            'capaGroupedSections' => $payload['capa_grouped_sections'],
            'capaEvidencePdfImages' => $capaEvidencePdfImages,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->capaExportBasename($id, 'pdf'));
    }

    /**
     * Export satu kasus: form CAPA lengkap (Excel).
     */
    public function exportCapaExcel(Request $request, int $id)
    {
        $payload = $this->prepareCapaExport($id);
        if ($payload === null) {
            abort(404);
        }

        return Excel::download(
            new FeedbackCapaExcelExport($payload['flat_rows']),
            $this->capaExportBasename($id, 'xlsx')
        );
    }

    /**
     * @return array{presented: array<string, mixed>, flat_rows: \Illuminate\Support\Collection<int, array{bagian: string, field: string, nilai: string}>, capa_grouped_sections: array<int, array{bagian: string, items: list<array{field: string, nilai: string}>}>, capa_evidence_pdf_images: list<array{label: string, src: string|null, note: string|null}>, generated_at: string}|null
     */
    private function prepareCapaExport(int $id): ?array
    {
        $case = DB::table('feedback_cases as c')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'c.id_outlet')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'c.assigned_to')
            ->leftJoin('tbl_data_jabatan as aj', 'aj.id_jabatan', '=', 'assignee.id_jabatan')
            ->where('c.id', $id)
            ->select([
                'c.id',
                'c.source_type',
                'c.source_ref',
                'c.id_outlet',
                'o.nama_outlet',
                'c.author_name',
                'c.customer_contact',
                'c.meta',
                'c.topics',
                'c.event_at',
                'c.severity',
                'c.summary_id',
                'c.raw_text',
                'c.risk_score',
                'c.status',
                'c.follow_up_status',
                'c.assigned_to',
                'assignee.nama_lengkap as assigned_to_name',
                'aj.nama_jabatan as assigned_to_jabatan',
                'c.due_at',
                'c.resolved_at',
                'c.created_at',
            ])
            ->first();

        if ($case === null) {
            return null;
        }

        $presented = $this->presentVoiceCaseRow($case);
        $formatter = new FeedbackCapaExportFormatter;
        $divisionRows = $this->collectCapaByDivisionForExport($presented);
        $flatRows = collect();
        $evidenceImages = [];
        foreach ($divisionRows as $div) {
            $label = (string) ($div['label'] ?? '');
            $capa = $div['capa'] ?? null;
            if (! is_array($capa)) {
                continue;
            }
            $rows = $formatter->flatten($presented, $capa)->map(function (array $row) use ($label) {
                $row['bagian'] = $label !== ''
                    ? '['.$label.'] '.(string) ($row['bagian'] ?? '')
                    : (string) ($row['bagian'] ?? '');

                return $row;
            });
            $flatRows = $flatRows->concat($rows);

            foreach ($this->capaService->pdfEmbedCapaEvidenceImages($capa) as $img) {
                $img['label'] = $label !== '' ? '['.$label.'] '.(string) ($img['label'] ?? '') : (string) ($img['label'] ?? '');
                $evidenceImages[] = $img;
            }
        }

        $capaGroupedSections = $formatter->groupConsecutiveBagian($flatRows);

        return [
            'presented' => $presented,
            'flat_rows' => $flatRows,
            'capa_grouped_sections' => $capaGroupedSections,
            'capa_evidence_pdf_images' => $evidenceImages,
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
    }

    /**
     * @param  array<string, mixed>  $presented
     * @return array<int, array{division: string, label: string, capa: array<string,mixed>}>
     */
    private function collectCapaByDivisionForExport(array $presented): array
    {
        $labels = ['service' => 'Service', 'kitchen' => 'Kitchen', 'bar' => 'Bar'];
        $out = [];
        $divs = $presented['capa_divisions'] ?? null;
        if (is_array($divs)) {
            foreach (['service', 'kitchen', 'bar'] as $d) {
                if (isset($divs[$d]) && is_array($divs[$d])) {
                    $out[] = ['division' => $d, 'label' => $labels[$d], 'capa' => $divs[$d]];
                }
            }
        }
        if ($out === [] && isset($presented['capa']) && is_array($presented['capa'])) {
            $out[] = ['division' => 'service', 'label' => $labels['service'], 'capa' => $presented['capa']];
        }

        return $out;
    }

    private function capaExportBasename(int $id, string $ext): string
    {
        return 'capa-case-'.$id.'-'.now()->format('Y-m-d_His').'.'.$ext;
    }

    /**
     * @param  array<int, int>  $caseIds
     * @return array<int, list<object>>
     */
    private function loadActivitiesForExportPdf(array $caseIds, int $perCase): array
    {
        if ($caseIds === []) {
            return [];
        }

        $activitiesByCase = [];
        foreach ($caseIds as $cid) {
            $activitiesByCase[$cid] = [];
        }

        foreach ($caseIds as $cid) {
            $rows = DB::table('feedback_case_activities as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
                ->where('a.case_id', $cid)
                ->orderByDesc('a.id')
                ->limit($perCase)
                ->get([
                    'a.case_id',
                    'a.activity_type',
                    'a.from_status',
                    'a.to_status',
                    'a.note',
                    'a.created_at',
                    'u.nama_lengkap as actor_name',
                ]);

            if ($rows->isEmpty()) {
                continue;
            }

            $chronological = $rows->reverse()->values();
            $trimmed = [];
            foreach ($chronological as $row) {
                $note = (string) ($row->note ?? '');
                if ($note !== '') {
                    $note = Str::limit(preg_replace('/\s+/u', ' ', $note), 140);
                }
                $trimmed[] = (object) [
                    'case_id' => $row->case_id,
                    'activity_type' => $row->activity_type,
                    'from_status' => $row->from_status,
                    'to_status' => $row->to_status,
                    'note' => $note,
                    'created_at' => $row->created_at,
                    'actor_name' => $row->actor_name,
                ];
            }
            $activitiesByCase[$cid] = $trimmed;
        }

        return $activitiesByCase;
    }

    public function apiAddNote(Request $request, int $id)
    {
        $response = $this->handleAddNote($request, $id);

        return response()->json($response, $response['success'] ? 200 : 404);
    }

    /**
     * User login untuk default PIC di form CAPA (nama + jabatan).
     *
     * @return array{id: int, nama_lengkap: string, nama_jabatan: string|null}|null
     */
    private function capaAuthUserPayload(?object $user): ?array
    {
        if ($user === null || ! method_exists($user, 'getAuthIdentifier')) {
            return null;
        }
        $id = $user->getAuthIdentifier();
        if ($id === null) {
            return null;
        }
        $row = DB::table('users as u')
            ->leftJoin('tbl_data_jabatan as j', 'j.id_jabatan', '=', 'u.id_jabatan')
            ->where('u.id', $id)
            ->first(['u.id', 'u.nama_lengkap', 'j.nama_jabatan as nama_jabatan']);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'nama_lengkap' => (string) ($row->nama_lengkap ?? ''),
            'nama_jabatan' => $row->nama_jabatan !== null ? (string) $row->nama_jabatan : null,
        ];
    }

    private function buildPayload(Request $request): array
    {
        $query = DB::table('feedback_cases as c')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'c.id_outlet')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'c.assigned_to')
            ->leftJoin('tbl_data_jabatan as aj', 'aj.id_jabatan', '=', 'assignee.id_jabatan')
            ->select([
                'c.id',
                'c.source_type',
                'c.source_ref',
                'c.id_outlet',
                'o.nama_outlet',
                'c.author_name',
                'c.customer_contact',
                'c.meta',
                'c.topics',
                'c.event_at',
                'c.severity',
                'c.summary_id',
                'c.raw_text',
                'c.risk_score',
                'c.status',
                'c.follow_up_status',
                'c.assigned_to',
                'assignee.nama_lengkap as assigned_to_name',
                'aj.nama_jabatan as assigned_to_jabatan',
                'c.due_at',
                'c.resolved_at',
                'c.created_at',
            ]);

        $this->applyFilters($query, $request);

        $cases = $query
            ->orderByDesc('c.risk_score')
            ->orderByDesc('c.event_at')
            ->paginate(20)
            ->withQueryString();

        $cases->setCollection(
            $cases->getCollection()->map(fn ($row) => $this->presentVoiceCaseRow($row))
        );

        $caseIdsPage = collect($cases->items())->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $activities = $this->loadActivitiesMap($caseIdsPage);
        $noteCounts = $this->loadNoteCountsMap($caseIdsPage);

        if ($request->boolean('cases_only')) {
            $summary = [
                'total_cases' => 0,
                'open_cases' => 0,
                'critical_open' => 0,
                'overdue_open' => 0,
            ];
            // Harus berupa objek JSON `{}`, bukan array `[]`, agar klien mem-parse sebagai Map KPI.
            $kpis = new \stdClass;
            $trend = [];
            $picPerformance = [];
            $outletPerformance = [];
            $perfWindowDays = 0;
        } else {
            $summary = [
                'total_cases' => (int) DB::table('feedback_cases')->count(),
                'open_cases' => (int) DB::table('feedback_cases')->whereIn('status', $this->voiceCaseOpenStatuses())->count(),
                'critical_open' => (int) DB::table('feedback_cases')->whereIn('status', $this->voiceCaseOpenStatuses())->whereIn('severity', ['critical', 'severe'])->count(),
                'overdue_open' => (int) DB::table('feedback_cases')
                    ->whereIn('status', $this->voiceCaseOpenStatuses())
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now())
                    ->count(),
            ];

            $firstResponseAvgMinutes = DB::table('feedback_cases')
                ->whereNotNull('first_response_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, event_at, first_response_at)) AS avg_minutes')
                ->value('avg_minutes');
            $firstResponseMedianMinutes = $this->medianMinutesBetween('event_at', 'first_response_at');

            $resolutionAvgMinutes = DB::table('feedback_cases')
                ->whereNotNull('resolved_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, event_at, resolved_at)) AS avg_minutes')
                ->value('avg_minutes');

            $slaResolvedBase = DB::table('feedback_cases')
                ->whereNotNull('due_at')
                ->whereNotNull('resolved_at');
            $slaResolvedTotal = (int) (clone $slaResolvedBase)->count();
            $slaResolvedOnTime = (int) (clone $slaResolvedBase)
                ->whereColumn('resolved_at', '<=', 'due_at')
                ->count();
            $slaCompliancePct = $slaResolvedTotal > 0
                ? round(($slaResolvedOnTime / $slaResolvedTotal) * 100, 2)
                : null;

            $repeatBase = DB::table('feedback_cases')
                ->whereNotNull('summary_id')
                ->where('summary_id', '!=', '')
                ->where('event_at', '>=', now()->subDays(30));
            $repeatTotal = (int) (clone $repeatBase)->count();
            $repeatGrouped = (clone $repeatBase)
                ->select('summary_id')
                ->selectRaw('COUNT(*) AS cnt')
                ->groupBy('summary_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();
            $repeatCases = (int) $repeatGrouped->sum('cnt');
            $repeatIssueRatePct = $repeatTotal > 0
                ? round(($repeatCases / $repeatTotal) * 100, 2)
                : null;

            $negativeByOutlet = DB::table('feedback_cases as c')
                ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'c.id_outlet')
                ->whereIn('c.severity', ['minor', 'major', 'critical', 'mild_negative', 'negative', 'severe'])
                ->where('c.event_at', '>=', now()->subDays(30))
                ->groupBy('c.id_outlet', 'o.nama_outlet')
                ->selectRaw('c.id_outlet, o.nama_outlet, COUNT(*) as total')
                ->orderByDesc('total')
                ->limit(1)
                ->first();

            $kpis = [
                'first_response_median_minutes' => $firstResponseMedianMinutes,
                'first_response_avg_minutes' => $firstResponseAvgMinutes !== null ? (float) $firstResponseAvgMinutes : null,
                'resolution_avg_minutes' => $resolutionAvgMinutes !== null ? (float) $resolutionAvgMinutes : null,
                'sla_compliance_pct' => $slaCompliancePct,
                'repeat_issue_rate_pct' => $repeatIssueRatePct,
                'repeat_issue_window_days' => 30,
                'negative_top_outlet_30d' => $negativeByOutlet ? [
                    'id_outlet' => $negativeByOutlet->id_outlet !== null ? (int) $negativeByOutlet->id_outlet : null,
                    'nama_outlet' => (string) ($negativeByOutlet->nama_outlet ?? '-'),
                    'total' => (int) ($negativeByOutlet->total ?? 0),
                ] : null,
            ];

            $trendDays = 14;
            $trendStart = now()->subDays($trendDays - 1)->startOfDay();
            $dailyRows = DB::table('feedback_cases')
                ->selectRaw('DATE(event_at) as d')
                ->selectRaw('COUNT(*) as total_cases')
                ->selectRaw("SUM(CASE WHEN severity IN ('minor','major','critical','mild_negative','negative','severe') THEN 1 ELSE 0 END) as negative_cases")
                ->where('event_at', '>=', $trendStart)
                ->groupBy(DB::raw('DATE(event_at)'))
                ->orderBy('d')
                ->get();

            $dailyMap = [];
            foreach ($dailyRows as $row) {
                $dailyMap[(string) $row->d] = [
                    'total_cases' => (int) ($row->total_cases ?? 0),
                    'negative_cases' => (int) ($row->negative_cases ?? 0),
                ];
            }

            $trend = [];
            for ($i = $trendDays - 1; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                $trend[] = [
                    'date' => $date,
                    'total_cases' => $dailyMap[$date]['total_cases'] ?? 0,
                    'negative_cases' => $dailyMap[$date]['negative_cases'] ?? 0,
                ];
            }

            $perfWindowDays = 30;
            $perfSince = now()->subDays($perfWindowDays)->startOfDay();

            $picRows = DB::table('feedback_cases as c')
                ->leftJoin('users as u', 'u.id', '=', 'c.assigned_to')
                ->whereNotNull('c.assigned_to')
                ->where('c.event_at', '>=', $perfSince)
                ->groupBy('c.assigned_to', 'u.nama_lengkap')
                ->selectRaw('c.assigned_to')
                ->selectRaw('u.nama_lengkap as assignee_name')
                ->selectRaw('COUNT(*) as total_cases')
                ->selectRaw('SUM(CASE WHEN c.status IN ('.$this->voiceCaseStatusesSqlList($this->voiceCaseCompletedStatuses()).') THEN 1 ELSE 0 END) as resolved_cases')
                ->selectRaw('SUM(CASE WHEN c.status IN ('.$this->voiceCaseStatusesSqlList($this->voiceCaseOpenStatuses()).') THEN 1 ELSE 0 END) as open_cases')
                ->selectRaw('AVG(CASE WHEN c.first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, c.event_at, c.first_response_at) END) as avg_first_response_minutes')
                ->selectRaw('SUM(CASE WHEN c.due_at IS NOT NULL AND c.resolved_at IS NOT NULL THEN 1 ELSE 0 END) as sla_total')
                ->selectRaw('SUM(CASE WHEN c.due_at IS NOT NULL AND c.resolved_at IS NOT NULL AND c.resolved_at <= c.due_at THEN 1 ELSE 0 END) as sla_on_time')
                ->orderByDesc('resolved_cases')
                ->orderBy('avg_first_response_minutes')
                ->limit(8)
                ->get();

            $picPerformance = collect($picRows)->map(function ($row) {
                $slaTotal = (int) ($row->sla_total ?? 0);
                $slaOnTime = (int) ($row->sla_on_time ?? 0);

                return [
                    'assignee_id' => (int) ($row->assigned_to ?? 0),
                    'assignee_name' => (string) ($row->assignee_name ?? '-'),
                    'total_cases' => (int) ($row->total_cases ?? 0),
                    'resolved_cases' => (int) ($row->resolved_cases ?? 0),
                    'open_cases' => (int) ($row->open_cases ?? 0),
                    'avg_first_response_minutes' => $row->avg_first_response_minutes !== null ? round((float) $row->avg_first_response_minutes, 2) : null,
                    'sla_compliance_pct' => $slaTotal > 0 ? round(($slaOnTime / $slaTotal) * 100, 2) : null,
                ];
            })->values()->all();

            $outletRows = DB::table('feedback_cases as c')
                ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'c.id_outlet')
                ->where('c.event_at', '>=', $perfSince)
                ->groupBy('c.id_outlet', 'o.nama_outlet')
                ->selectRaw('c.id_outlet')
                ->selectRaw('o.nama_outlet as outlet_name')
                ->selectRaw('COUNT(*) as total_cases')
                ->selectRaw("SUM(CASE WHEN c.severity IN ('minor','major','critical','mild_negative','negative','severe') THEN 1 ELSE 0 END) as negative_cases")
                ->selectRaw('SUM(CASE WHEN c.status IN ('.$this->voiceCaseStatusesSqlList($this->voiceCaseCompletedStatuses()).') THEN 1 ELSE 0 END) as resolved_cases')
                ->selectRaw('SUM(CASE WHEN c.status IN ('.$this->voiceCaseStatusesSqlList($this->voiceCaseOpenStatuses()).') THEN 1 ELSE 0 END) as open_cases')
                ->selectRaw('SUM(CASE WHEN c.due_at IS NOT NULL AND c.resolved_at IS NOT NULL THEN 1 ELSE 0 END) as sla_total')
                ->selectRaw('SUM(CASE WHEN c.due_at IS NOT NULL AND c.resolved_at IS NOT NULL AND c.resolved_at <= c.due_at THEN 1 ELSE 0 END) as sla_on_time')
                ->orderByDesc('negative_cases')
                ->limit(8)
                ->get();

            $outletPerformance = collect($outletRows)->map(function ($row) {
                $totalCases = (int) ($row->total_cases ?? 0);
                $negativeCases = (int) ($row->negative_cases ?? 0);
                $slaTotal = (int) ($row->sla_total ?? 0);
                $slaOnTime = (int) ($row->sla_on_time ?? 0);

                return [
                    'id_outlet' => $row->id_outlet !== null ? (int) $row->id_outlet : null,
                    'outlet_name' => (string) ($row->outlet_name ?? '-'),
                    'total_cases' => $totalCases,
                    'negative_cases' => $negativeCases,
                    'negative_rate_pct' => $totalCases > 0 ? round(($negativeCases / $totalCases) * 100, 2) : null,
                    'resolved_cases' => (int) ($row->resolved_cases ?? 0),
                    'open_cases' => (int) ($row->open_cases ?? 0),
                    'sla_compliance_pct' => $slaTotal > 0 ? round(($slaOnTime / $slaTotal) * 100, 2) : null,
                ];
            })->values()->all();
        }

        $outlets = Outlet::where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet']);

        $assignees = User::active()
            ->with('jabatan:id_jabatan,nama_jabatan')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'id_outlet', 'division_id', 'id_jabatan']);

        $assigneesForUi = $assignees->map(fn ($u) => [
            'id' => (int) $u->id,
            'nama_lengkap' => (string) ($u->nama_lengkap ?? ''),
            'nama_jabatan' => $u->jabatan !== null ? (string) ($u->jabatan->nama_jabatan ?? '') : null,
        ])->values()->all();

        return [
            'summary' => $summary,
            'kpis' => $kpis,
            'trend' => $trend,
            'picPerformance' => $picPerformance,
            'outletPerformance' => $outletPerformance,
            'perfWindowDays' => $perfWindowDays,
            'cases' => $cases,
            'outlets' => $outlets,
            'assignees' => $assigneesForUi,
            'capa_auth_user' => $this->capaAuthUserPayload($request->user()),
            'activities' => $activities,
            'note_counts' => $noteCounts,
            'filters' => [
                'status' => $request->input('status'),
                'severity' => $request->input('severity'),
                'source_type' => $request->input('source_type'),
                'id_outlet' => $request->input('id_outlet'),
                'q' => $request->input('q'),
                'overdue_only' => $request->boolean('overdue_only'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'show_all' => $request->boolean('show_all'),
            ],
        ];
    }

    private function applyFilters($query, Request $request): void
    {
        /** Mode antrian default: belum selesai + perlu perhatian (bukan positif/netral). */
        if (! $request->boolean('show_all')) {
            $query->whereIn('c.status', $this->voiceCaseOpenStatuses())
                ->whereNotIn('c.severity', ['positive', 'neutral']);
        }

        if ($request->filled('status')) {
            $query->whereIn('c.status', $this->voiceCaseStatusFilterValues((string) $request->input('status')));
        }
        if ($request->filled('severity')) {
            $query->where('c.severity', $request->input('severity'));
        }
        if ($request->filled('source_type')) {
            $query->where('c.source_type', $request->input('source_type'));
        }
        if ($request->filled('id_outlet')) {
            $query->where('c.id_outlet', (int) $request->input('id_outlet'));
        }
        if ($request->filled('q')) {
            $keyword = '%'.trim((string) $request->input('q')).'%';
            $query->where(function ($q) use ($keyword) {
                $q->where('c.author_name', 'like', $keyword)
                    ->orWhere('c.customer_contact', 'like', $keyword)
                    ->orWhere('c.summary_id', 'like', $keyword)
                    ->orWhere('c.raw_text', 'like', $keyword)
                    ->orWhere('o.nama_outlet', 'like', $keyword)
                    ->orWhere('c.meta', 'like', $keyword);
            });
        }
        if ($request->boolean('overdue_only')) {
            $query->whereIn('c.status', $this->voiceCaseOpenStatuses())
                ->whereNotNull('c.due_at')
                ->where('c.due_at', '<', now());
        }
        if ($request->filled('date_from')) {
            $query->whereDate('c.event_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('c.event_at', '<=', $request->input('date_to'));
        }
    }

    /**
     * Filter opsional untuk daftar arsip (selesai / positif).
     */
    private function applyArchiveListFilters($query, Request $request): void
    {
        if ($request->filled('id_outlet')) {
            $query->where('c.id_outlet', (int) $request->input('id_outlet'));
        }
        if ($request->filled('status')) {
            $query->whereIn('c.status', $this->voiceCaseStatusFilterValues((string) $request->input('status')));
        }
        if ($request->filled('severity')) {
            $query->where('c.severity', $request->input('severity'));
        }
        if ($request->filled('source_type')) {
            $query->where('c.source_type', $request->input('source_type'));
        }
        if ($request->filled('assigned_to')) {
            $query->where('c.assigned_to', (int) $request->input('assigned_to'));
        }
        if ($request->filled('topic')) {
            $topic = strtolower(trim((string) $request->input('topic')));
            if ($topic !== '' && strlen($topic) <= 64 && preg_match('/^[a-z0-9_]+$/', $topic)) {
                $query->where('c.topics', 'like', '%'.$topic.'%');
            }
        }
        if ($request->boolean('overdue_only')) {
            $query->whereIn('c.status', $this->voiceCaseOpenStatuses())
                ->whereNotNull('c.due_at')
                ->where('c.due_at', '<', now());
        }
        if ($request->filled('q')) {
            $keyword = '%'.trim((string) $request->input('q')).'%';
            $query->where(function ($q) use ($keyword) {
                $q->where('c.author_name', 'like', $keyword)
                    ->orWhere('c.customer_contact', 'like', $keyword)
                    ->orWhere('c.summary_id', 'like', $keyword)
                    ->orWhere('c.raw_text', 'like', $keyword)
                    ->orWhere('o.nama_outlet', 'like', $keyword)
                    ->orWhere('c.meta', 'like', $keyword);
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('c.event_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('c.event_at', '<=', $request->input('date_to'));
        }
    }

    private function redirectToVoiceIndex(Request $request): RedirectResponse
    {
        return redirect()->route('customer-voice-command-center.index', $this->voiceIndexFiltersFromRequest($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function voiceIndexFiltersFromRequest(Request $request): array
    {
        $params = [];
        foreach (['q', 'severity', 'source_type', 'id_outlet', 'page', 'date_from', 'date_to'] as $key) {
            $val = $request->input($key);
            if ($val !== null && $val !== '') {
                $params[$key] = $val;
            }
        }

        // Setelah POST simpan case, body punya `status` = status baris; filter tabel dikirim sebagai `list_status`.
        // Pakai exists() bukan has(), agar filter "semua status" (string kosong) tetap dikenali dan tidak jatuh ke `status` case.
        if ($request->exists('list_status')) {
            $filterStatus = trim((string) $request->input('list_status', ''));
            if ($filterStatus !== '') {
                $params['status'] = $filterStatus;
            }
        } elseif ($request->filled('status')) {
            $params['status'] = $request->input('status');
        }

        if ($request->boolean('overdue_only')) {
            $params['overdue_only'] = 1;
        }

        if ($request->boolean('show_all')) {
            $params['show_all'] = 1;
        }

        return $params;
    }

    /**
     * Jumlah aktivitas bertipe `note` per case (untuk badge di UI).
     *
     * @param  array<int, int>  $caseIds
     * @return array<int, int>
     */
    private function loadNoteCountsMap(array $caseIds): array
    {
        if ($caseIds === []) {
            return [];
        }

        $counts = array_fill_keys($caseIds, 0);

        $rows = DB::table('feedback_case_activities')
            ->select('case_id', DB::raw('COUNT(*) as cnt'))
            ->whereIn('case_id', $caseIds)
            ->where('activity_type', 'note')
            ->groupBy('case_id')
            ->get();

        foreach ($rows as $row) {
            $cid = (int) $row->case_id;
            if (array_key_exists($cid, $counts)) {
                $counts[$cid] = (int) ($row->cnt ?? 0);
            }
        }

        return $counts;
    }

    private function loadActivitiesMap(array $caseIds): array
    {
        $activities = [];
        if ($caseIds === []) {
            return $activities;
        }

        $activityRows = DB::table('feedback_case_activities as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
            ->whereIn('a.case_id', $caseIds)
            ->orderByDesc('a.id')
            ->get([
                'a.id',
                'a.case_id',
                'a.activity_type',
                'a.from_status',
                'a.to_status',
                'a.note',
                'a.created_at',
                'u.nama_lengkap as actor_name',
            ]);

        foreach ($activityRows as $row) {
            $caseId = (int) $row->case_id;
            if (! isset($activities[$caseId])) {
                $activities[$caseId] = [];
            }
            if (count($activities[$caseId]) < 8) {
                $activities[$caseId][] = $row;
            }
        }

        return $activities;
    }

    /**
     * Gabungkan kolom DB + isi meta JSON untuk tampilan Customer Voice (FU target, dampak, email opsional).
     *
     * @param  object  $case  Baris dari query dengan field meta.
     * @return array<string, mixed>
     */
    private function presentVoiceCaseRow(object $case): array
    {
        $meta = [];
        if (! empty($case->meta)) {
            $decoded = json_decode((string) $case->meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        $followUp = strtolower(trim((string) ($meta['follow_up_target'] ?? '')));
        $followUp = in_array($followUp, ['customer', 'internal'], true) ? $followUp : null;

        $impact = [];
        if (isset($meta['impact']) && is_array($meta['impact'])) {
            foreach ($meta['impact'] as $x) {
                $impact[] = strtolower(trim((string) $x));
            }
        }

        $email = null;
        if (isset($meta['customer_email'])) {
            $e = trim((string) $meta['customer_email']);
            $email = $e !== '' ? $e : null;
        }

        $notifyFollowerIds = [];
        if (isset($meta['notify_follower_user_ids']) && is_array($meta['notify_follower_user_ids'])) {
            foreach ($meta['notify_follower_user_ids'] as $x) {
                $n = (int) $x;
                if ($n > 0) {
                    $notifyFollowerIds[$n] = $n;
                }
            }
            $notifyFollowerIds = array_values($notifyFollowerIds);
        }

        $regionalUserIds = [];
        if (isset($meta['regional_user_ids']) && is_array($meta['regional_user_ids'])) {
            foreach ($meta['regional_user_ids'] as $x) {
                $n = (int) $x;
                if ($n > 0) {
                    $regionalUserIds[$n] = $n;
                }
            }
            $regionalUserIds = array_values($regionalUserIds);
        }

        $topicsArr = [];
        if (isset($case->topics)) {
            $rawT = $case->topics;
            if (is_string($rawT) && $rawT !== '') {
                $decoded = json_decode($rawT, true);
                $topicsArr = is_array($decoded) ? $decoded : [];
            } elseif (is_array($rawT)) {
                $topicsArr = $rawT;
            }
        }

        $capaDivisions = $this->normalizeCapaDivisionsFromMeta($meta);
        $activeDivision = $this->normalizeCapaDivision($meta['capa_active_division'] ?? null);
        $storedCapa = $capaDivisions[$activeDivision] ?? null;

        $complaintTypeLabels = $this->voiceComplaintTopicLabels($topicsArr);

        $sevDb = strtolower(trim((string) ($case->severity ?? '')));

        /** @var array{service: array<string, mixed>|null, kitchen: array<string, mixed>|null, bar: array<string, mixed>|null} $capaDivisionsPresented */
        $capaDivisionsPresented = ['service' => null, 'kitchen' => null, 'bar' => null];
        foreach (['service', 'kitchen', 'bar'] as $divKey) {
            $storedDiv = $capaDivisions[$divKey] ?? null;
            if ($storedDiv === null || ! is_array($storedDiv)) {
                continue;
            }
            $presentedDiv = $this->capaService->buildForPresentation($storedDiv, $case, $topicsArr);
            $presentedDiv = $this->capaService->decorateEvidenceUrls($presentedDiv);
            if (empty($presentedDiv['h']['documented_impact']) && count($impact)) {
                $presentedDiv['h']['documented_impact'] = array_values(array_unique($impact));
            }
            if (($presentedDiv['h']['documented_severity'] ?? null) === null && in_array($sevDb, ['minor', 'major', 'critical'], true)) {
                $presentedDiv['h']['documented_severity'] = $sevDb;
            }
            $capaDivisionsPresented[$divKey] = $presentedDiv;
        }

        $capa = $capaDivisionsPresented[$activeDivision] ?? $this->capaService->buildForPresentation($storedCapa, $case, $topicsArr);
        $capa = $this->capaService->decorateEvidenceUrls($capa);
        if (empty($capa['h']['documented_impact']) && count($impact)) {
            $capa['h']['documented_impact'] = array_values(array_unique($impact));
        }
        if (($capa['h']['documented_severity'] ?? null) === null && in_array($sevDb, ['minor', 'major', 'critical'], true)) {
            $capa['h']['documented_severity'] = $sevDb;
        }

        /** Icon kolom per-divisi harus mengikuti data tersimpan di meta, bukan merge presentasi (H bisa terisi dari source). */
        $capaDivisionFilled = [
            'service' => $this->capaService->storedCapaHasUserInput($capaDivisions['service'] ?? null),
            'kitchen' => $this->capaService->storedCapaHasUserInput($capaDivisions['kitchen'] ?? null),
            'bar' => $this->capaService->storedCapaHasUserInput($capaDivisions['bar'] ?? null),
        ];
        $capaDivisionVerification = [
            'service' => $this->capaService->storedCapaVerificationState($capaDivisions['service'] ?? null),
            'kitchen' => $this->capaService->storedCapaVerificationState($capaDivisions['kitchen'] ?? null),
            'bar' => $this->capaService->storedCapaVerificationState($capaDivisions['bar'] ?? null),
        ];

        $gcfCapa = null;
        $sourceRef = (string) ($case->source_ref ?? '');
        if (str_starts_with($sourceRef, 'gcf:')) {
            $gcfId = (int) substr($sourceRef, 4);
            if ($gcfId > 0) {
                $capaRow = DB::table('guest_comment_capas')
                    ->where('guest_comment_form_id', $gcfId)
                    ->first();
                if ($capaRow) {
                    $filledByName = null;
                    if ($capaRow->filled_by) {
                        $filledByUser = DB::table('users')
                            ->where('id', $capaRow->filled_by)
                            ->first(['nama_lengkap']);
                        $filledByName = $filledByUser->nama_lengkap ?? null;
                    }
                    $gcfCapa = [
                        'kronologi' => (string) ($capaRow->kronologi ?? ''),
                        'corrective_action' => (string) ($capaRow->corrective_action ?? ''),
                        'preventive_action' => (string) ($capaRow->preventive_action ?? ''),
                        'filled_by_name' => $filledByName,
                        'filled_at' => $capaRow->filled_at,
                    ];
                }
            }
        }

        return [
            'id' => (int) $case->id,
            'source_type' => (string) ($case->source_type ?? ''),
            'source_ref' => $sourceRef,
            'id_outlet' => $case->id_outlet !== null ? (int) $case->id_outlet : null,
            'nama_outlet' => (string) ($case->nama_outlet ?? ''),
            'author_name' => $case->author_name !== null ? (string) $case->author_name : null,
            'customer_contact' => $case->customer_contact !== null ? (string) $case->customer_contact : null,
            'customer_email' => $email,
            'event_at' => $case->event_at,
            'severity' => (string) ($case->severity ?? ''),
            'summary_id' => $case->summary_id !== null ? (string) $case->summary_id : null,
            'raw_text' => $case->raw_text !== null ? (string) $case->raw_text : null,
            'risk_score' => (int) ($case->risk_score ?? 0),
            'status' => (string) ($case->status ?? ''),
            'follow_up_status' => (string) ($case->follow_up_status ?? 'new'),
            'assigned_to' => $case->assigned_to !== null ? (int) $case->assigned_to : null,
            'assigned_to_name' => $case->assigned_to_name !== null ? (string) $case->assigned_to_name : null,
            'assigned_to_jabatan' => isset($case->assigned_to_jabatan) && $case->assigned_to_jabatan !== null && $case->assigned_to_jabatan !== ''
                ? (string) $case->assigned_to_jabatan
                : null,
            'due_at' => $case->due_at,
            'resolved_at' => $case->resolved_at,
            'created_at' => $case->created_at,
            'follow_up_target' => $followUp,
            'impact' => $impact,
            'topics' => $topicsArr,
            'complaint_type_labels' => $complaintTypeLabels,
            'notify_follower_user_ids' => $notifyFollowerIds,
            'regional_user_ids' => $regionalUserIds,
            'capa_filled' => $this->capaService->storedCapaHasUserInput($storedCapa),
            'capa_verification' => $this->capaService->storedCapaVerificationState($storedCapa),
            'capa_audit' => [
                'updated_by_user_id' => $this->extractCapaAuditUserId($meta, 'updated_by_user_id'),
                'updated_at' => $this->extractCapaAuditDateTime($meta, 'updated_at'),
                'verified_by_user_id' => $this->extractCapaAuditUserId($meta, 'verified_by_user_id'),
                'verified_at' => $this->extractCapaAuditDateTime($meta, 'verified_at'),
            ],
            'capa_active_division' => $activeDivision,
            'capa_divisions' => $capaDivisionsPresented,
            'capa_division_filled' => $capaDivisionFilled,
            'capa_division_verification' => $capaDivisionVerification,
            'capa' => $capa,
            'gcf_capa' => $gcfCapa,
        ];
    }

    /**
     * Label bahasa Indonesia untuk jenis komplain (dari kolom topics / klasifikasi AI).
     *
     * @param  array<int, string>  $topics
     * @return array<int, string>
     */
    private function voiceComplaintTopicLabels(array $topics): array
    {
        $map = [
            'food_quality' => 'Kualitas makanan',
            'service' => 'Layanan',
            'hygiene' => 'Kebersihan',
            'cleanliness' => 'Kebersihan',
            'ambiance' => 'Suasana',
            'price' => 'Harga / nilai',
            'price_value' => 'Harga / nilai',
            'billing' => 'Tagihan',
            'wait_time' => 'Waktu tunggu',
            'waiting_time' => 'Waktu tunggu',
            'speed_wait_time' => 'Waktu tunggu',
            'parking' => 'Parkir',
            'portion' => 'Porsi',
            'noise' => 'Kebisingan',
            'reservation' => 'Reservasi',
            'beverage' => 'Minuman',
            'staff_attitude' => 'Sikap staf',
            'other' => 'Lainnya',
        ];

        $seenLabel = [];
        $out = [];
        foreach ($topics as $t) {
            $k = strtolower(trim((string) $t));
            if ($k === '') {
                continue;
            }
            $label = $map[$k] ?? ucfirst(str_replace('_', ' ', $k));
            if (isset($seenLabel[$label])) {
                continue;
            }
            $seenLabel[$label] = true;
            $out[] = $label;
        }

        return $out;
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function runFeedbackCaseRowUpdate(Request $request, int $id): array
    {
        $payload = $request->validate([
            'status' => 'required|string|in:new,internal_follow_up,courtesy_done',
            'follow_up_status' => 'nullable|string|in:new,on_progress,done',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'notify_follower_user_ids' => 'nullable|array|max:30',
            'notify_follower_user_ids.*' => 'integer|exists:users,id',
            'regional_user_ids' => 'nullable|array|max:30',
            'regional_user_ids.*' => 'integer|exists:users,id',
            'case_severity' => 'nullable|string|in:critical,major,minor,neutral,positive,severe,negative,mild_negative',
            'follow_up_target' => 'nullable|string|in:customer,internal',
            'case_topics' => 'nullable|array|max:15',
            'case_topics.*' => 'string|max:64',
        ]);

        $row = DB::table('feedback_cases')->where('id', $id)->first();
        if (! $row) {
            return [
                'success' => false,
                'message' => 'Case tidak ditemukan.',
            ];
        }

        $now = now();
        $fromStatus = (string) ($row->status ?? 'new');
        $toStatus = $this->normalizeIncomingVoiceCaseStatus((string) $payload['status']);
        $oldAssignee = $row->assigned_to !== null ? (int) $row->assigned_to : null;
        $newAssignee = isset($payload['assigned_to']) && $payload['assigned_to'] !== null
            ? (int) $payload['assigned_to']
            : null;

        $update = [
            'status' => $toStatus,
            'assigned_to' => $newAssignee,
            'updated_at' => $now,
        ];
        if ($toStatus === 'courtesy_done') {
            $update['resolved_at'] = $now;
            if ($row->first_response_at === null) {
                $update['first_response_at'] = $now;
            }
        } else {
            $update['resolved_at'] = null;
            if ($toStatus !== 'new' && $row->first_response_at === null) {
                $update['first_response_at'] = $now;
            }
        }

        if (array_key_exists('follow_up_status', $payload)) {
            $update['follow_up_status'] = $payload['follow_up_status'];
        }

        $oldSeverity = strtolower(trim((string) ($row->severity ?? '')));
        $newSeverity = null;
        if (array_key_exists('case_severity', $payload) && $payload['case_severity'] !== null) {
            $newSeverity = $this->normalizeIncomingSeverity((string) $payload['case_severity']);
            if ($newSeverity !== $oldSeverity) {
                $update['severity'] = $newSeverity;
                $slaMinutes = $this->severityToSlaMinutes($newSeverity);
                $update['sla_minutes'] = $slaMinutes;
                $update['due_at'] = $slaMinutes !== null && $row->event_at !== null
                    ? \Carbon\Carbon::parse($row->event_at)->addMinutes($slaMinutes)
                    : null;
            }
        }

        if (array_key_exists('case_topics', $payload)) {
            $validTopics = array_values(array_filter(
                array_map(fn ($t) => strtolower(trim((string) $t)), $payload['case_topics'] ?? []),
                fn ($t) => $t !== ''
            ));
            $update['topics'] = ! empty($validTopics) ? json_encode($validTopics, JSON_UNESCAPED_UNICODE) : null;
        }

        DB::transaction(function () use ($id, $update, $request, $fromStatus, $toStatus, $oldAssignee, $newAssignee, $oldSeverity, $newSeverity, $now) {
            DB::table('feedback_cases')->where('id', $id)->update($update);

            if ($fromStatus !== $toStatus) {
                DB::table('feedback_case_activities')->insert([
                    'case_id' => $id,
                    'activity_type' => 'status_changed',
                    'actor_user_id' => $request->user()->id ?? null,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'note' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($oldAssignee !== $newAssignee) {
                DB::table('feedback_case_activities')->insert([
                    'case_id' => $id,
                    'activity_type' => 'assigned',
                    'actor_user_id' => $request->user()->id ?? null,
                    'from_status' => null,
                    'to_status' => null,
                    'note' => 'Assign PIC: '.($newAssignee ?? 'unassigned'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($newSeverity !== null && $newSeverity !== $oldSeverity) {
                DB::table('feedback_case_activities')->insert([
                    'case_id' => $id,
                    'activity_type' => 'severity_changed',
                    'actor_user_id' => $request->user()->id ?? null,
                    'from_status' => null,
                    'to_status' => null,
                    'note' => 'Severity: '.$oldSeverity.' → '.$newSeverity,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        if (array_key_exists('notify_follower_user_ids', $payload)) {
            $notifyRaw = $payload['notify_follower_user_ids'];
            $this->persistNotifyFollowerMetaAndNotify($request, $id, is_array($notifyRaw) ? $notifyRaw : []);
        }

        if (array_key_exists('regional_user_ids', $payload)) {
            $regionalRaw = $payload['regional_user_ids'];
            $this->persistRegionalUsersMetaAndNotify($request, $id, is_array($regionalRaw) ? $regionalRaw : []);
        }

        if (array_key_exists('follow_up_target', $payload)) {
            $this->persistFollowUpTargetMeta($id, $payload['follow_up_target']);
        }

        return [
            'success' => true,
            'message' => 'Case diperbarui.',
        ];
    }

    private function handleUpdateCase(Request $request, int $id): array
    {
        return $this->runFeedbackCaseRowUpdate($request, $id);
    }

    /**
     * @param  array<int, mixed>  $rawIds
     */
    private function persistNotifyFollowerMetaAndNotify(Request $request, int $caseId, array $rawIds): void
    {
        $ids = [];
        foreach ($rawIds as $x) {
            $n = (int) $x;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }
        $ids = array_slice(array_values($ids), 0, 30);

        $fresh = DB::table('feedback_cases')->where('id', $caseId)->first();
        if ($fresh === null) {
            return;
        }

        $meta = [];
        if (! empty($fresh->meta)) {
            $meta = json_decode((string) $fresh->meta, true) ?: [];
        }
        $meta['notify_follower_user_ids'] = $ids;

        DB::table('feedback_cases')->where('id', $caseId)->update([
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        if ($ids === []) {
            return;
        }

        $actorId = (int) ($request->user()->id ?? 0);
        $summary = Str::limit(trim((string) ($fresh->summary_id ?? '')), 120);
        $url = url('/customer-voice-command-center?show_all=1&open_case='.$caseId);
        $lines = [];
        foreach ($ids as $uid) {
            if ($uid === $actorId) {
                continue;
            }
            $lines[] = [
                'user_id' => $uid,
                'type' => 'customer_voice_case_follower',
                'title' => 'Customer Voice — pembaruan kasus',
                'message' => 'Kasus #'.$caseId.' diperbarui'.($summary !== '' ? ': '.$summary : '.'),
                'url' => $url,
                'is_read' => 0,
            ];
        }
        if ($lines !== []) {
            NotificationService::createMany($lines);
        }
    }

    /**
     * @param  array<int, mixed>  $rawIds
     */
    private function persistRegionalUsersMetaAndNotify(Request $request, int $caseId, array $rawIds): void
    {
        $ids = [];
        foreach ($rawIds as $x) {
            $n = (int) $x;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }
        $ids = array_slice(array_values($ids), 0, 30);

        $fresh = DB::table('feedback_cases')->where('id', $caseId)->first();
        if ($fresh === null) {
            return;
        }

        $meta = [];
        if (! empty($fresh->meta)) {
            $meta = json_decode((string) $fresh->meta, true) ?: [];
        }
        $meta['regional_user_ids'] = $ids;

        DB::table('feedback_cases')->where('id', $caseId)->update([
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        if ($ids === []) {
            return;
        }

        $actorId = (int) ($request->user()->id ?? 0);
        $summary = Str::limit(trim((string) ($fresh->summary_id ?? '')), 120);
        $url = url('/customer-voice-command-center?show_all=1&open_case='.$caseId);
        $lines = [];
        foreach ($ids as $uid) {
            if ($uid === $actorId) {
                continue;
            }
            $lines[] = [
                'user_id' => $uid,
                'type' => 'customer_voice_regional_fu_capa',
                'title' => 'Customer Voice — tindak lanjut regional',
                'message' => 'PIC meminta Anda mendukung follow-up dan pengisian CAPA untuk kasus #'.$caseId
                    .($summary !== '' ? ': '.$summary : '.'),
                'url' => $url,
                'is_read' => 0,
            ];
        }
        if ($lines !== []) {
            NotificationService::createMany($lines);
        }
    }

    private function severityToSlaMinutes(string $severity): ?int
    {
        return match ($severity) {
            'critical', 'severe' => 30,
            'major', 'negative' => 120,
            'minor', 'mild_negative' => 1440,
            default => null,
        };
    }

    private function normalizeIncomingSeverity(string $severity): string
    {
        return match ($severity) {
            'severe' => 'critical',
            'negative' => 'major',
            'mild_negative' => 'minor',
            default => $severity,
        };
    }

    private function persistFollowUpTargetMeta(int $caseId, ?string $followUpTarget): void
    {
        $row = DB::table('feedback_cases')->where('id', $caseId)->first();
        if ($row === null) {
            return;
        }

        $meta = [];
        if (! empty($row->meta)) {
            $meta = json_decode((string) $row->meta, true) ?: [];
        }

        if ($followUpTarget !== null && in_array($followUpTarget, ['customer', 'internal'], true)) {
            $meta['follow_up_target'] = $followUpTarget;
        } else {
            unset($meta['follow_up_target']);
        }

        DB::table('feedback_cases')->where('id', $caseId)->update([
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    private function extractCapaVerifierUserId(array $meta): ?int
    {
        $division = $this->normalizeCapaDivision($meta['capa_active_division'] ?? null);
        $capa = $this->getDivisionCapaFromMeta($meta, $division);
        if (! is_array($capa)) {
            return null;
        }
        $g = $capa['g'] ?? null;
        if (! is_array($g) || ! isset($g['verified_by_user_id'])) {
            return null;
        }
        $v = $g['verified_by_user_id'];
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    private function extractCapaVerificationResult(array $meta): ?string
    {
        $division = $this->normalizeCapaDivision($meta['capa_active_division'] ?? null);
        $capa = $this->getDivisionCapaFromMeta($meta, $division);
        if (! is_array($capa)) {
            return null;
        }
        $g = $capa['g'] ?? null;
        if (! is_array($g)) {
            return null;
        }
        $raw = strtolower(trim((string) ($g['result'] ?? '')));

        return in_array($raw, ['effective', 'not_effective'], true) ? $raw : null;
    }

    private function normalizeCapaDivision(mixed $raw): string
    {
        $v = strtolower(trim((string) ($raw ?? '')));
        if (! in_array($v, ['service', 'kitchen', 'bar'], true)) {
            return 'service';
        }

        return $v;
    }

    /**
     * @return array{service: array<string,mixed>|null, kitchen: array<string,mixed>|null, bar: array<string,mixed>|null}
     */
    private function normalizeCapaDivisionsFromMeta(array $meta): array
    {
        $out = ['service' => null, 'kitchen' => null, 'bar' => null];
        $divs = $meta['capa_divisions'] ?? null;
        if (is_array($divs)) {
            foreach (['service', 'kitchen', 'bar'] as $k) {
                if (isset($divs[$k]) && is_array($divs[$k])) {
                    try {
                        $out[$k] = $this->capaService->sanitizeCapa($divs[$k]);
                    } catch (\Throwable) {
                        $out[$k] = null;
                    }
                }
            }
        }
        // Backward compatibility: old single CAPA is treated as service.
        if ($out['service'] === null && isset($meta['capa']) && is_array($meta['capa'])) {
            try {
                $out['service'] = $this->capaService->sanitizeCapa($meta['capa']);
            } catch (\Throwable) {
                $out['service'] = null;
            }
        }

        return $out;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getDivisionCapaFromMeta(array $meta, string $division): ?array
    {
        $divs = $this->normalizeCapaDivisionsFromMeta($meta);

        return $divs[$division] ?? null;
    }

    private function mergeDivisionCapaIntoMeta(array $meta, string $division, array $sanitized): array
    {
        $divs = $this->normalizeCapaDivisionsFromMeta($meta);
        $divs[$division] = $sanitized;
        $meta['capa_divisions'] = $divs;
        $meta['capa_active_division'] = $division;
        // Keep old key for compatibility with old readers/exporters.
        $meta['capa'] = $sanitized;

        return $meta;
    }

    private function stampCapaAuditMeta(array $meta, mixed $actorIdRaw, \Carbon\Carbon $now, ?string $verificationResult): array
    {
        $actorId = (int) $actorIdRaw;
        $capaMeta = isset($meta['capa_meta']) && is_array($meta['capa_meta']) ? $meta['capa_meta'] : [];
        $capaMeta['updated_at'] = $now->format('Y-m-d H:i:s');
        $capaMeta['updated_by_user_id'] = $actorId > 0 ? $actorId : null;

        if ($verificationResult !== null) {
            $verifiedBy = $this->extractCapaVerifierUserId($meta);
            if ($verifiedBy === null || $verifiedBy <= 0) {
                $verifiedBy = $actorId > 0 ? $actorId : null;
            }
            $capaMeta['verified_at'] = $now->format('Y-m-d H:i:s');
            $capaMeta['verified_by_user_id'] = $verifiedBy;
        } else {
            $capaMeta['verified_at'] = null;
            $capaMeta['verified_by_user_id'] = null;
        }

        $meta['capa_meta'] = $capaMeta;

        return $meta;
    }

    private function extractCapaAuditUserId(array $meta, string $key): ?int
    {
        $capaMeta = $meta['capa_meta'] ?? null;
        if (! is_array($capaMeta)) {
            return null;
        }
        $v = $capaMeta[$key] ?? null;
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    private function extractCapaAuditDateTime(array $meta, string $key): ?string
    {
        $capaMeta = $meta['capa_meta'] ?? null;
        if (! is_array($capaMeta)) {
            return null;
        }
        $v = trim((string) ($capaMeta[$key] ?? ''));

        return $v !== '' ? $v : null;
    }

    private function notifyCapaVerifierIfNew(Request $request, int $caseId, ?int $oldVerifierId, ?int $newVerifierId, mixed $summaryId): void
    {
        if ($newVerifierId === null || $newVerifierId <= 0) {
            return;
        }
        if ($oldVerifierId !== null && $oldVerifierId === $newVerifierId) {
            return;
        }

        $actorId = (int) ($request->user()->id ?? 0);
        if ($newVerifierId === $actorId) {
            return;
        }

        $summary = Str::limit(trim((string) ($summaryId ?? '')), 120);
        NotificationService::create([
            'user_id' => $newVerifierId,
            'type' => 'customer_voice_capa_verification_request',
            'title' => 'CAPA — verifikasi diperlukan',
            'message' => 'Anda ditunjuk sebagai verifikator untuk kasus #'.$caseId.($summary !== '' ? ': '.$summary : '.'),
            'url' => url('/customer-voice-command-center?show_all=1&open_case='.$caseId),
        ]);
    }

    private function handleAddNote(Request $request, int $id): array
    {
        $payload = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $exists = DB::table('feedback_cases')->where('id', $id)->exists();
        if (! $exists) {
            return [
                'success' => false,
                'message' => 'Case tidak ditemukan.',
            ];
        }

        DB::table('feedback_case_activities')->insert([
            'case_id' => $id,
            'activity_type' => 'note',
            'actor_user_id' => $request->user()->id ?? null,
            'from_status' => null,
            'to_status' => null,
            'note' => trim((string) $payload['note']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Catatan tersimpan.',
        ];
    }

    /**
     * Status antrian (belum selesai). Legacy values ditangani oleh voiceCaseStatusFilterValues().
     *
     * @return list<string>
     */
    private function voiceCaseOpenStatuses(): array
    {
        return ['new', 'internal_follow_up'];
    }

    /**
     * Status selesai untuk KPI / arsip.
     *
     * @return list<string>
     */
    private function voiceCaseCompletedStatuses(): array
    {
        return ['courtesy_done'];
    }

    private function normalizeIncomingVoiceCaseStatus(string $status): string
    {
        return match ($status) {
            'courtesy_by_cs', 'follow_up_by_ops', 'in_progress' => 'internal_follow_up',
            'done', 'resolved', 'ignored' => 'courtesy_done',
            default => $status,
        };
    }

    /**
     * @param  list<string>  $statuses
     */
    private function voiceCaseStatusesSqlList(array $statuses): string
    {
        return implode(',', array_map(
            static fn (string $s): string => "'".str_replace("'", "''", $s)."'",
            $statuses
        ));
    }

    /**
     * Satu pilihan filter UI dapat mencakup beberapa nilai DB (data lama + baru, tanpa migrasi).
     *
     * @return list<string>
     */
    private function voiceCaseStatusFilterValues(string $filter): array
    {
        $filter = trim($filter);

        return match ($filter) {
            'internal_follow_up' => ['internal_follow_up', 'courtesy_by_cs', 'follow_up_by_ops', 'in_progress'],
            'courtesy_done' => ['courtesy_done', 'done', 'resolved', 'ignored'],
            default => [$filter],
        };
    }

    private function medianMinutesBetween(string $startColumn, string $endColumn): ?float
    {
        $rows = DB::table('feedback_cases')
            ->whereNotNull($startColumn)
            ->whereNotNull($endColumn)
            ->selectRaw("TIMESTAMPDIFF(MINUTE, {$startColumn}, {$endColumn}) as v")
            ->whereRaw("TIMESTAMPDIFF(MINUTE, {$startColumn}, {$endColumn}) >= 0")
            ->orderBy('v')
            ->pluck('v')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $count = count($rows);
        if ($count === 0) {
            return null;
        }
        $mid = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float) $rows[$mid];
        }

        return round((($rows[$mid - 1] + $rows[$mid]) / 2), 2);
    }
}
