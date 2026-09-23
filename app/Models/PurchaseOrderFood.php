<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderFood extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_foods';

    protected $fillable = [
        'number',
        'date',
        'supplier_id',
        'status',
        'created_by',
        'notes',
        'arrival_date',
        'purchasing_manager_approved_at',
        'purchasing_manager_approved_by',
        'purchasing_manager_note',
        'gm_finance_approved_at',
        'gm_finance_approved_by',
        'gm_finance_note',
        'ppn_enabled',
        'ppn_amount',
        'subtotal',
        'discount_total_percent',
        'discount_total_amount',
        'grand_total',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'date' => 'datetime',
        'ppn_enabled' => 'boolean',
        'ppn_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_total_percent' => 'decimal:2',
        'discount_total_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderFoodItem::class, 'purchase_order_food_id');
    }

    public function butcherPoItems()
    {
        return $this->hasMany(PurchaseOrderFoodItem::class, 'purchase_order_food_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchasing_manager()
    {
        return $this->belongsTo(User::class, 'purchasing_manager_approved_by');
    }

    public function gm_finance()
    {
        return $this->belongsTo(User::class, 'gm_finance_approved_by');
    }

    public function approvalFlows()
    {
        return $this->hasMany(PurchaseOrderFoodApprovalFlow::class, 'purchase_order_food_id');
    }

    public function purchase_requests()
    {
        return $this->belongsToMany(
            \App\Models\PurchaseRequisitionFood::class,
            'purchase_order_food_purchase_request',
            'purchase_order_food_id',
            'purchase_requisition_food_id'
        );
    }

    public function getPrNumbersAttribute()
    {
        $prItemIds = $this->items()->pluck('pr_food_item_id')->toArray();
        $prIds = \App\Models\PurchaseRequisitionFoodItem::whereIn('id', $prItemIds)->pluck('pr_food_id')->unique()->toArray();
        return \App\Models\PurchaseRequisitionFood::whereIn('id', $prIds)->pluck('pr_number')->unique()->toArray();
    }

    /**
     * Batch load approved PO Food approval history (who + when), keyed by PO id.
     *
     * @param  array<int>  $poIds
     * @return array<int, array<int, array{level:int|string, role:string, approver_name:string, approved_at:?string, status:string, comments:?string}>>
     */
    public static function getApprovalHistoriesByIds(array $poIds): array
    {
        $poIds = array_values(array_unique(array_filter(array_map('intval', $poIds))));
        if (empty($poIds)) {
            return [];
        }

        $histories = [];
        foreach ($poIds as $poId) {
            $histories[$poId] = [];
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('purchase_order_food_approval_flows')) {
            $flows = \Illuminate\Support\Facades\DB::table('purchase_order_food_approval_flows as af')
                ->leftJoin('users as u', 'af.approver_id', '=', 'u.id')
                ->whereIn('af.purchase_order_food_id', $poIds)
                ->where('af.status', 'APPROVED')
                ->select(
                    'af.purchase_order_food_id',
                    'af.approval_level',
                    'af.status',
                    'af.approved_at',
                    'af.comments',
                    'u.nama_lengkap as approver_name'
                )
                ->orderBy('af.approval_level', 'asc')
                ->orderBy('af.approved_at', 'asc')
                ->get()
                ->groupBy('purchase_order_food_id');

            foreach ($flows as $poId => $poFlows) {
                foreach ($poFlows as $flow) {
                    $histories[(int) $poId][] = [
                        'level' => $flow->approval_level,
                        'role' => 'Level ' . $flow->approval_level,
                        'approver_name' => $flow->approver_name ?: '-',
                        'approved_at' => $flow->approved_at,
                        'status' => $flow->status,
                        'comments' => $flow->comments,
                    ];
                }
            }
        }

        $needLegacyIds = array_values(array_filter($poIds, function ($poId) use ($histories) {
            return empty($histories[$poId]);
        }));

        if (!empty($needLegacyIds)) {
            $legacyRows = \Illuminate\Support\Facades\DB::table('purchase_order_foods as po')
                ->leftJoin('users as pm', 'po.purchasing_manager_approved_by', '=', 'pm.id')
                ->leftJoin('users as gm', 'po.gm_finance_approved_by', '=', 'gm.id')
                ->whereIn('po.id', $needLegacyIds)
                ->select(
                    'po.id',
                    'po.purchasing_manager_approved_at',
                    'po.gm_finance_approved_at',
                    'pm.nama_lengkap as purchasing_manager_name',
                    'gm.nama_lengkap as gm_finance_name'
                )
                ->get();

            foreach ($legacyRows as $row) {
                $history = [];
                if ($row->purchasing_manager_approved_at) {
                    $history[] = [
                        'level' => 1,
                        'role' => 'Purchasing Manager',
                        'approver_name' => $row->purchasing_manager_name ?: '-',
                        'approved_at' => $row->purchasing_manager_approved_at,
                        'status' => 'APPROVED',
                        'comments' => null,
                    ];
                }
                if ($row->gm_finance_approved_at) {
                    $history[] = [
                        'level' => 2,
                        'role' => 'GM Finance',
                        'approver_name' => $row->gm_finance_name ?: '-',
                        'approved_at' => $row->gm_finance_approved_at,
                        'status' => 'APPROVED',
                        'comments' => null,
                    ];
                }
                $histories[(int) $row->id] = $history;
            }
        }

        return $histories;
    }
} 