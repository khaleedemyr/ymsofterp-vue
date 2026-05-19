<?php

namespace App\Services;

use App\Models\PrKasbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollKasbonService
{
    public function tablesReady(): bool
    {
        return Schema::hasTable('pr_kasbons');
    }

    public function deductionsTableReady(): bool
    {
        return Schema::hasTable('pr_kasbon_payroll_deductions');
    }

    /**
     * Satu kasbon aktif per karyawan (NFP paid, belum lunas).
     *
     * @param  array<int>  $userIds
     * @return array<int, array{pr_kasbon_id: int, amount: float, cicilan_ke: int, pr_number: ?string, termin_total: int, paid_installments: int}>
     */
    public function loadEligibleByUserIds(array $userIds, ?int $outletId = null): array
    {
        if (! $this->tablesReady() || empty($userIds)) {
            return [];
        }

        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        $nfpLatestSub = DB::table('non_food_payments')
            ->select('purchase_requisition_id', DB::raw('MAX(id) as latest_nfp_id'))
            ->whereNotNull('purchase_requisition_id')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->groupBy('purchase_requisition_id');

        $query = DB::table('pr_kasbons as k')
            ->leftJoinSub($nfpLatestSub, 'nfp_idx', function ($join) {
                $join->on('k.purchase_requisition_id', '=', 'nfp_idx.purchase_requisition_id');
            })
            ->leftJoin('non_food_payments as nfp', 'nfp.id', '=', 'nfp_idx.latest_nfp_id')
            ->whereIn('k.employee_user_id', $userIds)
            ->where('k.status', '!=', 'completed')
            ->whereNotNull('nfp.id')
            ->where('nfp.status', 'paid')
            ->whereColumn('k.paid_installments', '<', 'k.termin_total')
            ->where('k.installment_amount', '>', 0)
            ->orderByDesc('k.id')
            ->select([
                'k.id',
                'k.employee_user_id',
                'k.pr_number',
                'k.installment_amount',
                'k.termin_total',
                'k.paid_installments',
                'k.outlet_id',
            ]);

        if ($outletId) {
            $query->where('k.outlet_id', $outletId);
        }

        $map = [];
        foreach ($query->get() as $row) {
            $uid = (int) $row->employee_user_id;
            if (isset($map[$uid])) {
                continue;
            }
            $paid = (int) $row->paid_installments;
            $termin = max(1, (int) $row->termin_total);
            if ($paid >= $termin) {
                continue;
            }
            $map[$uid] = [
                'pr_kasbon_id' => (int) $row->id,
                'amount' => (float) $row->installment_amount,
                'cicilan_ke' => $paid + 1,
                'pr_number' => $row->pr_number,
                'termin_total' => $termin,
                'paid_installments' => $paid,
            ];
        }

        return $map;
    }

    /**
     * @return array{potongan_kasbon: float, pr_kasbon_id: ?int, kasbon_cicilan_ke: ?int, kasbon_pr_number: ?string}|null
     */
    public function previewForUser(int $userId, array $eligibleMap): ?array
    {
        $info = $eligibleMap[$userId] ?? null;
        if (! $info) {
            return null;
        }

        return [
            'potongan_kasbon' => round((float) $info['amount'], 2),
            'pr_kasbon_id' => (int) $info['pr_kasbon_id'],
            'kasbon_cicilan_ke' => (int) $info['cicilan_ke'],
            'kasbon_pr_number' => $info['pr_number'] ?? null,
        ];
    }

    /**
     * Batalkan cicilan yang sudah tercatat untuk payroll ini (saat regenerate).
     */
    public function reversePayrollDeductions(int $payrollGeneratedId): void
    {
        if (! $this->deductionsTableReady()) {
            return;
        }

        $rows = DB::table('pr_kasbon_payroll_deductions')
            ->where('payroll_generated_id', $payrollGeneratedId)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows as $row) {
            $k = PrKasbon::query()->lockForUpdate()->find($row->pr_kasbon_id);
            if (! $k) {
                continue;
            }
            $newPaid = max(0, (int) $k->paid_installments - 1);
            $termin = max(1, (int) $k->termin_total);
            $line = '[' . now()->format('Y-m-d H:i') . '] Pembatalan cicilan payroll #' . $payrollGeneratedId
                . ' (menjadi ' . $newPaid . '/' . $termin . ')';
            $mergedNotes = trim(($k->notes ? $k->notes . "\n" : '') . $line);
            if (strlen($mergedNotes) > 2000) {
                $mergedNotes = substr($mergedNotes, -2000);
            }

            $k->update([
                'paid_installments' => $newPaid,
                'status' => $newPaid >= $termin ? 'completed' : 'active',
                'last_installment_at' => $newPaid > 0 ? $k->last_installment_at : null,
                'notes' => $mergedNotes,
            ]);
        }

        DB::table('pr_kasbon_payroll_deductions')
            ->where('payroll_generated_id', $payrollGeneratedId)
            ->delete();
    }

    /**
     * Terapkan potongan kasbon dari data payroll yang disimpan + update pr_kasbons.
     *
     * @param  array<int, array<string, mixed>>  $payrollData
     */
    public function applyPayrollDeductions(int $payrollGeneratedId, array $payrollData): void
    {
        if (! $this->tablesReady() || ! $this->deductionsTableReady()) {
            return;
        }

        foreach ($payrollData as $item) {
            $amount = (float) ($item['potongan_kasbon'] ?? 0);
            $kasbonId = (int) ($item['pr_kasbon_id'] ?? 0);
            $userId = (int) ($item['user_id'] ?? 0);
            $cicilanKe = (int) ($item['kasbon_cicilan_ke'] ?? 0);

            if ($amount <= 0 || $kasbonId <= 0 || $userId <= 0 || $cicilanKe <= 0) {
                continue;
            }

            $exists = DB::table('pr_kasbon_payroll_deductions')
                ->where('payroll_generated_id', $payrollGeneratedId)
                ->where('pr_kasbon_id', $kasbonId)
                ->exists();
            if ($exists) {
                continue;
            }

            DB::transaction(function () use ($payrollGeneratedId, $kasbonId, $userId, $amount, $cicilanKe) {
                $k = PrKasbon::query()->lockForUpdate()->findOrFail($kasbonId);
                if ((int) $k->employee_user_id !== $userId) {
                    throw new \RuntimeException('Kasbon tidak milik karyawan ini.');
                }
                $termin = max(1, (int) $k->termin_total);
                $paid = (int) $k->paid_installments;
                if ($k->status === 'completed' || $paid >= $termin) {
                    throw new \RuntimeException('Kasbon sudah lunas.');
                }
                if ($paid + 1 !== $cicilanKe) {
                    throw new \RuntimeException('Urutan cicilan kasbon tidak sesuai.');
                }

                $this->assertNfpPaid((int) $k->purchase_requisition_id);

                $newPaid = $paid + 1;
                $line = '[' . now()->format('Y-m-d H:i') . '] Cicilan ' . $newPaid . '/' . $termin
                    . ' via payroll #' . $payrollGeneratedId;
                $mergedNotes = trim(($k->notes ? $k->notes . "\n" : '') . $line);
                if (strlen($mergedNotes) > 2000) {
                    $mergedNotes = substr($mergedNotes, -2000);
                }

                DB::table('pr_kasbon_payroll_deductions')->insert([
                    'pr_kasbon_id' => $kasbonId,
                    'payroll_generated_id' => $payrollGeneratedId,
                    'user_id' => $userId,
                    'installment_number' => $cicilanKe,
                    'amount' => $amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $k->update([
                    'paid_installments' => $newPaid,
                    'last_installment_at' => now(),
                    'status' => $newPaid >= $termin ? 'completed' : 'active',
                    'notes' => $mergedNotes,
                ]);
            });
        }
    }

    private function assertNfpPaid(int $purchaseRequisitionId): void
    {
        $latestNfp = DB::table('non_food_payments')
            ->where('purchase_requisition_id', $purchaseRequisitionId)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->orderByDesc('id')
            ->first();

        if (! $latestNfp || ($latestNfp->status ?? '') !== 'paid') {
            throw new \RuntimeException('Non Food Payment belum paid untuk kasbon ini.');
        }
    }
}
