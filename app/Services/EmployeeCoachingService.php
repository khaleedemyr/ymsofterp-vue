<?php

namespace App\Services;

use App\Models\EmployeeCoaching;
use App\Models\EmployeeCoachingConcern;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmployeeCoachingService
{
    public const CONCERN_CODES = [
        'behavior',
        'safety',
        'customer_service',
        'rules_sop',
        'attendance',
        'appearance',
        'other',
    ];

    /**
     * @return list<array{code: string, label_en: string, label_id: string}>
     */
    public function concernOptions(): array
    {
        return [
            [
                'code' => 'behavior',
                'label_en' => 'Behavior, interpersonal skill',
                'label_id' => 'Prilaku, kemampuan berkomunikasi & membangun hubungan dengan orang lain',
            ],
            [
                'code' => 'safety',
                'label_en' => 'Safety of Work Environment',
                'label_id' => 'Keselamatan lingkungan kerja',
            ],
            [
                'code' => 'customer_service',
                'label_en' => 'Customer Service',
                'label_id' => 'Pelayanan Pelanggan',
            ],
            [
                'code' => 'rules_sop',
                'label_en' => 'Company / Department Rules, S.O.P',
                'label_id' => 'Aturan perusahaan / departemen, standar operasional prosedur',
            ],
            [
                'code' => 'attendance',
                'label_en' => 'Attendance',
                'label_id' => 'Kehadiran',
            ],
            [
                'code' => 'appearance',
                'label_en' => 'Appearance',
                'label_id' => 'Penampilan',
            ],
            [
                'code' => 'other',
                'label_en' => 'Other',
                'label_id' => 'Lain-Lain',
            ],
        ];
    }

    public function concernLabel(string $code): string
    {
        foreach ($this->concernOptions() as $option) {
            if ($option['code'] === $code) {
                return $option['label_en'];
            }
        }

        return $code;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchEmployees(string $query, int $limit = 15): array
    {
        $search = trim($query);
        if ($search === '') {
            return [];
        }

        return User::query()
            ->where('users.status', 'A')
            ->where(function ($q) use ($search) {
                $q->where('users.nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('users.nik', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            })
            ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
            ->leftJoin('tbl_data_divisi as d', 'users.division_id', '=', 'd.id')
            ->leftJoin('tbl_data_outlet as o', 'users.id_outlet', '=', 'o.id_outlet')
            ->orderBy('users.nama_lengkap')
            ->limit($limit)
            ->get([
                'users.id',
                'users.nama_lengkap',
                'users.nik',
                'users.email',
                'users.id_jabatan',
                'users.id_outlet',
                'users.division_id',
                DB::raw('j.nama_jabatan as jabatan_name'),
                DB::raw('d.nama_divisi as division_name'),
                DB::raw('o.nama_outlet as outlet_name'),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'nama_lengkap' => (string) $row->nama_lengkap,
                'nik' => (string) ($row->nik ?? ''),
                'email' => (string) ($row->email ?? ''),
                'id_jabatan' => $row->id_jabatan ? (int) $row->id_jabatan : null,
                'id_outlet' => $row->id_outlet ? (int) $row->id_outlet : null,
                'division_id' => $row->division_id ? (int) $row->division_id : null,
                'jabatan_name' => (string) ($row->jabatan_name ?? '-'),
                'division_name' => (string) ($row->division_name ?? '-'),
                'outlet_name' => (string) ($row->outlet_name ?? '-'),
                'display_label' => trim(sprintf(
                    '%s — %s @ %s',
                    $row->nama_lengkap,
                    $row->jabatan_name ?? '-',
                    $row->outlet_name ?? '-'
                )),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function syncConcerns(EmployeeCoaching $coaching, array $concerns): void
    {
        EmployeeCoachingConcern::where('employee_coaching_id', $coaching->id)->delete();

        foreach ($concerns as $index => $concern) {
            EmployeeCoachingConcern::create([
                'employee_coaching_id' => $coaching->id,
                'concern_code' => $concern['code'],
                'other_label' => $concern['other_label'] ?? null,
                'comment' => $concern['comment'],
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(EmployeeCoaching $coaching): array
    {
        $coaching->loadMissing('concerns');

        return [
            'id' => $coaching->id,
            'employee_id' => $coaching->employee_id,
            'employee_name' => $coaching->employee_name,
            'jabatan_name' => $coaching->jabatan_name,
            'outlet_name' => $coaching->outlet_name,
            'division_name' => $coaching->division_name,
            'performance_description' => $coaching->performance_description,
            'action_taken' => $coaching->action_taken,
            'action_due_date' => optional($coaching->action_due_date)?->format('Y-m-d'),
            'performance_review_plan_date' => optional($coaching->performance_review_plan_date)?->format('Y-m-d'),
            'concerns' => $coaching->concerns->map(fn (EmployeeCoachingConcern $item) => [
                'code' => $item->concern_code,
                'other_label' => $item->other_label,
                'comment' => $item->comment,
            ])->values()->all(),
        ];
    }
}
