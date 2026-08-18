<?php

namespace App\Services;

use App\Models\EmployeeResignation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Karyawan yang harus tetap dihitung di outlet lama/baru pada periode absensi (26–25),
 * selaras menu Payroll: mutasi (employee_movements) dan resign approved.
 */
class OutletPeriodEmployeeScopeService
{
    /**
     * @return array{
     *     include_user_ids: list<int>,
     *     mutation_map: array<int, array<string, mixed>>,
     *     resignations: array<int, string>
     * }
     */
    public function resolve(?int $outletId, string $start, string $end, int $year, int $month): array
    {
        $empty = [
            'include_user_ids' => [],
            'mutation_map' => [],
            'resignations' => [],
        ];

        if (empty($outletId)) {
            return $empty;
        }

        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();
        $gajian2Start = Carbon::create($year, $month, 1)->startOfDay();
        $gajian2End = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');
        if (! $outletName) {
            return $empty;
        }

        $mutations = $this->collectMutations((int) $outletId, $outletName, $start, $end, $gajian2Start, $gajian2End);
        $mutationMap = $this->buildMutationMap($mutations, (int) $outletId, $outletName);
        $mutationMap = $this->filterMutationMapForPeriod($mutationMap, $startDate, $gajian2End);

        $resignations = $this->collectResignations((int) $outletId, $start, $end, $gajian2Start, $gajian2End);

        $includeIds = array_values(array_unique(array_merge(
            array_keys($mutationMap),
            array_keys($resignations)
        )));

        return [
            'include_user_ids' => array_map('intval', $includeIds),
            'mutation_map' => $mutationMap,
            'resignations' => $resignations,
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{start: string, end: string}|null
     */
    public function segmentForUser(int $userId, string $start, string $end, array $scope): ?array
    {
        $segStart = Carbon::parse($start)->startOfDay();
        $segEnd = Carbon::parse($end)->startOfDay();

        if (isset($scope['mutation_map'][$userId])) {
            $mut = $scope['mutation_map'][$userId];
            $segment = PayrollSplitPoolCalculator::resolveMutationDateSegment(
                Carbon::parse($mut['effective_date']),
                $mut['role'] ?? 'from',
                $segStart,
                $segEnd
            );

            if (! $segment) {
                return null;
            }

            $segStart = $segment['start']->copy()->startOfDay();
            $segEnd = $segment['end']->copy()->startOfDay();
        }

        if (isset($scope['resignations'][$userId])) {
            $resignDay = Carbon::parse($scope['resignations'][$userId])->startOfDay();
            if ($resignDay->lt($segStart)) {
                return null;
            }
            if ($resignDay->lt($segEnd)) {
                $segEnd = $resignDay;
            }
        }

        if ($segStart->gt($segEnd)) {
            return null;
        }

        return [
            'start' => $segStart->toDateString(),
            'end' => $segEnd->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function dateInScope(int $userId, string $date, string $start, string $end, array $scope): bool
    {
        $segment = $this->segmentForUser($userId, $start, $end, $scope);
        if (! $segment) {
            return isset($scope['mutation_map'][$userId]) || isset($scope['resignations'][$userId])
                ? false
                : true;
        }

        $d = Carbon::parse($date)->startOfDay();

        return $d->gte(Carbon::parse($segment['start'])) && $d->lte(Carbon::parse($segment['end']));
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array<string, mixed>  $scope
     */
    public function applyAttendanceUserFilter($query, ?int $outletId, array $scope, bool $activeOnly = false): void
    {
        if (empty($outletId)) {
            return;
        }

        $extraIds = $scope['include_user_ids'] ?? [];

        $query->where(function ($q) use ($outletId, $extraIds, $activeOnly) {
            $q->where(function ($inner) use ($outletId, $activeOnly) {
                $inner->where('u.id_outlet', $outletId);
                if ($activeOnly) {
                    $inner->where('u.status', 'A');
                }
            });

            if (! empty($extraIds)) {
                $q->orWhereIn('u.id', $extraIds);
            }
        });
    }

    private function collectMutations(
        int $outletId,
        string $outletName,
        string $start,
        string $end,
        Carbon $gajian2Start,
        Carbon $gajian2End
    ) {
        $periodFilter = function ($query) use ($start, $end, $gajian2Start, $gajian2End) {
            $query->where(function ($q) use ($start, $end, $gajian2Start, $gajian2End) {
                $q->whereBetween('employment_effective_date', [$start, $end])
                    ->orWhereBetween('employment_effective_date', [
                        $gajian2Start->toDateString(),
                        $gajian2End->toDateString(),
                    ]);
            })->where('employment_effective_date', '>', $start);
        };

        $baseSelect = [
            'id', 'employee_id', 'employee_name', 'employee_unit_property', 'unit_property_change',
            'unit_property_from', 'unit_property_to', 'employment_effective_date', 'status',
        ];

        $fromMutations = DB::table('employee_movements')
            ->where('employment_type', 'mutation')
            ->where('unit_property_change', '>', 0)
            ->whereNotNull('employment_effective_date')
            ->where(function ($q) use ($outletId, $outletName) {
                $this->applyOutletMovementPropertyScope($q, 'unit_property_from', $outletId, $outletName);
                $q->orWhere(function ($q2) use ($outletId, $outletName) {
                    $this->applyOutletMovementPropertyScope($q2, 'employee_unit_property', $outletId, $outletName);
                });
            })
            ->where($periodFilter)
            ->whereIn('status', ['executed', 'approved', 'pending'])
            ->select($baseSelect)
            ->get();

        $toMutations = DB::table('employee_movements')
            ->where('employment_type', 'mutation')
            ->where('unit_property_change', '>', 0)
            ->whereNotNull('employment_effective_date')
            ->where(function ($q) use ($outletId, $outletName) {
                $this->applyOutletMovementPropertyScope($q, 'unit_property_to', $outletId, $outletName);
            })
            ->where($periodFilter)
            ->whereIn('status', ['executed', 'approved', 'pending'])
            ->select($baseSelect)
            ->get();

        return $fromMutations->merge($toMutations)->unique('id')->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $mutations
     * @return array<int, array<string, mixed>>
     */
    private function buildMutationMap($mutations, int $outletId, string $outletName): array
    {
        $mutationMap = [];

        foreach ($mutations as $m) {
            if ((int) ($m->unit_property_change ?? 0) <= 0) {
                continue;
            }

            $outletToId = $this->resolveOutletIdFromMovementProperty($m->unit_property_to);
            $outletFromId = $this->resolveOutletIdFromMovementProperty($m->unit_property_from);
            $outletFromMatches = $this->outletMovementPropertyMatches($outletId, $outletName, $m->unit_property_from);
            $outletToMatches = $this->outletMovementPropertyMatches($outletId, $outletName, $m->unit_property_to);

            if ($outletToMatches) {
                $mutationMap[(int) $m->employee_id] = [
                    'effective_date' => $m->employment_effective_date,
                    'outlet_from_id' => $outletFromId,
                    'outlet_to_id' => $outletId,
                    'outlet_from_name' => $this->resolveOutletNameFromMovementProperty($m->unit_property_from),
                    'outlet_to_name' => $outletName,
                    'employee_name' => $m->employee_name,
                    'role' => 'to',
                ];
            } elseif ($outletFromMatches) {
                $mutationMap[(int) $m->employee_id] = [
                    'effective_date' => $m->employment_effective_date,
                    'outlet_from_id' => $outletId,
                    'outlet_to_id' => $outletToId,
                    'outlet_from_name' => $outletName,
                    'outlet_to_name' => $this->resolveOutletNameFromMovementProperty($m->unit_property_to),
                    'employee_name' => $m->employee_name,
                    'role' => 'from',
                ];
            }
        }

        return $mutationMap;
    }

    /**
     * @param  array<int, array<string, mixed>>  $mutationMap
     * @return array<int, array<string, mixed>>
     */
    private function filterMutationMapForPeriod(array $mutationMap, Carbon $startDate, Carbon $gajian2End): array
    {
        $filtered = [];

        foreach ($mutationMap as $userId => $mut) {
            $effective = Carbon::parse($mut['effective_date'])->startOfDay();
            $role = $mut['role'] ?? 'from';

            if ($role === 'to' && $effective->gt($gajian2End->copy()->startOfDay())) {
                continue;
            }

            if ($role === 'from' && $effective->lte($startDate->copy()->startOfDay())) {
                continue;
            }

            $filtered[(int) $userId] = $mut;
        }

        return $filtered;
    }

    /**
     * @return array<int, string>
     */
    private function collectResignations(
        int $outletId,
        string $start,
        string $end,
        Carbon $gajian2Start,
        Carbon $gajian2End
    ): array {
        $all = EmployeeResignation::where('status', 'approved')
            ->where(function ($query) use ($start, $end, $gajian2Start, $gajian2End) {
                $query->whereBetween('resignation_date', [$start, $end])
                    ->orWhereBetween('resignation_date', [$gajian2Start, $gajian2End]);
            })
            ->get(['employee_id', 'resignation_date', 'outlet_id']);

        $employeeIds = $all->pluck('employee_id')->all();
        if (empty($employeeIds)) {
            return [];
        }

        $resignedAtOutlet = DB::table('users')
            ->whereIn('id', $employeeIds)
            ->where(function ($q) use ($outletId, $all) {
                $q->where('id_outlet', $outletId)
                    ->orWhereIn('id', $all->where('outlet_id', $outletId)->pluck('employee_id'));
            })
            ->pluck('id')
            ->all();

        $map = [];
        foreach ($all as $row) {
            if (! in_array((int) $row->employee_id, array_map('intval', $resignedAtOutlet), true)
                && (int) $row->outlet_id !== $outletId) {
                continue;
            }

            $map[(int) $row->employee_id] = Carbon::parse($row->resignation_date)->toDateString();
        }

        return $map;
    }

    private function applyOutletMovementPropertyScope($query, string $column, int $outletId, string $outletName): void
    {
        $query->where(function ($q) use ($column, $outletId, $outletName) {
            $q->where($column, $outletName)
                ->orWhere($column, (string) $outletId);
        });
    }

    private function outletMovementPropertyMatches(int $outletId, string $outletName, ?string $property): bool
    {
        if ($property === null || $property === '') {
            return false;
        }

        return $property === $outletName || $property === (string) $outletId;
    }

    private function resolveOutletIdFromMovementProperty(?string $property): ?int
    {
        if ($property === null || $property === '') {
            return null;
        }

        if (ctype_digit((string) $property)) {
            return (int) $property;
        }

        $id = DB::table('tbl_data_outlet')->where('nama_outlet', $property)->value('id_outlet');

        return $id ? (int) $id : null;
    }

    private function resolveOutletNameFromMovementProperty(?string $property): ?string
    {
        if ($property === null || $property === '') {
            return null;
        }

        if (ctype_digit((string) $property)) {
            $name = DB::table('tbl_data_outlet')
                ->where('id_outlet', (int) $property)
                ->value('nama_outlet');

            return $name ?: null;
        }

        return $property;
    }
}
