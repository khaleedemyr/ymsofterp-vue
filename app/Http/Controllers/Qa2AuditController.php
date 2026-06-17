<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class Qa2AuditController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;

        $search = $request->input('search');
        $status = $request->input('status');
        $outletId = $request->input('outlet_id');

        $query = DB::table('qa2_audits as a')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'a.outlet_id')
            ->leftJoin('qa2_templates as t', 't.id', '=', 'a.template_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.created_by')
            ->select([
                'a.id',
                'a.audit_number',
                'a.audit_datetime',
                'a.status',
                'a.outlet_id',
                'a.audit_time_start',
                'a.audit_time_end',
                'o.nama_outlet as outlet_name',
                't.name as template_name',
                'u.nama_lengkap as created_by_name',
            ])
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'C') as count_c")
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NC') as count_nc")
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NA') as count_na")
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NC' and not exists (select 1 from qa2_audit_caps c where c.audit_item_id = i.id and c.action_plan is not null and c.action_plan <> '')) as count_nc_pending_cap")
            ->orderByDesc('a.id');

        if (!$isHo) {
            $query->where('a.outlet_id', (int) $user->id_outlet);
        }

        if ($outletId) {
            $query->where('a.outlet_id', (int) $outletId);
        }

        if ($status && in_array($status, ['draft', 'submitted'], true)) {
            $query->where('a.status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('a.audit_number', 'like', "%{$search}%")
                    ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                    ->orWhere('t.name', 'like', "%{$search}%")
                    ->orWhere('u.nama_lengkap', 'like', "%{$search}%")
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->from('qa2_audit_auditors as aa')
                            ->join('users as au', 'au.id', '=', 'aa.user_id')
                            ->leftJoin('tbl_data_jabatan as aj', 'aj.id_jabatan', '=', 'au.id_jabatan')
                            ->whereColumn('aa.audit_id', 'a.id')
                            ->where(function ($people) use ($search) {
                                $people->where('au.nama_lengkap', 'like', "%{$search}%")
                                    ->orWhere('aj.nama_jabatan', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->from('qa2_audit_auditees as ae')
                            ->join('users as au', 'au.id', '=', 'ae.user_id')
                            ->leftJoin('tbl_data_jabatan as aj', 'aj.id_jabatan', '=', 'au.id_jabatan')
                            ->whereColumn('ae.audit_id', 'a.id')
                            ->where(function ($people) use ($search) {
                                $people->where('au.nama_lengkap', 'like', "%{$search}%")
                                    ->orWhere('aj.nama_jabatan', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $audits = $query->paginate(15)->withQueryString();
        $this->attachAuditPeople($audits);

        $statsQuery = DB::table('qa2_audits');
        if (!$isHo) {
            $statsQuery->where('outlet_id', (int) $user->id_outlet);
        }

        $statistics = [
            'total' => (clone $statsQuery)->count(),
            'draft' => (clone $statsQuery)->where('status', 'draft')->count(),
            'submitted' => (clone $statsQuery)->where('status', 'submitted')->count(),
        ];

        $outlets = $this->allowedOutlets($isHo, (int) $user->id_outlet);

        return Inertia::render('Qa2Audits/Index', [
            'audits' => $audits,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'outlet_id' => $outletId,
            ],
            'statistics' => $statistics,
            'outlets' => $outlets,
            'permissions' => [
                'can_manage' => $isHo,
            ],
        ]);
    }

    public function create()
    {
        $this->ensureHo();

        $user = auth()->user();

        $templates = DB::table('qa2_templates')
            ->where('status', 'A')
            ->whereExists(function ($q) {
                $q->from('qa2_template_items as ti')
                    ->selectRaw('1')
                    ->whereColumn('ti.template_id', 'qa2_templates.id');
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Qa2Audits/Form', [
            'mode' => 'create',
            'audit' => null,
            'outlets' => $this->allowedOutlets(true, (int) $user->id_outlet),
            'users' => $this->usersForSelector(),
            'templates' => $templates,
            'tree' => [],
            'permissions' => [
                'can_manage' => true,
                'can_fill_cap' => false,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureHo();

        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'template_id' => 'required|exists:qa2_templates,id',
            'auditor_ids' => 'nullable|array',
            'auditor_ids.*' => 'integer|exists:users,id',
            'auditee_ids' => 'nullable|array',
            'auditee_ids.*' => 'integer|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $user = auth()->user();

        // Tarik dulu data template dan pastikan ada item sebelum membuat draft audit.
        $templateRows = $this->getTemplateSeedRows((int) $validated['template_id']);
        if (empty($templateRows)) {
            throw ValidationException::withMessages([
                'template_id' => 'Template tidak memiliki parameter audit. Cek QA2 Template Items.',
            ]);
        }

        $auditId = DB::transaction(function () use ($validated, $user, $templateRows) {
            $auditId = DB::table('qa2_audits')->insertGetId([
                'audit_number' => $this->generateAuditNumber(),
                'audit_datetime' => now(),
                'outlet_id' => (int) $validated['outlet_id'],
                'template_id' => (int) $validated['template_id'],
                'created_by' => (int) $user->id,
                'audit_time_start' => now(),
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncPeople($auditId, $validated['auditor_ids'] ?? [], $validated['auditee_ids'] ?? []);
            $this->seedAuditItemsFromTemplateRows($auditId, $templateRows);

            return $auditId;
        });

        return redirect()->route('qa2-audits.edit', $auditId)->with('success', 'QA Audit draft berhasil dibuat.');
    }

    public function edit(int $id)
    {
        $audit = $this->getAuditRow($id);

        abort_if(!$audit, 404);

        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;
        if (!$isHo && (int) $audit->outlet_id !== (int) $user->id_outlet) {
            abort(403);
        }

        $this->ensureAuditItems((int) $id, (int) $audit->template_id);

        $canFillCap = DB::table('qa2_audit_auditees')
            ->where('audit_id', $id)
            ->where('user_id', (int) $user->id)
            ->exists();

        return Inertia::render('Qa2Audits/Form', [
            'mode' => 'edit',
            'audit' => $this->auditPayload($id),
            'outlets' => $this->allowedOutlets($isHo, (int) $user->id_outlet),
            'users' => $this->usersForSelector(),
            'templates' => DB::table('qa2_templates')
                ->where('status', 'A')
                ->whereExists(function ($q) {
                    $q->from('qa2_template_items as ti')
                        ->selectRaw('1')
                        ->whereColumn('ti.template_id', 'qa2_templates.id');
                })
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'tree' => $this->auditTree($id),
            'permissions' => [
                'can_manage' => $isHo && $audit->status === 'draft',
                'can_fill_cap' => $canFillCap && $audit->status === 'submitted',
            ],
        ]);
    }

    public function saveDraft(Request $request, int $id)
    {
        $this->ensureHo();

        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);
        abort_if($audit->status !== 'draft', 422, 'Draft sudah disubmit.');

        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'auditor_ids' => 'nullable|array',
            'auditor_ids.*' => 'integer|exists:users,id',
            'auditee_ids' => 'nullable|array',
            'auditee_ids.*' => 'integer|exists:users,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:qa2_audit_items,id',
            'items.*.result' => 'nullable|in:C,NC,NA',
            'items.*.comment' => 'nullable|string',
            'items.*.due_date' => 'nullable|date',
        ]);

        DB::transaction(function () use ($id, $validated) {
            DB::table('qa2_audits')->where('id', $id)->update([
                'outlet_id' => (int) $validated['outlet_id'],
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);

            $this->syncPeople($id, $validated['auditor_ids'] ?? [], $validated['auditee_ids'] ?? []);

            foreach ($validated['items'] as $item) {
                DB::table('qa2_audit_items')
                    ->where('id', (int) $item['id'])
                    ->where('audit_id', $id)
                    ->update([
                        'result' => $item['result'] ?? null,
                        'comment' => $item['comment'] ?? null,
                        'due_date' => $item['due_date'] ?? null,
                        'updated_at' => now(),
                    ]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function submit(int $id)
    {
        $this->ensureHo();

        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);
        abort_if($audit->status !== 'draft', 422, 'Audit sudah disubmit.');

        $missing = DB::table('qa2_audit_items')
            ->where('audit_id', $id)
            ->whereNull('result')
            ->count();

        if ($missing > 0) {
            return back()->withErrors(['submit' => 'Semua parameter harus diisi C/NC/NA sebelum submit.']);
        }

        DB::table('qa2_audits')->where('id', $id)->update([
            'status' => 'submitted',
            'audit_time_end' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('qa2-audits.index')->with('success', 'QA Audit berhasil disubmit.');
    }

    public function uploadItemMedia(Request $request, int $id, int $itemId)
    {
        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);
        abort_if($audit->status !== 'draft', 422, 'Upload media hanya untuk draft.');
        $this->ensureHo();

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:30720|mimes:jpg,jpeg,png,webp,mp4,mov,avi,webm',
        ]);

        $exists = DB::table('qa2_audit_items')->where('id', $itemId)->where('audit_id', $id)->exists();
        abort_if(!$exists, 404);

        $userId = (int) auth()->id();
        $inserted = [];

        foreach ($request->file('files', []) as $file) {
            $path = $file->store('qa2-audits/items', 'public');
            $mime = (string) $file->getMimeType();
            $mediaType = Str::startsWith($mime, 'video/') ? 'video' : 'photo';

            $mediaId = DB::table('qa2_audit_item_media')->insertGetId([
                'audit_item_id' => $itemId,
                'uploaded_by' => $userId,
                'media_type' => $mediaType,
                'file_path' => $path,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $inserted[] = [
                'id' => $mediaId,
                'media_type' => $mediaType,
                'url' => Storage::url($path),
            ];
        }

        return response()->json(['success' => true, 'items' => $inserted]);
    }

    public function deleteItemMedia(int $id, int $itemId, int $mediaId)
    {
        $this->ensureHo();

        $row = DB::table('qa2_audit_item_media as m')
            ->join('qa2_audit_items as i', 'i.id', '=', 'm.audit_item_id')
            ->join('qa2_audits as a', 'a.id', '=', 'i.audit_id')
            ->where('a.id', $id)
            ->where('i.id', $itemId)
            ->where('m.id', $mediaId)
            ->select(['m.id', 'm.file_path'])
            ->first();

        abort_if(!$row, 404);

        DB::table('qa2_audit_item_media')->where('id', $mediaId)->delete();
        Storage::disk('public')->delete($row->file_path);

        return response()->json(['success' => true]);
    }

    public function saveCap(Request $request, int $id)
    {
        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);
        abort_if($audit->status !== 'submitted', 422, 'CAP hanya untuk audit submitted.');

        $user = auth()->user();
        $isAuditee = DB::table('qa2_audit_auditees')
            ->where('audit_id', $id)
            ->where('user_id', (int) $user->id)
            ->exists();

        abort_if(!$isAuditee, 403);

        $validated = $request->validate([
            'caps' => 'required|array|min:1',
            'caps.*.audit_item_id' => 'required|integer|exists:qa2_audit_items,id',
            'caps.*.action_plan' => 'nullable|string',
            'caps.*.target_date' => 'nullable|date',
            'caps.*.status' => 'nullable|in:open,progress,done',
        ]);

        $savedCaps = [];

        DB::transaction(function () use ($id, $validated, $user, &$savedCaps) {
            foreach ($validated['caps'] as $cap) {
                $item = DB::table('qa2_audit_items')
                    ->where('id', (int) $cap['audit_item_id'])
                    ->where('audit_id', $id)
                    ->where('result', 'NC')
                    ->exists();

                if (!$item) {
                    continue;
                }

                $auditItemId = (int) $cap['audit_item_id'];
                $existingCap = DB::table('qa2_audit_caps')->where('audit_item_id', $auditItemId)->first();

                if ($existingCap) {
                    DB::table('qa2_audit_caps')->where('id', $existingCap->id)->update([
                        'filled_by' => (int) $user->id,
                        'action_plan' => $cap['action_plan'] ?? null,
                        'target_date' => $cap['target_date'] ?? null,
                        'status' => $cap['status'] ?? 'open',
                        'updated_at' => now(),
                    ]);
                    $capId = (int) $existingCap->id;
                } else {
                    $capId = (int) DB::table('qa2_audit_caps')->insertGetId([
                        'audit_item_id' => $auditItemId,
                        'filled_by' => (int) $user->id,
                        'action_plan' => $cap['action_plan'] ?? null,
                        'target_date' => $cap['target_date'] ?? null,
                        'status' => $cap['status'] ?? 'open',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $savedCaps[] = [
                    'audit_item_id' => $auditItemId,
                    'cap_id' => $capId,
                ];
            }
        });

        return response()->json(['success' => true, 'caps' => $savedCaps]);
    }

    public function uploadCapMedia(Request $request, int $id, int $capId)
    {
        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);

        $user = auth()->user();
        $isAuditee = DB::table('qa2_audit_auditees')
            ->where('audit_id', $id)
            ->where('user_id', (int) $user->id)
            ->exists();

        abort_if(!$isAuditee, 403);

        $cap = DB::table('qa2_audit_caps as c')
            ->join('qa2_audit_items as i', 'i.id', '=', 'c.audit_item_id')
            ->where('c.id', $capId)
            ->where('i.audit_id', $id)
            ->select('c.id')
            ->first();

        abort_if(!$cap, 404);

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:30720|mimes:jpg,jpeg,png,webp,mp4,mov,avi,webm',
        ]);

        $inserted = [];
        foreach ($request->file('files', []) as $file) {
            $path = $file->store('qa2-audits/caps', 'public');
            $mime = (string) $file->getMimeType();
            $mediaType = Str::startsWith($mime, 'video/') ? 'video' : 'photo';

            $mediaId = DB::table('qa2_audit_cap_media')->insertGetId([
                'cap_id' => $capId,
                'uploaded_by' => (int) $user->id,
                'media_type' => $mediaType,
                'file_path' => $path,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $inserted[] = [
                'id' => $mediaId,
                'media_type' => $mediaType,
                'url' => Storage::url($path),
            ];
        }

        return response()->json(['success' => true, 'items' => $inserted]);
    }

    public function destroy(int $id)
    {
        $this->ensureHo();

        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);

        DB::transaction(function () use ($id) {
            $itemMedia = DB::table('qa2_audit_item_media as m')
                ->join('qa2_audit_items as i', 'i.id', '=', 'm.audit_item_id')
                ->where('i.audit_id', $id)
                ->pluck('m.file_path');

            $capMedia = DB::table('qa2_audit_cap_media as m')
                ->join('qa2_audit_caps as c', 'c.id', '=', 'm.cap_id')
                ->join('qa2_audit_items as i', 'i.id', '=', 'c.audit_item_id')
                ->where('i.audit_id', $id)
                ->pluck('m.file_path');

            DB::table('qa2_audit_cap_media')->whereIn('cap_id', function ($q) use ($id) {
                $q->from('qa2_audit_caps as c')
                    ->join('qa2_audit_items as i', 'i.id', '=', 'c.audit_item_id')
                    ->select('c.id')
                    ->where('i.audit_id', $id);
            })->delete();

            DB::table('qa2_audit_caps')->whereIn('audit_item_id', function ($q) use ($id) {
                $q->from('qa2_audit_items')->select('id')->where('audit_id', $id);
            })->delete();

            DB::table('qa2_audit_item_media')->whereIn('audit_item_id', function ($q) use ($id) {
                $q->from('qa2_audit_items')->select('id')->where('audit_id', $id);
            })->delete();

            DB::table('qa2_audit_items')->where('audit_id', $id)->delete();
            DB::table('qa2_audit_auditors')->where('audit_id', $id)->delete();
            DB::table('qa2_audit_auditees')->where('audit_id', $id)->delete();
            DB::table('qa2_audits')->where('id', $id)->delete();

            foreach ($itemMedia as $path) {
                Storage::disk('public')->delete((string) $path);
            }
            foreach ($capMedia as $path) {
                Storage::disk('public')->delete((string) $path);
            }
        });

        return back()->with('success', 'QA Audit berhasil dihapus.');
    }

    private function getAuditRow(int $id)
    {
        return DB::table('qa2_audits')->where('id', $id)->first();
    }

    private function ensureHo(): void
    {
        $isHo = (int) (auth()->user()->id_outlet ?? 0) === 1;
        abort_if(!$isHo, 403);
    }

    private function generateAuditNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "QAA-{$date}";
        $last = DB::table('qa2_audits')
            ->where('audit_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->value('audit_number');

        $next = 1;
        if ($last && str_contains($last, '-')) {
            $parts = explode('-', $last);
            $seq = (int) end($parts);
            $next = $seq + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function syncPeople(int $auditId, array $auditorIds, array $auditeeIds): void
    {
        DB::table('qa2_audit_auditors')->where('audit_id', $auditId)->delete();
        DB::table('qa2_audit_auditees')->where('audit_id', $auditId)->delete();

        $auditors = array_values(array_unique(array_map('intval', $auditorIds)));
        $auditees = array_values(array_unique(array_map('intval', $auditeeIds)));

        if (!empty($auditors)) {
            $rows = [];
            foreach ($auditors as $uid) {
                $rows[] = [
                    'audit_id' => $auditId,
                    'user_id' => $uid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('qa2_audit_auditors')->insert($rows);
        }

        if (!empty($auditees)) {
            $rows = [];
            foreach ($auditees as $uid) {
                $rows[] = [
                    'audit_id' => $auditId,
                    'user_id' => $uid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('qa2_audit_auditees')->insert($rows);
        }
    }

    private function getTemplateSeedRows(int $templateId): array
    {
        $items = DB::table('qa2_template_items as ti')
            ->join('qa2_parameters as p', 'p.id', '=', 'ti.parameter_id')
            ->leftJoin('qa2_subcategories as s', 's.id', '=', 'p.subcategory_id')
            ->leftJoin('qa2_categories as c', 'c.id', '=', 's.category_id')
            ->where('ti.template_id', $templateId)
            ->orderBy('ti.sort_order')
            ->select([
                'ti.id as template_item_id',
                'ti.sort_order',
                'p.id as parameter_id',
                'p.code as parameter_code',
                'p.parameter_text',
                's.id as subcategory_id',
                's.name as subcategory_name',
                'c.id as category_id',
                'c.name as category_name',
            ])
            ->get();

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'template_item_id' => (int) $item->template_item_id,
                'category_id' => $item->category_id ? (int) $item->category_id : null,
                'subcategory_id' => $item->subcategory_id ? (int) $item->subcategory_id : null,
                'parameter_id' => (int) $item->parameter_id,
                'parameter_code' => $item->parameter_code,
                'parameter_text' => $item->parameter_text,
                'sort_order' => (int) $item->sort_order,
            ];
        }

        return $rows;
    }

    private function seedAuditItemsFromTemplateRows(int $auditId, array $templateRows): int
    {
        $rows = [];
        foreach ($templateRows as $item) {
            $rows[] = [
                'audit_id' => $auditId,
                'template_item_id' => $item['template_item_id'],
                'category_id' => $item['category_id'],
                'subcategory_id' => $item['subcategory_id'],
                'parameter_id' => $item['parameter_id'],
                'parameter_code' => $item['parameter_code'],
                'parameter_text' => $item['parameter_text'],
                'sort_order' => $item['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rows)) {
            DB::table('qa2_audit_items')->insert($rows);
        }

        return count($rows);
    }

    private function ensureAuditItems(int $auditId, int $templateId): void
    {
        $exists = DB::table('qa2_audit_items')->where('audit_id', $auditId)->exists();
        if ($exists) {
            return;
        }

        $templateRows = $this->getTemplateSeedRows($templateId);
        if (!empty($templateRows)) {
            $this->seedAuditItemsFromTemplateRows($auditId, $templateRows);
        }
    }

    private function allowedOutlets(bool $isHo, int $userOutletId)
    {
        $query = DB::table('tbl_data_outlet')
            ->select(['id_outlet', 'nama_outlet'])
            ->orderBy('nama_outlet');

        if (!$isHo) {
            $query->where('id_outlet', $userOutletId);
        }

        return $query->get();
    }

    private function usersForSelector()
    {
        return DB::table('users as u')
            ->leftJoin('tbl_data_jabatan as j', 'j.id_jabatan', '=', 'u.id_jabatan')
            ->select([
                'u.id',
                'u.nama_lengkap',
                'u.id_outlet',
                'j.nama_jabatan as jabatan',
            ])
            ->where('u.status', 'A')
            ->whereNotNull('u.id_outlet')
            ->orderBy('u.nama_lengkap')
            ->get();
    }

    private function attachAuditPeople($paginator): void
    {
        $ids = collect($paginator->items())->pluck('id')->map(fn ($x) => (int) $x)->all();
        if (empty($ids)) {
            return;
        }

        $auditors = DB::table('qa2_audit_auditors as aa')
            ->join('users as u', 'u.id', '=', 'aa.user_id')
            ->leftJoin('tbl_data_jabatan as j', 'j.id_jabatan', '=', 'u.id_jabatan')
            ->whereIn('aa.audit_id', $ids)
            ->orderBy('u.nama_lengkap')
            ->get(['aa.audit_id', 'u.id', 'u.nama_lengkap', 'j.nama_jabatan as jabatan'])
            ->groupBy('audit_id');

        $auditees = DB::table('qa2_audit_auditees as ae')
            ->join('users as u', 'u.id', '=', 'ae.user_id')
            ->leftJoin('tbl_data_jabatan as j', 'j.id_jabatan', '=', 'u.id_jabatan')
            ->whereIn('ae.audit_id', $ids)
            ->orderBy('u.nama_lengkap')
            ->get(['ae.audit_id', 'u.id', 'u.nama_lengkap', 'j.nama_jabatan as jabatan'])
            ->groupBy('audit_id');

        foreach ($paginator->items() as $audit) {
            $auditId = (int) $audit->id;
            $audit->auditors = ($auditors[$auditId] ?? collect())->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->nama_lengkap,
                'jabatan' => $row->jabatan,
            ])->values()->all();
            $audit->auditees = ($auditees[$auditId] ?? collect())->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->nama_lengkap,
                'jabatan' => $row->jabatan,
            ])->values()->all();
        }
    }

    private function auditPayload(int $id): array
    {
        $audit = DB::table('qa2_audits as a')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'a.outlet_id')
            ->leftJoin('qa2_templates as t', 't.id', '=', 'a.template_id')
            ->select([
                'a.id',
                'a.audit_number',
                'a.audit_datetime',
                'a.audit_time_start',
                'a.audit_time_end',
                'a.status',
                'a.outlet_id',
                'a.template_id',
                'a.notes',
                'o.nama_outlet as outlet_name',
                't.name as template_name',
            ])
            ->where('a.id', $id)
            ->first();

        $auditorIds = DB::table('qa2_audit_auditors')->where('audit_id', $id)->pluck('user_id')->map(fn ($x) => (int) $x)->values()->all();
        $auditeeIds = DB::table('qa2_audit_auditees')->where('audit_id', $id)->pluck('user_id')->map(fn ($x) => (int) $x)->values()->all();

        $items = DB::table('qa2_audit_items as i')
            ->leftJoin('qa2_categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('qa2_subcategories as s', 's.id', '=', 'i.subcategory_id')
            ->where('i.audit_id', $id)
            ->orderBy('i.sort_order')
            ->select([
                'i.id',
                'i.category_id',
                'i.subcategory_id',
                'i.parameter_id',
                'i.parameter_code',
                'i.parameter_text',
                'i.sort_order',
                'i.result',
                'i.comment',
                'i.due_date',
                'c.name as category_name',
                's.name as subcategory_name',
            ])
            ->get()
            ->map(function ($row) {
                $row->media = DB::table('qa2_audit_item_media')
                    ->where('audit_item_id', $row->id)
                    ->orderBy('id')
                    ->get(['id', 'media_type', 'file_path'])
                    ->map(function ($m) {
                        return [
                            'id' => (int) $m->id,
                            'media_type' => $m->media_type,
                            'url' => Storage::url($m->file_path),
                        ];
                    })
                    ->values()
                    ->all();

                $cap = DB::table('qa2_audit_caps')->where('audit_item_id', $row->id)->first();
                $capMedia = [];
                if ($cap) {
                    $capMedia = DB::table('qa2_audit_cap_media')
                        ->where('cap_id', $cap->id)
                        ->orderBy('id')
                        ->get(['id', 'media_type', 'file_path'])
                        ->map(function ($m) {
                            return [
                                'id' => (int) $m->id,
                                'media_type' => $m->media_type,
                                'url' => Storage::url($m->file_path),
                            ];
                        })
                        ->values()
                        ->all();
                }

                $row->cap = $cap ? [
                    'id' => (int) $cap->id,
                    'action_plan' => $cap->action_plan,
                    'target_date' => $cap->target_date,
                    'status' => $cap->status,
                    'media' => $capMedia,
                ] : null;

                return $row;
            })
            ->values()
            ->all();

        return [
            'id' => (int) $audit->id,
            'audit_number' => $audit->audit_number,
            'audit_datetime' => $audit->audit_datetime,
            'audit_time_start' => $audit->audit_time_start,
            'audit_time_end' => $audit->audit_time_end,
            'status' => $audit->status,
            'outlet_id' => (int) $audit->outlet_id,
            'template_id' => (int) $audit->template_id,
            'notes' => $audit->notes,
            'outlet_name' => $audit->outlet_name,
            'template_name' => $audit->template_name,
            'auditor_ids' => $auditorIds,
            'auditee_ids' => $auditeeIds,
            'items' => $items,
        ];
    }

    private function auditTree(int $id): array
    {
        $items = DB::table('qa2_audit_items as i')
            ->leftJoin('qa2_categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('qa2_subcategories as s', 's.id', '=', 'i.subcategory_id')
            ->where('i.audit_id', $id)
            ->orderBy('i.sort_order')
            ->select([
                'i.id',
                'i.category_id',
                'i.subcategory_id',
                'c.name as category_name',
                's.name as subcategory_name',
            ])
            ->get();

        $grouped = [];
        foreach ($items as $item) {
            $catId = (int) ($item->category_id ?? 0);
            $subId = (int) ($item->subcategory_id ?? 0);
            if (!isset($grouped[$catId])) {
                $grouped[$catId] = [
                    'id' => $catId,
                    'name' => $item->category_name ?: 'Tanpa Kategori',
                    'subcategories' => [],
                ];
            }

            if (!isset($grouped[$catId]['subcategories'][$subId])) {
                $grouped[$catId]['subcategories'][$subId] = [
                    'id' => $subId,
                    'name' => $item->subcategory_name ?: 'Tanpa Subcategory',
                ];
            }
        }

        $result = [];
        foreach ($grouped as $cat) {
            $cat['subcategories'] = array_values($cat['subcategories']);
            $result[] = $cat;
        }

        return $result;
    }

    // ==================== Approval App API (ymsoftapp) ====================

    public function apiIndex(Request $request)
    {
        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;

        $search = $request->input('search');
        $status = $request->input('status');
        $outletId = $request->input('outlet_id');

        $query = DB::table('qa2_audits as a')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'a.outlet_id')
            ->leftJoin('qa2_templates as t', 't.id', '=', 'a.template_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.created_by')
            ->select([
                'a.id',
                'a.audit_number',
                'a.audit_datetime',
                'a.status',
                'a.outlet_id',
                'a.audit_time_start',
                'a.audit_time_end',
                'o.nama_outlet as outlet_name',
                't.name as template_name',
                'u.nama_lengkap as created_by_name',
            ])
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'C') as count_c")
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NC') as count_nc")
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NA') as count_na")
            ->selectRaw("(select count(*) from qa2_audit_items i where i.audit_id = a.id and i.result = 'NC' and not exists (select 1 from qa2_audit_caps c where c.audit_item_id = i.id and c.action_plan is not null and c.action_plan <> '')) as count_nc_pending_cap")
            ->orderByDesc('a.id');

        if (!$isHo) {
            $query->where('a.outlet_id', (int) $user->id_outlet);
        }

        if ($outletId) {
            $query->where('a.outlet_id', (int) $outletId);
        }

        if ($status && in_array($status, ['draft', 'submitted'], true)) {
            $query->where('a.status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('a.audit_number', 'like', "%{$search}%")
                    ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                    ->orWhere('t.name', 'like', "%{$search}%")
                    ->orWhere('u.nama_lengkap', 'like', "%{$search}%")
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->from('qa2_audit_auditors as aa')
                            ->join('users as au', 'au.id', '=', 'aa.user_id')
                            ->leftJoin('tbl_data_jabatan as aj', 'aj.id_jabatan', '=', 'au.id_jabatan')
                            ->whereColumn('aa.audit_id', 'a.id')
                            ->where(function ($people) use ($search) {
                                $people->where('au.nama_lengkap', 'like', "%{$search}%")
                                    ->orWhere('aj.nama_jabatan', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->from('qa2_audit_auditees as ae')
                            ->join('users as au', 'au.id', '=', 'ae.user_id')
                            ->leftJoin('tbl_data_jabatan as aj', 'aj.id_jabatan', '=', 'au.id_jabatan')
                            ->whereColumn('ae.audit_id', 'a.id')
                            ->where(function ($people) use ($search) {
                                $people->where('au.nama_lengkap', 'like', "%{$search}%")
                                    ->orWhere('aj.nama_jabatan', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $perPage = min(50, max(1, (int) $request->input('per_page', 15)));
        $audits = $query->paginate($perPage);
        $this->attachAuditPeople($audits);

        $statsQuery = DB::table('qa2_audits');
        if (!$isHo) {
            $statsQuery->where('outlet_id', (int) $user->id_outlet);
        }

        return response()->json([
            'success' => true,
            'audits' => collect($audits->items())->map(fn ($row) => (array) $row)->values(),
            'pagination' => [
                'current_page' => $audits->currentPage(),
                'last_page' => $audits->lastPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
            ],
            'statistics' => [
                'total' => (clone $statsQuery)->count(),
                'draft' => (clone $statsQuery)->where('status', 'draft')->count(),
                'submitted' => (clone $statsQuery)->where('status', 'submitted')->count(),
            ],
            'outlets' => $this->allowedOutlets($isHo, (int) $user->id_outlet),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'outlet_id' => $outletId,
            ],
            'permissions' => [
                'can_manage' => $isHo,
            ],
        ]);
    }

    public function apiCreateData()
    {
        $this->ensureHo();

        $user = auth()->user();

        return response()->json([
            'success' => true,
            'outlets' => $this->allowedOutlets(true, (int) $user->id_outlet),
            'users' => $this->usersForSelector(),
            'templates' => DB::table('qa2_templates')
                ->where('status', 'A')
                ->whereExists(function ($q) {
                    $q->from('qa2_template_items as ti')
                        ->selectRaw('1')
                        ->whereColumn('ti.template_id', 'qa2_templates.id');
                })
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function apiStore(Request $request)
    {
        $this->ensureHo();

        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'template_id' => 'required|exists:qa2_templates,id',
            'auditor_ids' => 'nullable|array',
            'auditor_ids.*' => 'integer|exists:users,id',
            'auditee_ids' => 'nullable|array',
            'auditee_ids.*' => 'integer|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $user = auth()->user();
        $templateRows = $this->getTemplateSeedRows((int) $validated['template_id']);
        if (empty($templateRows)) {
            return response()->json([
                'success' => false,
                'message' => 'Template tidak memiliki parameter audit.',
            ], 422);
        }

        $auditId = DB::transaction(function () use ($validated, $user, $templateRows) {
            $auditId = DB::table('qa2_audits')->insertGetId([
                'audit_number' => $this->generateAuditNumber(),
                'audit_datetime' => now(),
                'outlet_id' => (int) $validated['outlet_id'],
                'template_id' => (int) $validated['template_id'],
                'created_by' => (int) $user->id,
                'audit_time_start' => now(),
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncPeople($auditId, $validated['auditor_ids'] ?? [], $validated['auditee_ids'] ?? []);
            $this->seedAuditItemsFromTemplateRows($auditId, $templateRows);

            return $auditId;
        });

        return response()->json([
            'success' => true,
            'audit_id' => (int) $auditId,
            'message' => 'QA Audit draft berhasil dibuat.',
        ]);
    }

    public function apiShow(int $id)
    {
        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);

        $user = auth()->user();
        $isHo = (int) ($user->id_outlet ?? 0) === 1;
        if (!$isHo && (int) $audit->outlet_id !== (int) $user->id_outlet) {
            abort(403);
        }

        $this->ensureAuditItems((int) $id, (int) $audit->template_id);

        $canFillCap = DB::table('qa2_audit_auditees')
            ->where('audit_id', $id)
            ->where('user_id', (int) $user->id)
            ->exists();

        return response()->json([
            'success' => true,
            'mode' => 'edit',
            'audit' => $this->auditPayload($id),
            'outlets' => $this->allowedOutlets($isHo, (int) $user->id_outlet),
            'users' => $this->usersForSelector(),
            'templates' => DB::table('qa2_templates')
                ->where('status', 'A')
                ->whereExists(function ($q) {
                    $q->from('qa2_template_items as ti')
                        ->selectRaw('1')
                        ->whereColumn('ti.template_id', 'qa2_templates.id');
                })
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'tree' => $this->auditTree($id),
            'permissions' => [
                'can_manage' => $isHo && $audit->status === 'draft',
                'can_fill_cap' => $canFillCap && $audit->status === 'submitted',
            ],
        ]);
    }

    public function apiSubmit(int $id)
    {
        $this->ensureHo();

        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);
        abort_if($audit->status !== 'draft', 422, 'Audit sudah disubmit.');

        $missing = DB::table('qa2_audit_items')
            ->where('audit_id', $id)
            ->whereNull('result')
            ->count();

        if ($missing > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Semua parameter harus diisi C/NC/NA sebelum submit.',
            ], 422);
        }

        DB::table('qa2_audits')->where('id', $id)->update([
            'status' => 'submitted',
            'audit_time_end' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QA Audit berhasil disubmit.',
        ]);
    }

    public function apiDestroy(int $id)
    {
        $this->ensureHo();

        $audit = $this->getAuditRow($id);
        abort_if(!$audit, 404);

        DB::transaction(function () use ($id) {
            $itemMedia = DB::table('qa2_audit_item_media as m')
                ->join('qa2_audit_items as i', 'i.id', '=', 'm.audit_item_id')
                ->where('i.audit_id', $id)
                ->pluck('m.file_path');

            $capMedia = DB::table('qa2_audit_cap_media as m')
                ->join('qa2_audit_caps as c', 'c.id', '=', 'm.cap_id')
                ->join('qa2_audit_items as i', 'i.id', '=', 'c.audit_item_id')
                ->where('i.audit_id', $id)
                ->pluck('m.file_path');

            DB::table('qa2_audit_cap_media')->whereIn('cap_id', function ($q) use ($id) {
                $q->from('qa2_audit_caps as c')
                    ->join('qa2_audit_items as i', 'i.id', '=', 'c.audit_item_id')
                    ->select('c.id')
                    ->where('i.audit_id', $id);
            })->delete();

            DB::table('qa2_audit_caps')->whereIn('audit_item_id', function ($q) use ($id) {
                $q->from('qa2_audit_items')->select('id')->where('audit_id', $id);
            })->delete();

            DB::table('qa2_audit_item_media')->whereIn('audit_item_id', function ($q) use ($id) {
                $q->from('qa2_audit_items')->select('id')->where('audit_id', $id);
            })->delete();

            DB::table('qa2_audit_items')->where('audit_id', $id)->delete();
            DB::table('qa2_audit_auditors')->where('audit_id', $id)->delete();
            DB::table('qa2_audit_auditees')->where('audit_id', $id)->delete();
            DB::table('qa2_audits')->where('id', $id)->delete();

            foreach ($itemMedia as $path) {
                Storage::disk('public')->delete((string) $path);
            }
            foreach ($capMedia as $path) {
                Storage::disk('public')->delete((string) $path);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'QA Audit berhasil dihapus.',
        ]);
    }
}
