<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FoodFloorOrder extends Model
{
    public const EDIT_CUTOFF_HOUR = 7;

    public const EDIT_TIMEZONE = 'Asia/Jakarta';

    protected $table = 'food_floor_orders';
    protected $guarded = [];

    /**
     * Batas waktu edit: tanggal dibuat + 1 hari jam 07:00 WIB (besok pagi).
     * Contoh: buat 11 Jul kapan pun → bisa edit sampai 12 Jul 06:59.
     */
    public function editCutoffAt(): ?Carbon
    {
        if (empty($this->created_at)) {
            return null;
        }

        $created = Carbon::parse($this->created_at)->timezone(self::EDIT_TIMEZONE);

        return $created->copy()->startOfDay()->addDay()->setTime(self::EDIT_CUTOFF_HOUR, 0, 0);
    }

    public function isWithinEditWindow(?Carbon $now = null): bool
    {
        $cutoff = $this->editCutoffAt();
        if ($cutoff === null) {
            return false;
        }

        $now = $now ?? Carbon::now(self::EDIT_TIMEZONE);

        return $now->lt($cutoff);
    }

    public function canEdit(?Carbon $now = null): bool
    {
        if ($this->fo_mode === 'RO Supplier' && $this->status !== 'draft') {
            return false;
        }

        if (! in_array($this->status, ['draft', 'approved', 'submitted'], true)) {
            return false;
        }

        return $this->isWithinEditWindow($now);
    }

    public function items()
    {
        return $this->hasMany(FoodFloorOrderItem::class, 'floor_order_id');
    }

    public function outlet()
    {
        return $this->belongsTo(\App\Models\Outlet::class, 'id_outlet', 'id_outlet');
    }

    public function requester()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }

    public function foSchedule()
    {
        return $this->belongsTo(\App\Models\FOSchedule::class, 'fo_schedule_id');
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approval_by', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function warehouseDivisions()
    {
        return $this->belongsToMany(
            \App\Models\WarehouseDivision::class,
            'food_floor_order_items', // nama pivot table
            'floor_order_id',         // foreign key di pivot mengarah ke FO
            'warehouse_division_id'   // foreign key di pivot mengarah ke division
        )->distinct();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function warehouseOutlet()
    {
        return $this->belongsTo(\App\Models\WarehouseOutlet::class, 'warehouse_outlet_id');
    }

    public function approvalFlows()
    {
        return $this->hasMany(FoodFloorOrderApprovalFlow::class, 'food_floor_order_id')
            ->orderBy('approval_level');
    }
} 