<?php

namespace App\Support;

use App\Models\FoodFloorOrder;
use App\Models\FoodFloorOrderApprovalFlow;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FoodFloorOrderApprovalService
{
    public function usesCustomFlow(FoodFloorOrder $order): bool
    {
        return $order->relationLoaded('approvalFlows')
            ? $order->approvalFlows->isNotEmpty()
            : FoodFloorOrderApprovalFlow::where('food_floor_order_id', $order->id)->exists();
    }

    /** @return list<int> */
    public function validateAndNormalizeApproverIds(Request $request): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => (int) $id,
            (array) $request->input('approvers', [])
        ))));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'approvers' => ['Minimal satu approver wajib dipilih untuk RO Khusus.'],
            ]);
        }

        $validCount = User::query()
            ->whereIn('id', $ids)
            ->where('status', 'A')
            ->count();

        if ($validCount !== count($ids)) {
            throw ValidationException::withMessages([
                'approvers' => ['Approver tidak valid atau tidak aktif.'],
            ]);
        }

        return $ids;
    }

    public function syncFlows(int $floorOrderId, array $approverIds): void
    {
        FoodFloorOrderApprovalFlow::where('food_floor_order_id', $floorOrderId)->delete();

        foreach (array_values($approverIds) as $index => $approverId) {
            FoodFloorOrderApprovalFlow::create([
                'food_floor_order_id' => $floorOrderId,
                'approver_id' => (int) $approverId,
                'approval_level' => $index + 1,
                'status' => 'PENDING',
            ]);
        }
    }

    public function nextPendingFlow(FoodFloorOrder $order): ?FoodFloorOrderApprovalFlow
    {
        $flows = $order->relationLoaded('approvalFlows')
            ? $order->approvalFlows
            : $order->approvalFlows()->get();

        return $flows
            ->where('status', 'PENDING')
            ->sortBy('approval_level')
            ->first();
    }

    public function isSuperadmin(User $user): bool
    {
        return ($user->id_role === '5af56935b011a' || in_array((int) ($user->id_jabatan ?? 0), [160, 317], true))
            && $user->status === 'A';
    }

    public function canUserApprove(User $user, FoodFloorOrder $order): bool
    {
        if ($order->fo_mode !== 'RO Khusus' || $order->status !== 'submitted') {
            return false;
        }

        if ($this->usesCustomFlow($order)) {
            $next = $this->nextPendingFlow($order);
            if (! $next) {
                return false;
            }

            if ($this->isSuperadmin($user)) {
                return true;
            }

            return (int) $next->approver_id === (int) $user->id;
        }

        return $this->canUserApproveByWarehouseLegacy($user, (int) $order->warehouse_outlet_id);
    }

    public function canUserApproveByWarehouseLegacy(User $user, int $warehouseOutletId): bool
    {
        $warehouseOutlet = DB::table('warehouse_outlets')->where('id', $warehouseOutletId)->first();
        if (! $warehouseOutlet) {
            return false;
        }

        $userJabatan = $user->id_jabatan;
        if ($user->status !== 'A') {
            return false;
        }

        return match ($warehouseOutlet->name) {
            'Kitchen' => in_array($userJabatan, [163, 174, 180, 345, 346, 347, 348, 349], true),
            'Bar' => in_array($userJabatan, [175, 182, 323], true),
            'Service' => in_array($userJabatan, [176, 322, 164, 321], true),
            default => false,
        };
    }

    /** @return Collection<int, FoodFloorOrder> */
    public function filterPendingForUser(Collection $orders, User $user): Collection
    {
        $isSuperadmin = $this->isSuperadmin($user);

        return $orders->filter(function (FoodFloorOrder $order) use ($user, $isSuperadmin) {
            if ($this->usesCustomFlow($order)) {
                $next = $this->nextPendingFlow($order);
                if (! $next) {
                    return false;
                }

                return $isSuperadmin || (int) $next->approver_id === (int) $user->id;
            }

            return $isSuperadmin || $this->canUserApproveByWarehouseLegacy($user, (int) $order->warehouse_outlet_id);
        })->values();
    }

    public function notifyFirstApprover(FoodFloorOrder $order): void
    {
        if (! $this->usesCustomFlow($order)) {
            return;
        }

        $this->notifyNextApprover($order);
    }

    public function notifyNextApprover(FoodFloorOrder $order): void
    {
        $next = $this->nextPendingFlow($order);
        if (! $next || ! $next->approver_id) {
            return;
        }

        $warehouseName = $order->warehouseOutlet?->name ?? 'Warehouse';
        $orderNumber = $order->order_number ?? ('#' . $order->id);

        NotificationService::createMany([[
            'user_id' => $next->approver_id,
            'type' => 'floor_order_approval',
            'title' => 'Approval RO Khusus',
            'message' => "RO Khusus {$orderNumber} ({$warehouseName}) menunggu persetujuan Anda (level {$next->approval_level}).",
            'url' => route('floor-order.show', $order->id),
            'is_read' => 0,
        ]]);
    }

    /**
     * @return array{final: bool, rejected: bool}
     */
    public function resolveCurrentFlow(FoodFloorOrder $order, User $actor, bool $isReject, ?string $comment): array
    {
        if (! $this->usesCustomFlow($order)) {
            return ['final' => true, 'rejected' => $isReject];
        }

        $isSuperadmin = $this->isSuperadmin($actor);
        $flow = $isSuperadmin
            ? $this->nextPendingFlow($order)
            : $order->approvalFlows
                ->where('approver_id', $actor->id)
                ->where('status', 'PENDING')
                ->sortBy('approval_level')
                ->first();

        if (! $flow) {
            throw ValidationException::withMessages([
                'approval' => ['Anda tidak berwenang memproses approval RO ini.'],
            ]);
        }

        if (! $isSuperadmin && (int) $flow->approver_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'approval' => ['Bukan giliran Anda untuk approve RO ini.'],
            ]);
        }

        $nextExpected = $this->nextPendingFlow($order);
        if ($nextExpected && (int) $nextExpected->id !== (int) $flow->id) {
            throw ValidationException::withMessages([
                'approval' => ['Approval harus mengikuti urutan approver.'],
            ]);
        }

        if ($isReject) {
            $flow->update([
                'status' => 'REJECTED',
                'rejected_at' => now(),
                'comments' => $comment,
                'approver_id' => $isSuperadmin ? $actor->id : $flow->approver_id,
            ]);

            return ['final' => true, 'rejected' => true];
        }

        $flow->update([
            'status' => 'APPROVED',
            'approved_at' => now(),
            'comments' => $comment,
            'approver_id' => $isSuperadmin ? $actor->id : $flow->approver_id,
        ]);

        $stillPending = FoodFloorOrderApprovalFlow::where('food_floor_order_id', $order->id)
            ->where('status', 'PENDING')
            ->exists();

        if ($stillPending) {
            $order->refresh();
            $order->load('approvalFlows.approver', 'warehouseOutlet');
            $this->notifyNextApprover($order);

            return ['final' => false, 'rejected' => false];
        }

        return ['final' => true, 'rejected' => false];
    }
}
