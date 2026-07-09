<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRegional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RegionalController extends Controller
{
    private ?bool $hasOutletVisitTargetsColumn = null;

    private ?bool $hasSupervisorPositionIdColumn = null;

    public function index(Request $request)
    {
        $query = DB::table('users as u')
            ->join('user_regional as ur', 'u.id', '=', 'ur.user_id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
            ->select(
                'u.id',
                'u.nama_lengkap as name',
                'u.email',
                'u.status',
                'u.avatar',
                'j.nama_jabatan',
                'd.nama_divisi',
                'ur.area',
                'ur.target_outlet_visits',
                'ur.updated_at as assigned_at',
            )
            ->orderBy('u.nama_lengkap');

        if ($this->hasOutletVisitTargetsColumn()) {
            $query->addSelect('ur.outlet_visit_targets');
        }

        if ($this->hasSupervisorPositionIdColumn()) {
            $query
                ->leftJoin('tbl_data_jabatan as sj', 'ur.supervisor_position_id', '=', 'sj.id_jabatan')
                ->addSelect('ur.supervisor_position_id', 'sj.nama_jabatan as supervisor_position_name');
        }

        if ($request->filled('status')) {
            $query->where('u.status', $request->get('status'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('u.nama_lengkap', 'like', '%' . $search . '%')
                    ->orWhere('u.email', 'like', '%' . $search . '%');
            });
        }

        $users = $query->get()->map(function ($row) {
            $targets = $this->hasOutletVisitTargetsColumn() ? ($row->outlet_visit_targets ?? []) : [];
            if (is_string($targets)) {
                $decoded = json_decode($targets, true);
                $targets = is_array($decoded) ? $decoded : [];
            }
            if (! is_array($targets)) {
                $targets = [];
            }

            $outletNames = $this->getOutletNamesByIds(
                collect($targets)->pluck('outlet_id')->map(fn ($id) => (int) $id)->all(),
            );

            $row->outlet_visit_targets = collect($targets)->map(function ($target) use ($outletNames) {
                $outletId = (int) ($target['outlet_id'] ?? 0);

                return [
                    'outlet_id' => $outletId,
                    'target_visits' => (int) ($target['target_visits'] ?? 0),
                    'outlet_name' => $outletNames[$outletId] ?? ('Outlet #' . $outletId),
                ];
            })->values()->all();

            return $row;
        });

        return inertia('Regional/Index', [
            'users' => $users,
            'filters' => [
                'status' => $request->get('status', ''),
                'search' => $request->get('search', ''),
            ],
        ]);
    }

    public function create()
    {
        return inertia('Regional/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => [
                'required',
                'exists:users,id',
                Rule::unique('user_regional', 'user_id'),
            ],
            'area' => ['required', Rule::in(UserRegional::AREAS)],
            'target_outlet_visits' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'outlet_visit_targets' => ['nullable', 'array'],
            'outlet_visit_targets.*.outlet_id' => ['required', 'integer', 'distinct', 'exists:tbl_data_outlet,id_outlet'],
            'outlet_visit_targets.*.target_visits' => ['required', 'integer', 'min:0', 'max:9999'],
            'supervisor_position_id' => ['required', 'integer', 'exists:tbl_data_jabatan,id_jabatan'],
        ]);

        try {
            $outletTargets = $this->normalizeOutletTargets($request->input('outlet_visit_targets', []));

            UserRegional::create([
                'user_id' => $request->user_id,
                'area' => $request->area,
                'target_outlet_visits' => $this->resolveTotalTargetVisits($request->input('target_outlet_visits'), $outletTargets),
                ...($this->hasOutletVisitTargetsColumn() ? ['outlet_visit_targets' => $outletTargets] : []),
                ...($this->hasSupervisorPositionIdColumn() ? ['supervisor_position_id' => $request->input('supervisor_position_id')] : []),
            ]);

            return redirect()->route('regional.index')
                ->with('success', 'Regional assignment berhasil disimpan!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menyimpan regional assignment: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $assignment = UserRegional::where('user_id', $id)->first();

        return inertia('Regional/Edit', [
            'user' => $user,
            'currentArea' => $assignment?->area,
            'targetOutletVisits' => $assignment?->target_outlet_visits,
            'outletVisitTargets' => $this->hasOutletVisitTargetsColumn() ? ($assignment?->outlet_visit_targets ?? []) : [],
            'supervisorPositionId' => $this->hasSupervisorPositionIdColumn() ? $assignment?->supervisor_position_id : null,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'area' => ['required', Rule::in(UserRegional::AREAS)],
            'target_outlet_visits' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'outlet_visit_targets' => ['nullable', 'array'],
            'outlet_visit_targets.*.outlet_id' => ['required', 'integer', 'distinct', 'exists:tbl_data_outlet,id_outlet'],
            'outlet_visit_targets.*.target_visits' => ['required', 'integer', 'min:0', 'max:9999'],
            'supervisor_position_id' => ['required', 'integer', 'exists:tbl_data_jabatan,id_jabatan'],
        ]);

        try {
            $outletTargets = $this->normalizeOutletTargets($request->input('outlet_visit_targets', []));

            UserRegional::updateOrCreate(
                ['user_id' => $id],
                [
                    'area' => $request->area,
                    'target_outlet_visits' => $this->resolveTotalTargetVisits($request->input('target_outlet_visits'), $outletTargets),
                    ...($this->hasOutletVisitTargetsColumn() ? ['outlet_visit_targets' => $outletTargets] : []),
                    ...($this->hasSupervisorPositionIdColumn() ? ['supervisor_position_id' => $request->input('supervisor_position_id')] : []),
                ],
            );

            return redirect()->route('regional.index')
                ->with('success', 'Regional assignment berhasil diupdate!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal mengupdate regional assignment: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            UserRegional::where('user_id', $id)->delete();

            return redirect()->route('regional.index')
                ->with('success', 'Regional assignment berhasil dihapus!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menghapus regional assignment: ' . $e->getMessage());
        }
    }

    public function searchUsers(Request $request)
    {
        $search = $request->get('search', '');

        $users = DB::table('users')
            ->where('status', 'A')
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('nama_lengkap', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                }
            })
            ->select('id', 'nama_lengkap as name', 'email')
            ->orderBy('nama_lengkap')
            ->get();

        return response()->json($users);
    }

    public function getUserRegional($userId)
    {
        $assignment = UserRegional::where('user_id', $userId)->first();

        return response()->json([
            'user_id' => (int) $userId,
            'area' => $assignment?->area,
            'target_outlet_visits' => $assignment?->target_outlet_visits,
            'outlet_visit_targets' => $this->hasOutletVisitTargetsColumn() ? ($assignment?->outlet_visit_targets ?? []) : [],
            'supervisor_position_id' => $this->hasSupervisorPositionIdColumn() ? $assignment?->supervisor_position_id : null,
        ]);
    }

    public function searchOutlets(Request $request)
    {
        $search = $request->get('search', '');

        $outlets = DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('nama_outlet', 'like', '%' . $search . '%');
                }
            })
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        return response()->json($outlets);
    }

    public function searchSupervisorPositions(Request $request)
    {
        $search = $request->get('search', '');

        $positions = DB::table('tbl_data_jabatan as j')
            ->join('users as u', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('j.status', 'A')
            ->where('u.status', 'A')
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('j.nama_jabatan', 'like', '%' . $search . '%');
                }
            })
            ->selectRaw('MIN(j.id_jabatan) as id, j.nama_jabatan as name')
            ->groupBy('j.nama_jabatan')
            ->orderBy('j.nama_jabatan')
            ->get();

        return response()->json($positions);
    }

    /**
     * @param  array<int, array<string, mixed>>  $targets
     * @return array<int, array{outlet_id:int,target_visits:int}>
     */
    private function normalizeOutletTargets(array $targets): array
    {
        return collect($targets)
            ->map(function ($row) {
                $outletId = (int) ($row['outlet_id'] ?? 0);
                $targetVisits = (int) ($row['target_visits'] ?? 0);

                return [
                    'outlet_id' => $outletId,
                    'target_visits' => $targetVisits,
                ];
            })
            ->filter(fn ($row) => $row['outlet_id'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  int|string|null  $legacyTarget
     * @param  array<int, array{outlet_id:int,target_visits:int}>  $outletTargets
     */
    private function resolveTotalTargetVisits($legacyTarget, array $outletTargets): ?int
    {
        if (! empty($outletTargets)) {
            return (int) collect($outletTargets)->sum('target_visits');
        }

        if ($legacyTarget === '' || $legacyTarget === null) {
            return null;
        }

        return (int) $legacyTarget;
    }

    /**
     * @param  array<int>  $outletIds
     * @return array<int, string>
     */
    private function getOutletNamesByIds(array $outletIds): array
    {
        $outletIds = array_values(array_unique(array_filter($outletIds)));
        if (empty($outletIds)) {
            return [];
        }

        return DB::table('tbl_data_outlet')
            ->whereIn('id_outlet', $outletIds)
            ->pluck('nama_outlet', 'id_outlet')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
            ->all();
    }

    private function hasOutletVisitTargetsColumn(): bool
    {
        if ($this->hasOutletVisitTargetsColumn === null) {
            $this->hasOutletVisitTargetsColumn = Schema::hasColumn('user_regional', 'outlet_visit_targets');
        }

        return $this->hasOutletVisitTargetsColumn;
    }

    private function hasSupervisorPositionIdColumn(): bool
    {
        if ($this->hasSupervisorPositionIdColumn === null) {
            $this->hasSupervisorPositionIdColumn = Schema::hasColumn('user_regional', 'supervisor_position_id');
        }

        return $this->hasSupervisorPositionIdColumn;
    }
}
