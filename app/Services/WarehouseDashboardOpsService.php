<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WarehouseDashboardOpsService
{
    /**
     * @return array{date_from: string, date_to: string, label: string, month: string}
     */
    public static function resolvePeriod(?string $period): array
    {
        $month = $period && preg_match('/^\d{4}-\d{2}$/', $period)
            ? $period
            : Carbon::now()->format('Y-m');

        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $label = $start->locale('id')->translatedFormat('F Y');
        $label = mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');

        return [
            'month' => $month,
            'date_from' => $start->format('Y-m-d'),
            'date_to' => $end->format('Y-m-d'),
            'label' => $label,
        ];
    }

    /**
     * @return array{
     *   summary: list<array<string, mixed>>,
     *   recent: list<array<string, mixed>>,
     *   daily: list<array<string, mixed>>,
     * }
     */
    public function build(string $dateFrom, string $dateTo, int $warehouseId = 0): array
    {
        $summary = [
            $this->countPrFoods($dateFrom, $dateTo, $warehouseId),
            $this->countGoodReceives($dateFrom, $dateTo, $warehouseId),
            $this->countTransfers($dateFrom, $dateTo, $warehouseId),
            $this->countPackingLists($dateFrom, $dateTo, $warehouseId),
            $this->countDeliveryOrders($dateFrom, $dateTo, $warehouseId),
            $this->countRetailFood($dateFrom, $dateTo, $warehouseId),
            $this->countRetailSales($dateFrom, $dateTo, $warehouseId),
            $this->countAdjustments($dateFrom, $dateTo, $warehouseId),
            $this->countStockOpnames($dateFrom, $dateTo, $warehouseId),
            $this->countInternalUseWaste($dateFrom, $dateTo, $warehouseId),
            $this->countWarehouseSales($dateFrom, $dateTo, $warehouseId),
            $this->countOutletRejections($dateFrom, $dateTo, $warehouseId),
        ];

        $recent = $this->recentTransactions($dateFrom, $dateTo, $warehouseId, 20);
        $daily = $this->dailyCounts($dateFrom, $dateTo, $warehouseId);

        return [
            'summary' => $summary,
            'recent' => $recent,
            'daily' => $daily,
            'total_transactions' => array_sum(array_column($summary, 'count')),
        ];
    }

    /**
     * Daftar transaksi paginated untuk modal klik card.
     *
     * @return array{
     *   transactions: list<array<string, mixed>>,
     *   pagination: array<string, int>,
     *   title: string,
     *   list_route: string,
     * }
     */
    public function listTransactions(
        string $type,
        string $dateFrom,
        string $dateTo,
        int $warehouseId = 0,
        string $search = '',
        int $page = 1,
        int $perPage = 20,
    ): array {
        $page = max(1, $page);
        $perPage = min(100, max(10, $perPage));
        $search = trim($search);

        $meta = match ($type) {
            'pr_foods' => ['title' => 'Purchase Requisition', 'list_route' => '/pr-foods'],
            'food_good_receive' => ['title' => 'Penerimaan Barang', 'list_route' => '/food-good-receive'],
            'warehouse_transfer' => ['title' => 'Pindah Gudang', 'list_route' => '/warehouse-transfer'],
            'packing_list' => ['title' => 'Packing List', 'list_route' => '/packing-list'],
            'delivery_order' => ['title' => 'Delivery Order', 'list_route' => '/delivery-order'],
            'retail_warehouse_food' => ['title' => 'Warehouse Retail Food', 'list_route' => '/retail-warehouse-food'],
            'retail_warehouse_sale' => ['title' => 'Penjualan Warehouse Retail', 'list_route' => '/retail-warehouse-sale'],
            'stock_adjustment' => ['title' => 'Penyesuaian Stok', 'list_route' => '/food-inventory-adjustment'],
            'warehouse_stock_opname' => ['title' => 'Stock Opname', 'list_route' => '/warehouse-stock-opnames'],
            'internal_use_waste' => ['title' => 'Pemakaian Internal & Sampah', 'list_route' => '/internal-use-waste'],
            'warehouse_sales' => ['title' => 'Penjualan Antar Gudang', 'list_route' => '/warehouse-sales'],
            'outlet_rejection' => ['title' => 'Penolakan Outlet', 'list_route' => '/outlet-rejections'],
            default => throw new \InvalidArgumentException('Tipe transaksi tidak dikenal: ' . $type),
        };

        $rows = match ($type) {
            'pr_foods' => $this->queryPrFoods($dateFrom, $dateTo, $warehouseId, $search),
            'food_good_receive' => $this->queryGoodReceives($dateFrom, $dateTo, $search),
            'warehouse_transfer' => $this->queryTransfers($dateFrom, $dateTo, $warehouseId, $search),
            'packing_list' => $this->queryPackingLists($dateFrom, $dateTo, $warehouseId, $search),
            'delivery_order' => $this->queryDeliveryOrders($dateFrom, $dateTo, $warehouseId, $search),
            'retail_warehouse_food' => $this->queryRetailFood($dateFrom, $dateTo, $warehouseId, $search),
            'retail_warehouse_sale' => $this->queryRetailSales($dateFrom, $dateTo, $warehouseId, $search),
            'stock_adjustment' => $this->queryAdjustments($dateFrom, $dateTo, $warehouseId, $search),
            'warehouse_stock_opname' => $this->queryStockOpnames($dateFrom, $dateTo, $warehouseId, $search),
            'internal_use_waste' => $this->queryInternalUseWaste($dateFrom, $dateTo, $warehouseId, $search),
            'warehouse_sales' => $this->queryWarehouseSales($dateFrom, $dateTo, $warehouseId, $search),
            'outlet_rejection' => $this->queryOutletRejections($dateFrom, $dateTo, $warehouseId, $search),
        };

        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values()->all();

        return [
            'title' => $meta['title'],
            'list_route' => $meta['list_route'],
            'transactions' => $slice,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $lastPage,
            ],
        ];
    }

    /**
     * Detail satu transaksi + items untuk modal nested.
     *
     * @return array{
     *   header: array<string, mixed>,
     *   items: list<array<string, mixed>>,
     *   grand_total: float|null,
     * }
     */
    public function transactionDetail(string $type, int $id): array
    {
        return match ($type) {
            'pr_foods' => $this->detailPrFood($id),
            'food_good_receive' => $this->detailGoodReceive($id),
            'warehouse_transfer' => $this->detailTransfer($id),
            'packing_list' => $this->detailPackingList($id),
            'delivery_order' => $this->detailDeliveryOrder($id),
            'retail_warehouse_food' => $this->detailRetailFood($id),
            'retail_warehouse_sale' => $this->detailRetailSale($id),
            'stock_adjustment' => $this->detailAdjustment($id),
            'warehouse_stock_opname' => $this->detailStockOpname($id),
            'internal_use_waste' => $this->detailInternalUseWaste($id),
            'warehouse_sales' => $this->detailWarehouseSale($id),
            'outlet_rejection' => $this->detailOutletRejection($id),
            default => throw new \InvalidArgumentException('Tipe transaksi tidak dikenal: ' . $type),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPrFood(int $id): array
    {
        $h = DB::table('pr_foods as p')
            ->leftJoin('warehouses as w', 'w.id', '=', 'p.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.requested_by')
            ->where('p.id', $id)
            ->first([
                'p.id', 'p.pr_number as number', 'p.tanggal as date', 'p.status',
                'w.name as warehouse_name', 'u.nama_lengkap as user_name', 'p.description',
            ]);
        if (!$h) {
            throw new \InvalidArgumentException('PR tidak ditemukan');
        }

        // Ambil item PR + harga/PO terbaru per pr_food_item (jika sudah dibuat PO)
        $rows = DB::select(
            "SELECT
                pi.id as pr_item_id,
                i.name as item_name,
                i.sku as item_code,
                pi.qty,
                pi.unit,
                pi.note,
                poi.price as po_price,
                poi.total as po_total,
                poi.subtotal as po_subtotal,
                poi.quantity as po_qty,
                po.id as po_id,
                po.number as po_number,
                po.date as po_date,
                COALESCE(s_item.name, s_po.name) as po_supplier,
                u_po.nama_lengkap as po_creator
            FROM pr_food_items pi
            LEFT JOIN items i ON i.id = pi.item_id
            LEFT JOIN purchase_order_food_items poi
                ON poi.id = (
                    SELECT poi2.id
                    FROM purchase_order_food_items poi2
                    WHERE poi2.pr_food_item_id = pi.id
                    ORDER BY poi2.id DESC
                    LIMIT 1
                )
            LEFT JOIN purchase_order_foods po
                ON po.id = COALESCE(poi.purchase_order_food_id, poi.purchase_order_id)
            LEFT JOIN suppliers s_po ON s_po.id = po.supplier_id
            LEFT JOIN suppliers s_item ON s_item.id = poi.supplier_id
            LEFT JOIN users u_po ON u_po.id = po.created_by
            WHERE pi.pr_food_id = ?
            ORDER BY pi.id ASC",
            [$id]
        );

        $items = [];
        $grand = 0.0;
        $hasAmount = false;
        foreach ($rows as $r) {
            $price = $r->po_price !== null ? (float) $r->po_price : null;
            $subtotal = null;
            if ($r->po_total !== null) {
                $subtotal = (float) $r->po_total;
            } elseif ($r->po_subtotal !== null) {
                $subtotal = (float) $r->po_subtotal;
            } elseif ($price !== null) {
                $subtotal = $price * (float) $r->qty;
            }
            if ($subtotal !== null) {
                $grand += $subtotal;
                $hasAmount = true;
            }

            $items[] = [
                'name' => $r->item_name ?? '-',
                'code' => $r->item_code,
                'qty' => (float) $r->qty,
                'unit' => $r->unit ?? '-',
                'price' => $price,
                'subtotal' => $subtotal,
                'note' => $r->note,
                'po_number' => $r->po_number,
                'po_date' => $r->po_date,
                'po_creator' => $r->po_creator,
                'po_supplier' => $r->po_supplier,
                'po_url' => $r->po_id ? ('/po-foods/' . $r->po_id) : null,
            ];
        }

        return [
            'header' => [
                'type' => 'Purchase Requisition',
                'number' => $h->number,
                'date' => $h->date,
                'status' => $h->status,
                'warehouse' => $h->warehouse_name,
                'party' => $h->user_name,
                'note' => $h->description,
                'url' => '/pr-foods/' . $id,
            ],
            'items' => $items,
            'grand_total' => $hasAmount ? $grand : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailGoodReceive(int $id): array
    {
        $h = DB::table('food_good_receives as g')
            ->leftJoin('suppliers as s', 's.id', '=', 'g.supplier_id')
            ->leftJoin('users as u', 'u.id', '=', 'g.received_by')
            ->where('g.id', $id)
            ->first([
                'g.id', 'g.gr_number as number', 'g.receive_date as date',
                's.name as supplier_name', 'u.nama_lengkap as user_name', 'g.notes',
            ]);
        if (!$h) {
            throw new \InvalidArgumentException('Good Receive tidak ditemukan');
        }

        $items = DB::table('food_good_receive_items as gi')
            ->leftJoin('items as i', 'i.id', '=', 'gi.item_id')
            ->leftJoin('units as un', 'un.id', '=', 'gi.unit_id')
            ->where('gi.good_receive_id', $id)
            ->get([
                'i.name as item_name', 'i.sku as item_code',
                'gi.qty_ordered', 'gi.qty_received', 'gi.qty_rejected',
                'un.name as unit_name', 'gi.notes',
            ])
            ->map(fn ($r) => [
                'name' => $r->item_name ?? '-',
                'code' => $r->item_code,
                'qty' => (float) $r->qty_received,
                'unit' => $r->unit_name ?? '-',
                'price' => null,
                'subtotal' => null,
                'note' => trim(sprintf(
                    'Ordered %s · Rejected %s%s',
                    $r->qty_ordered,
                    $r->qty_rejected,
                    $r->notes ? ' · ' . $r->notes : ''
                )),
            ])->all();

        return [
            'header' => [
                'type' => 'Penerimaan Barang',
                'number' => $h->number,
                'date' => $h->date,
                'status' => null,
                'warehouse' => null,
                'party' => $h->supplier_name ?? $h->user_name,
                'note' => $h->notes,
                'url' => '/food-good-receive/' . $id,
            ],
            'items' => $items,
            'grand_total' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailTransfer(int $id): array
    {
        $h = DB::table('warehouse_transfers as t')
            ->leftJoin('warehouses as wf', 'wf.id', '=', 't.warehouse_from_id')
            ->leftJoin('warehouses as wt', 'wt.id', '=', 't.warehouse_to_id')
            ->where('t.id', $id)
            ->first([
                't.id', 't.transfer_number as number', 't.transfer_date as date',
                't.transfer_mode as status', 't.notes',
                DB::raw("CONCAT(COALESCE(wf.name,'?'), ' → ', COALESCE(wt.name,'?')) as warehouse_name"),
            ]);
        if (!$h) {
            throw new \InvalidArgumentException('Transfer tidak ditemukan');
        }

        $items = DB::table('warehouse_transfer_items as ti')
            ->leftJoin('items as i', 'i.id', '=', 'ti.item_id')
            ->leftJoin('units as un', 'un.id', '=', 'ti.unit_id')
            ->where('ti.warehouse_transfer_id', $id)
            ->get(['i.name as item_name', 'i.sku as item_code', 'ti.quantity', 'ti.qty_small', 'un.name as unit_name', 'ti.note', 'ti.notes'])
            ->map(fn ($r) => [
                'name' => $r->item_name ?? '-',
                'code' => $r->item_code,
                'qty' => (float) ($r->quantity ?? $r->qty_small ?? 0),
                'unit' => $r->unit_name ?? '-',
                'price' => null,
                'subtotal' => null,
                'note' => $r->note ?? $r->notes,
            ])->all();

        return [
            'header' => [
                'type' => 'Pindah Gudang',
                'number' => $h->number,
                'date' => $h->date,
                'status' => $h->status,
                'warehouse' => $h->warehouse_name,
                'party' => null,
                'note' => $h->notes,
                'url' => '/warehouse-transfer/' . $id,
            ],
            'items' => $items,
            'grand_total' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPackingList(int $id): array
    {
        $h = DB::table('packing_lists as p')
            ->leftJoin('warehouses as w', 'w.id', '=', 'p.warehouse_id')
            ->where('p.id', $id)
            ->first([
                'p.id', 'p.pl_number as number', DB::raw('DATE(p.created_at) as date'),
                'p.status', 'w.name as warehouse_name', 'p.notes',
            ]);
        if (!$h) {
            throw new \InvalidArgumentException('Packing List tidak ditemukan');
        }

        $items = DB::table('packing_list_items as pi')
            ->leftJoin('items as i', 'i.id', '=', 'pi.item_id')
            ->where('pi.packing_list_id', $id)
            ->get(['i.name as item_name', 'i.sku as item_code', 'pi.quantity'])
            ->map(fn ($r) => [
                'name' => $r->item_name ?? '-',
                'code' => $r->item_code,
                'qty' => (float) $r->quantity,
                'unit' => '-',
                'price' => null,
                'subtotal' => null,
                'note' => null,
            ])->all();

        return [
            'header' => [
                'type' => 'Packing List',
                'number' => $h->number,
                'date' => $h->date,
                'status' => $h->status,
                'warehouse' => $h->warehouse_name,
                'party' => null,
                'note' => $h->notes,
                'url' => '/packing-list/' . $id,
            ],
            'items' => $items,
            'grand_total' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailDeliveryOrder(int $id): array
    {
        $h = DB::table('delivery_orders as do')
            ->leftJoin('packing_lists as pl', 'pl.id', '=', 'do.packing_list_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'pl.warehouse_id')
            ->where('do.id', $id)
            ->first([
                'do.id', 'do.number', DB::raw('DATE(do.created_at) as date'),
                'pl.pl_number', 'w.name as warehouse_name',
            ]);
        if (!$h) {
            throw new \InvalidArgumentException('Delivery Order tidak ditemukan');
        }

        $items = DB::table('delivery_order_items as di')
            ->leftJoin('items as i', 'i.id', '=', 'di.item_id')
            ->where('di.delivery_order_id', $id)
            ->get([
                'i.name as item_name', 'i.sku as item_code',
                'di.qty_packing_list', 'di.qty_scan', 'di.unit',
            ])
            ->map(fn ($r) => [
                'name' => $r->item_name ?? '-',
                'code' => $r->item_code,
                'qty' => (float) ($r->qty_scan ?? $r->qty_packing_list ?? 0),
                'unit' => $r->unit ?? '-',
                'price' => null,
                'subtotal' => null,
                'note' => 'PL qty: ' . ($r->qty_packing_list ?? 0),
            ])->all();

        return [
            'header' => [
                'type' => 'Delivery Order',
                'number' => $h->number,
                'date' => $h->date,
                'status' => null,
                'warehouse' => $h->warehouse_name,
                'party' => $h->pl_number ? ('PL: ' . $h->pl_number) : null,
                'note' => null,
                'url' => '/delivery-order/' . $id,
            ],
            'items' => $items,
            'grand_total' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailRetailFood(int $id): array
    {
        $h = DB::table('retail_warehouse_food as r')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->where('r.id', $id)
            ->first([
                'r.id', 'r.retail_number as number', 'r.transaction_date as date',
                'r.status', 'r.total_amount', 'r.notes',
                'w.name as warehouse_name', 's.name as supplier_name',
            ]);
        if (!$h) {
            throw new \InvalidArgumentException('Retail Food tidak ditemukan');
        }

        $items = DB::table('retail_warehouse_food_items as ri')
            ->leftJoin('items as i', 'i.id', '=', 'ri.item_id')
            ->where('ri.retail_warehouse_food_id', $id)
            ->get([
                'ri.item_name', 'i.name as item_name_join', 'i.sku as item_code',
                'ri.qty', 'ri.unit', 'ri.price', 'ri.subtotal',
            ])
            ->map(fn ($r) => [
                'name' => $r->item_name ?: ($r->item_name_join ?? '-'),
                'code' => $r->item_code,
                'qty' => (float) $r->qty,
                'unit' => $r->unit ?? '-',
                'price' => $r->price !== null ? (float) $r->price : null,
                'subtotal' => $r->subtotal !== null ? (float) $r->subtotal : null,
                'note' => null,
            ])->all();

        return [
            'header' => [
                'type' => 'Warehouse Retail Food',
                'number' => $h->number,
                'date' => $h->date,
                'status' => $h->status,
                'warehouse' => $h->warehouse_name,
                'party' => $h->supplier_name,
                'note' => $h->notes,
                'url' => '/retail-warehouse-food/' . $id,
            ],
            'items' => $items,
            'grand_total' => $h->total_amount !== null ? (float) $h->total_amount : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailRetailSale(int $id): array
    {
        $h = DB::table('retail_warehouse_sales as r')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->where('r.id', $id)
            ->first([
                'r.id', 'r.number', 'r.sale_date as date', 'r.status',
                'r.total_amount', 'r.notes', 'w.name as warehouse_name',
            ]);
        if (!$h) {
            throw new \InvalidArgumentException('Retail Sale tidak ditemukan');
        }

        $items = DB::table('retail_warehouse_sale_items as ri')
            ->leftJoin('items as i', 'i.id', '=', 'ri.item_id')
            ->where('ri.retail_warehouse_sale_id', $id)
            ->get(['i.name as item_name', 'i.sku as item_code', 'ri.qty', 'ri.unit', 'ri.price', 'ri.subtotal'])
            ->map(fn ($r) => [
                'name' => $r->item_name ?? '-',
                'code' => $r->item_code,
                'qty' => (float) $r->qty,
                'unit' => $r->unit ?? '-',
                'price' => $r->price !== null ? (float) $r->price : null,
                'subtotal' => $r->subtotal !== null ? (float) $r->subtotal : null,
                'note' => null,
            ])->all();

        return [
            'header' => [
                'type' => 'Penjualan Warehouse Retail',
                'number' => $h->number,
                'date' => $h->date,
                'status' => $h->status,
                'warehouse' => $h->warehouse_name,
                'party' => null,
                'note' => $h->notes,
                'url' => '/retail-warehouse-sale/' . $id,
            ],
            'items' => $items,
            'grand_total' => $h->total_amount !== null ? (float) $h->total_amount : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailAdjustment(int $id): array
    {
        $h = DB::table('food_inventory_adjustments as a')
            ->leftJoin('warehouses as w', 'w.id', '=', 'a.warehouse_id')
            ->where('a.id', $id)
            ->first([
                'a.id', 'a.number', 'a.date', 'a.status', 'a.type', 'a.reason',
                'w.name as warehouse_name',
            ]);
        if (!$h) {
            throw new \InvalidArgumentException('Adjustment tidak ditemukan');
        }

        $items = DB::table('food_inventory_adjustment_items as ai')
            ->leftJoin('items as i', 'i.id', '=', 'ai.item_id')
            ->where('ai.adjustment_id', $id)
            ->get(['i.name as item_name', 'i.sku as item_code', 'ai.qty', 'ai.unit', 'ai.note'])
            ->map(fn ($r) => [
                'name' => $r->item_name ?? '-',
                'code' => $r->item_code,
                'qty' => (float) $r->qty,
                'unit' => $r->unit ?? '-',
                'price' => null,
                'subtotal' => null,
                'note' => $r->note,
            ])->all();

        return [
            'header' => [
                'type' => 'Penyesuaian Stok',
                'number' => $h->number,
                'date' => $h->date,
                'status' => $h->status,
                'warehouse' => $h->warehouse_name,
                'party' => $h->type,
                'note' => $h->reason,
                'url' => '/food-inventory-adjustment/' . $id,
            ],
            'items' => $items,
            'grand_total' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailStockOpname(int $id): array
    {
        $h = DB::table('warehouse_stock_opnames as s')
            ->leftJoin('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->where('s.id', $id)
            ->first([
                's.id', 's.opname_number as number', 's.opname_date as date',
                's.status', 's.notes', 'w.name as warehouse_name',
            ]);
        if (!$h) {
            throw new \InvalidArgumentException('Stock Opname tidak ditemukan');
        }

        $items = DB::table('warehouse_stock_opname_items as si')
            ->leftJoin('food_inventory_items as fii', 'fii.id', '=', 'si.inventory_item_id')
            ->leftJoin('items as i', 'i.id', '=', 'fii.item_id')
            ->where('si.stock_opname_id', $id)
            ->get([
                'i.name as item_name', 'i.sku as item_code',
                'si.qty_system_small', 'si.qty_physical_small', 'si.qty_diff_small',
                'si.mac_before', 'si.value_adjustment', 'si.reason',
            ])
            ->map(fn ($r) => [
                'name' => $r->item_name ?? '-',
                'code' => $r->item_code,
                'qty' => (float) $r->qty_physical_small,
                'unit' => 'small',
                'price' => $r->mac_before !== null ? (float) $r->mac_before : null,
                'subtotal' => $r->value_adjustment !== null ? (float) $r->value_adjustment : null,
                'note' => trim(sprintf(
                    'Sys %s · Diff %s%s',
                    $r->qty_system_small,
                    $r->qty_diff_small,
                    $r->reason ? ' · ' . $r->reason : ''
                )),
            ])->all();

        return [
            'header' => [
                'type' => 'Stock Opname',
                'number' => $h->number,
                'date' => $h->date,
                'status' => $h->status,
                'warehouse' => $h->warehouse_name,
                'party' => null,
                'note' => $h->notes,
                'url' => '/warehouse-stock-opnames/' . $id,
            ],
            'items' => $items,
            'grand_total' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailInternalUseWaste(int $id): array
    {
        $h = DB::table('internal_use_wastes as i')
            ->leftJoin('warehouses as w', 'w.id', '=', 'i.warehouse_id')
            ->leftJoin('items as it', 'it.id', '=', 'i.item_id')
            ->leftJoin('units as un', 'un.id', '=', 'i.unit_id')
            ->where('i.id', $id)
            ->first([
                'i.id', 'i.type', 'i.date', 'i.qty', 'i.notes',
                'w.name as warehouse_name', 'it.name as item_name', 'it.sku as item_code',
                'un.name as unit_name',
            ]);
        if (!$h) {
            throw new \InvalidArgumentException('Internal use/waste tidak ditemukan');
        }

        $items = [[
            'name' => $h->item_name ?? '-',
            'code' => $h->item_code,
            'qty' => (float) $h->qty,
            'unit' => $h->unit_name ?? '-',
            'price' => null,
            'subtotal' => null,
            'note' => $h->notes,
        ]];

        return [
            'header' => [
                'type' => 'Pemakaian Internal & Sampah',
                'number' => '#' . $h->id,
                'date' => $h->date,
                'status' => $h->type,
                'warehouse' => $h->warehouse_name,
                'party' => null,
                'note' => $h->notes,
                'url' => '/internal-use-waste',
            ],
            'items' => $items,
            'grand_total' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailWarehouseSale(int $id): array
    {
        $h = DB::table('warehouse_sales as s')
            ->leftJoin('warehouses as wf', 'wf.id', '=', 's.source_warehouse_id')
            ->leftJoin('warehouses as wt', 'wt.id', '=', 's.target_warehouse_id')
            ->where('s.id', $id)
            ->first([
                's.id', 's.number', 's.date', 's.status', 's.note',
                DB::raw("CONCAT(COALESCE(wf.name,'?'), ' → ', COALESCE(wt.name,'?')) as warehouse_name"),
            ]);
        if (!$h) {
            throw new \InvalidArgumentException('Warehouse Sale tidak ditemukan');
        }

        $items = DB::table('warehouse_sale_items as si')
            ->leftJoin('items as i', 'i.id', '=', 'si.item_id')
            ->where('si.warehouse_sale_id', $id)
            ->whereNull('si.deleted_at')
            ->get(['i.name as item_name', 'i.sku as item_code', 'si.qty_small', 'si.price', 'si.total', 'si.note'])
            ->map(fn ($r) => [
                'name' => $r->item_name ?? '-',
                'code' => $r->item_code,
                'qty' => (float) $r->qty_small,
                'unit' => 'small',
                'price' => $r->price !== null ? (float) $r->price : null,
                'subtotal' => $r->total !== null ? (float) $r->total : null,
                'note' => $r->note,
            ])->all();

        $grand = array_sum(array_map(fn ($i) => (float) ($i['subtotal'] ?? 0), $items));

        return [
            'header' => [
                'type' => 'Penjualan Antar Gudang',
                'number' => $h->number,
                'date' => $h->date,
                'status' => $h->status,
                'warehouse' => $h->warehouse_name,
                'party' => null,
                'note' => $h->note,
                'url' => '/warehouse-sales/' . $id,
            ],
            'items' => $items,
            'grand_total' => $grand ?: null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailOutletRejection(int $id): array
    {
        $h = DB::table('outlet_rejections as r')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'r.outlet_id')
            ->where('r.id', $id)
            ->first([
                'r.id', 'r.number', 'r.rejection_date as date', 'r.status', 'r.notes',
                'w.name as warehouse_name', 'o.nama_outlet as outlet_name',
            ]);
        if (!$h) {
            throw new \InvalidArgumentException('Outlet Rejection tidak ditemukan');
        }

        $items = DB::table('outlet_rejection_items as ri')
            ->leftJoin('items as i', 'i.id', '=', 'ri.item_id')
            ->leftJoin('units as un', 'un.id', '=', 'ri.unit_id')
            ->where('ri.outlet_rejection_id', $id)
            ->get([
                'i.name as item_name', 'i.sku as item_code',
                'ri.qty_rejected', 'un.name as unit_name',
                'ri.mac_cost', 'ri.rejection_reason', 'ri.item_condition',
            ])
            ->map(fn ($r) => [
                'name' => $r->item_name ?? '-',
                'code' => $r->item_code,
                'qty' => (float) $r->qty_rejected,
                'unit' => $r->unit_name ?? '-',
                'price' => $r->mac_cost !== null ? (float) $r->mac_cost : null,
                'subtotal' => ($r->mac_cost !== null) ? ((float) $r->mac_cost * (float) $r->qty_rejected) : null,
                'note' => trim(($r->rejection_reason ?? '') . ' ' . ($r->item_condition ?? '')),
            ])->all();

        return [
            'header' => [
                'type' => 'Penolakan Outlet',
                'number' => $h->number,
                'date' => $h->date,
                'status' => $h->status,
                'warehouse' => $h->warehouse_name,
                'party' => $h->outlet_name,
                'note' => $h->notes,
                'url' => '/outlet-rejections/' . $id,
            ],
            'items' => $items,
            'grand_total' => null,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function queryPrFoods(string $from, string $to, int $warehouseId, string $search)
    {
        $q = DB::table('pr_foods as p')
            ->leftJoin('warehouses as w', 'w.id', '=', 'p.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.requested_by')
            ->whereBetween('p.tanggal', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('p.warehouse_id', $warehouseId))
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($q2) use ($search) {
                    $q2->where('p.pr_number', 'like', "%{$search}%")
                        ->orWhere('w.name', 'like', "%{$search}%")
                        ->orWhere('u.nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('p.status', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('p.tanggal')
            ->orderByDesc('p.id')
            ->get([
                'p.id',
                'p.pr_number as number',
                'p.tanggal as date',
                'p.status',
                'w.name as warehouse_name',
                'u.nama_lengkap as user_name',
            ]);

        return $q->map(fn ($r) => [
            'id' => (int) $r->id,
            'number' => $r->number,
            'date' => $r->date,
            'status' => $r->status,
            'warehouse_name' => $r->warehouse_name ?? '—',
            'party' => $r->user_name ?? '—',
            'amount' => null,
            'url' => '/pr-foods/' . $r->id,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function queryGoodReceives(string $from, string $to, string $search)
    {
        $q = DB::table('food_good_receives as g')
            ->leftJoin('suppliers as s', 's.id', '=', 'g.supplier_id')
            ->leftJoin('users as u', 'u.id', '=', 'g.received_by')
            ->whereBetween('g.receive_date', [$from, $to])
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($q2) use ($search) {
                    $q2->where('g.gr_number', 'like', "%{$search}%")
                        ->orWhere('s.name', 'like', "%{$search}%")
                        ->orWhere('u.nama_lengkap', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('g.receive_date')
            ->orderByDesc('g.id')
            ->get([
                'g.id',
                'g.gr_number as number',
                'g.receive_date as date',
                's.name as supplier_name',
                'u.nama_lengkap as user_name',
            ]);

        return $q->map(fn ($r) => [
            'id' => (int) $r->id,
            'number' => $r->number,
            'date' => $r->date,
            'status' => null,
            'warehouse_name' => '—',
            'party' => $r->supplier_name ?? ($r->user_name ?? '—'),
            'amount' => null,
            'url' => '/food-good-receive/' . $r->id,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function queryTransfers(string $from, string $to, int $warehouseId, string $search)
    {
        $q = DB::table('warehouse_transfers as t')
            ->leftJoin('warehouses as wf', 'wf.id', '=', 't.warehouse_from_id')
            ->leftJoin('warehouses as wt', 'wt.id', '=', 't.warehouse_to_id')
            ->whereBetween('t.transfer_date', [$from, $to])
            ->when($warehouseId > 0, function ($qq) use ($warehouseId) {
                $qq->where(function ($q2) use ($warehouseId) {
                    $q2->where('t.warehouse_from_id', $warehouseId)
                        ->orWhere('t.warehouse_to_id', $warehouseId);
                });
            })
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($q2) use ($search) {
                    $q2->where('t.transfer_number', 'like', "%{$search}%")
                        ->orWhere('wf.name', 'like', "%{$search}%")
                        ->orWhere('wt.name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('t.transfer_date')
            ->orderByDesc('t.id')
            ->get([
                't.id',
                't.transfer_number as number',
                't.transfer_date as date',
                't.transfer_mode as status',
                DB::raw("CONCAT(COALESCE(wf.name,'?'), ' → ', COALESCE(wt.name,'?')) as warehouse_name"),
            ]);

        return $q->map(fn ($r) => [
            'id' => (int) $r->id,
            'number' => $r->number,
            'date' => $r->date,
            'status' => $r->status,
            'warehouse_name' => $r->warehouse_name,
            'party' => '—',
            'amount' => null,
            'url' => '/warehouse-transfer/' . $r->id,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function queryPackingLists(string $from, string $to, int $warehouseId, string $search)
    {
        $q = DB::table('packing_lists as p')
            ->leftJoin('warehouses as w', 'w.id', '=', 'p.warehouse_id')
            ->whereDate('p.created_at', '>=', $from)
            ->whereDate('p.created_at', '<=', $to)
            ->when($warehouseId > 0, fn ($qq) => $qq->where('p.warehouse_id', $warehouseId))
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($q2) use ($search) {
                    $q2->where('p.pl_number', 'like', "%{$search}%")
                        ->orWhere('w.name', 'like', "%{$search}%")
                        ->orWhere('p.status', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('p.created_at')
            ->orderByDesc('p.id')
            ->get([
                'p.id',
                'p.pl_number as number',
                DB::raw('DATE(p.created_at) as date'),
                'p.status',
                'w.name as warehouse_name',
            ]);

        return $q->map(fn ($r) => [
            'id' => (int) $r->id,
            'number' => $r->number,
            'date' => $r->date,
            'status' => $r->status,
            'warehouse_name' => $r->warehouse_name ?? '—',
            'party' => '—',
            'amount' => null,
            'url' => '/packing-list/' . $r->id,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function queryDeliveryOrders(string $from, string $to, int $warehouseId, string $search)
    {
        $q = DB::table('delivery_orders as do')
            ->leftJoin('packing_lists as pl', 'pl.id', '=', 'do.packing_list_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'pl.warehouse_id')
            ->whereDate('do.created_at', '>=', $from)
            ->whereDate('do.created_at', '<=', $to)
            ->when($warehouseId > 0, fn ($qq) => $qq->where('pl.warehouse_id', $warehouseId))
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($q2) use ($search) {
                    $q2->where('do.number', 'like', "%{$search}%")
                        ->orWhere('w.name', 'like', "%{$search}%")
                        ->orWhere('pl.pl_number', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('do.created_at')
            ->orderByDesc('do.id')
            ->get([
                'do.id',
                'do.number',
                DB::raw('DATE(do.created_at) as date'),
                'pl.pl_number as pl_number',
                'w.name as warehouse_name',
            ]);

        return $q->map(fn ($r) => [
            'id' => (int) $r->id,
            'number' => $r->number,
            'date' => $r->date,
            'status' => null,
            'warehouse_name' => $r->warehouse_name ?? '—',
            'party' => $r->pl_number ? ('PL: ' . $r->pl_number) : '—',
            'amount' => null,
            'url' => '/delivery-order/' . $r->id,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function queryRetailFood(string $from, string $to, int $warehouseId, string $search)
    {
        $q = DB::table('retail_warehouse_food as r')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->whereNull('r.deleted_at')
            ->whereBetween('r.transaction_date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('r.warehouse_id', $warehouseId))
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($q2) use ($search) {
                    $q2->where('r.retail_number', 'like', "%{$search}%")
                        ->orWhere('w.name', 'like', "%{$search}%")
                        ->orWhere('s.name', 'like', "%{$search}%")
                        ->orWhere('r.status', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('r.transaction_date')
            ->orderByDesc('r.id')
            ->get([
                'r.id',
                'r.retail_number as number',
                'r.transaction_date as date',
                'r.status',
                'r.total_amount',
                'w.name as warehouse_name',
                's.name as supplier_name',
            ]);

        return $q->map(fn ($r) => [
            'id' => (int) $r->id,
            'number' => $r->number,
            'date' => $r->date,
            'status' => $r->status,
            'warehouse_name' => $r->warehouse_name ?? '—',
            'party' => $r->supplier_name ?? '—',
            'amount' => $r->total_amount !== null ? (float) $r->total_amount : null,
            'url' => '/retail-warehouse-food/' . $r->id,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function queryRetailSales(string $from, string $to, int $warehouseId, string $search)
    {
        if (!Schema::hasTable('retail_warehouse_sales')) {
            return collect();
        }

        $q = DB::table('retail_warehouse_sales as r')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->whereBetween('r.sale_date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('r.warehouse_id', $warehouseId))
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($q2) use ($search) {
                    $q2->where('r.number', 'like', "%{$search}%")
                        ->orWhere('w.name', 'like', "%{$search}%")
                        ->orWhere('r.status', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('r.sale_date')
            ->orderByDesc('r.id')
            ->get([
                'r.id',
                'r.number',
                'r.sale_date as date',
                'r.status',
                'r.total_amount',
                'w.name as warehouse_name',
            ]);

        return $q->map(fn ($r) => [
            'id' => (int) $r->id,
            'number' => $r->number,
            'date' => $r->date,
            'status' => $r->status,
            'warehouse_name' => $r->warehouse_name ?? '—',
            'party' => '—',
            'amount' => $r->total_amount !== null ? (float) $r->total_amount : null,
            'url' => '/retail-warehouse-sale/' . $r->id,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function queryAdjustments(string $from, string $to, int $warehouseId, string $search)
    {
        $q = DB::table('food_inventory_adjustments as a')
            ->leftJoin('warehouses as w', 'w.id', '=', 'a.warehouse_id')
            ->whereBetween('a.date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('a.warehouse_id', $warehouseId))
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($q2) use ($search) {
                    $q2->where('a.number', 'like', "%{$search}%")
                        ->orWhere('w.name', 'like', "%{$search}%")
                        ->orWhere('a.status', 'like', "%{$search}%")
                        ->orWhere('a.type', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('a.date')
            ->orderByDesc('a.id')
            ->get([
                'a.id',
                'a.number',
                'a.date',
                'a.status',
                'a.type',
                'w.name as warehouse_name',
            ]);

        return $q->map(fn ($r) => [
            'id' => (int) $r->id,
            'number' => $r->number,
            'date' => $r->date,
            'status' => $r->status,
            'warehouse_name' => $r->warehouse_name ?? '—',
            'party' => $r->type ?? '—',
            'amount' => null,
            'url' => '/food-inventory-adjustment/' . $r->id,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function queryStockOpnames(string $from, string $to, int $warehouseId, string $search)
    {
        $q = DB::table('warehouse_stock_opnames as s')
            ->leftJoin('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->whereBetween('s.opname_date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('s.warehouse_id', $warehouseId))
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($q2) use ($search) {
                    $q2->where('s.opname_number', 'like', "%{$search}%")
                        ->orWhere('w.name', 'like', "%{$search}%")
                        ->orWhere('s.status', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('s.opname_date')
            ->orderByDesc('s.id')
            ->get([
                's.id',
                's.opname_number as number',
                's.opname_date as date',
                's.status',
                'w.name as warehouse_name',
            ]);

        return $q->map(fn ($r) => [
            'id' => (int) $r->id,
            'number' => $r->number,
            'date' => $r->date,
            'status' => $r->status,
            'warehouse_name' => $r->warehouse_name ?? '—',
            'party' => '—',
            'amount' => null,
            'url' => '/warehouse-stock-opnames/' . $r->id,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function queryInternalUseWaste(string $from, string $to, int $warehouseId, string $search)
    {
        $q = DB::table('internal_use_wastes as i')
            ->leftJoin('warehouses as w', 'w.id', '=', 'i.warehouse_id')
            ->leftJoin('items as it', 'it.id', '=', 'i.item_id')
            ->whereBetween('i.date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('i.warehouse_id', $warehouseId))
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($q2) use ($search) {
                    $q2->where('i.type', 'like', "%{$search}%")
                        ->orWhere('w.name', 'like', "%{$search}%")
                        ->orWhere('it.name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('i.date')
            ->orderByDesc('i.id')
            ->get([
                'i.id',
                'i.type',
                'i.date',
                'i.qty',
                'w.name as warehouse_name',
                'it.name as item_name',
            ]);

        return $q->map(fn ($r) => [
            'id' => (int) $r->id,
            'number' => '#' . $r->id . ' · ' . ($r->type ?? '—'),
            'date' => $r->date,
            'status' => $r->type,
            'warehouse_name' => $r->warehouse_name ?? '—',
            'party' => $r->item_name ?? '—',
            'amount' => null,
            'url' => '/internal-use-waste',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function queryWarehouseSales(string $from, string $to, int $warehouseId, string $search)
    {
        $q = DB::table('warehouse_sales as s')
            ->leftJoin('warehouses as wf', 'wf.id', '=', 's.source_warehouse_id')
            ->leftJoin('warehouses as wt', 'wt.id', '=', 's.target_warehouse_id')
            ->whereNull('s.deleted_at')
            ->whereBetween('s.date', [$from, $to])
            ->when($warehouseId > 0, function ($qq) use ($warehouseId) {
                $qq->where(function ($q2) use ($warehouseId) {
                    $q2->where('s.source_warehouse_id', $warehouseId)
                        ->orWhere('s.target_warehouse_id', $warehouseId);
                });
            })
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($q2) use ($search) {
                    $q2->where('s.number', 'like', "%{$search}%")
                        ->orWhere('wf.name', 'like', "%{$search}%")
                        ->orWhere('wt.name', 'like', "%{$search}%")
                        ->orWhere('s.status', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('s.date')
            ->orderByDesc('s.id')
            ->get([
                's.id',
                's.number',
                's.date',
                's.status',
                DB::raw("CONCAT(COALESCE(wf.name,'?'), ' → ', COALESCE(wt.name,'?')) as warehouse_name"),
            ]);

        return $q->map(fn ($r) => [
            'id' => (int) $r->id,
            'number' => $r->number,
            'date' => $r->date,
            'status' => $r->status,
            'warehouse_name' => $r->warehouse_name,
            'party' => '—',
            'amount' => null,
            'url' => '/warehouse-sales/' . $r->id,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function queryOutletRejections(string $from, string $to, int $warehouseId, string $search)
    {
        $q = DB::table('outlet_rejections as r')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'r.outlet_id')
            ->whereBetween('r.rejection_date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('r.warehouse_id', $warehouseId))
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($q2) use ($search) {
                    $q2->where('r.number', 'like', "%{$search}%")
                        ->orWhere('w.name', 'like', "%{$search}%")
                        ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                        ->orWhere('r.status', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('r.rejection_date')
            ->orderByDesc('r.id')
            ->get([
                'r.id',
                'r.number',
                'r.rejection_date as date',
                'r.status',
                'w.name as warehouse_name',
                'o.nama_outlet as outlet_name',
            ]);

        return $q->map(fn ($r) => [
            'id' => (int) $r->id,
            'number' => $r->number,
            'date' => $r->date,
            'status' => $r->status,
            'warehouse_name' => $r->warehouse_name ?? '—',
            'party' => $r->outlet_name ?? '—',
            'amount' => null,
            'url' => '/outlet-rejections/' . $r->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function countPrFoods(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('pr_foods')->whereBetween('tanggal', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();
        $byStatus = DB::table('pr_foods')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('tanggal', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('warehouse_id', $warehouseId))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        return $this->card('pr_foods', 'Purchase Requisition', $count, '/pr-foods', 'fa-solid fa-file-invoice', $byStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function countGoodReceives(string $from, string $to, int $warehouseId): array
    {
        // food_good_receives tidak punya warehouse_id — hitung by receive_date (filter WH diabaikan)
        $count = (int) DB::table('food_good_receives')
            ->whereBetween('receive_date', [$from, $to])
            ->count();

        return $this->card('food_good_receive', 'Penerimaan Barang', $count, '/food-good-receive', 'fa-solid fa-truck', [], $warehouseId > 0 ? 'Semua WH (GR tanpa warehouse_id)' : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function countTransfers(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('warehouse_transfers')->whereBetween('transfer_date', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where(function ($qq) use ($warehouseId) {
                $qq->where('warehouse_from_id', $warehouseId)
                    ->orWhere('warehouse_to_id', $warehouseId);
            });
        }
        $count = (int) $q->count();

        return $this->card('warehouse_transfer', 'Pindah Gudang', $count, '/warehouse-transfer', 'fa-solid fa-right-left');
    }

    /**
     * @return array<string, mixed>
     */
    private function countPackingLists(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('packing_lists')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);
        if ($warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();
        $byStatus = DB::table('packing_lists')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when($warehouseId > 0, fn ($qq) => $qq->where('warehouse_id', $warehouseId))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        return $this->card('packing_list', 'Packing List', $count, '/packing-list', 'fa-solid fa-box', $byStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function countDeliveryOrders(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('delivery_orders as do')
            ->leftJoin('packing_lists as pl', 'pl.id', '=', 'do.packing_list_id')
            ->whereDate('do.created_at', '>=', $from)
            ->whereDate('do.created_at', '<=', $to);
        if ($warehouseId > 0) {
            $q->where('pl.warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();

        return $this->card('delivery_order', 'Delivery Order', $count, '/delivery-order', 'fa-solid fa-truck-arrow-right');
    }

    /**
     * @return array<string, mixed>
     */
    private function countRetailFood(string $from, string $to, int $warehouseId): array
    {
        $base = DB::table('retail_warehouse_food')
            ->whereNull('deleted_at')
            ->whereBetween('transaction_date', [$from, $to]);
        if ($warehouseId > 0) {
            $base->where('warehouse_id', $warehouseId);
        }
        $count = (int) (clone $base)->count();
        $amount = (float) (clone $base)->sum('total_amount');
        $byStatus = (clone $base)
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $card = $this->card('retail_warehouse_food', 'Warehouse Retail Food', $count, '/retail-warehouse-food', 'fa-solid fa-warehouse', $byStatus);
        $card['amount'] = round($amount, 2);

        return $card;
    }

    /**
     * @return array<string, mixed>
     */
    private function countRetailSales(string $from, string $to, int $warehouseId): array
    {
        if (!Schema::hasTable('retail_warehouse_sales')) {
            return $this->card('retail_warehouse_sale', 'Penjualan Warehouse Retail', 0, '/retail-warehouse-sale', 'fa-solid fa-store');
        }

        $q = DB::table('retail_warehouse_sales')->whereBetween('sale_date', [$from, $to]);
        if ($warehouseId > 0 && Schema::hasColumn('retail_warehouse_sales', 'warehouse_id')) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();

        return $this->card('retail_warehouse_sale', 'Penjualan Warehouse Retail', $count, '/retail-warehouse-sale', 'fa-solid fa-store');
    }

    /**
     * @return array<string, mixed>
     */
    private function countAdjustments(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('food_inventory_adjustments')->whereBetween('date', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();
        $byStatus = DB::table('food_inventory_adjustments')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('warehouse_id', $warehouseId))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        return $this->card('stock_adjustment', 'Penyesuaian Stok', $count, '/food-inventory-adjustment', 'fa-solid fa-boxes-stacked', $byStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function countStockOpnames(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('warehouse_stock_opnames')->whereBetween('opname_date', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();
        $byStatus = DB::table('warehouse_stock_opnames')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('opname_date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('warehouse_id', $warehouseId))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        return $this->card('warehouse_stock_opname', 'Stock Opname', $count, '/warehouse-stock-opnames', 'fa-solid fa-clipboard-check', $byStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function countInternalUseWaste(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('internal_use_wastes')->whereBetween('date', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();
        $byType = DB::table('internal_use_wastes')
            ->select('type', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('warehouse_id', $warehouseId))
            ->groupBy('type')
            ->pluck('cnt', 'type')
            ->all();

        return $this->card('internal_use_waste', 'Pemakaian Internal & Sampah', $count, '/internal-use-waste', 'fa-solid fa-recycle', $byType);
    }

    /**
     * @return array<string, mixed>
     */
    private function countWarehouseSales(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('warehouse_sales')
            ->whereNull('deleted_at')
            ->whereBetween('date', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where(function ($qq) use ($warehouseId) {
                $qq->where('source_warehouse_id', $warehouseId)
                    ->orWhere('target_warehouse_id', $warehouseId);
            });
        }
        $count = (int) $q->count();
        $byStatus = DB::table('warehouse_sales')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->whereNull('deleted_at')
            ->whereBetween('date', [$from, $to])
            ->when($warehouseId > 0, function ($qq) use ($warehouseId) {
                $qq->where(function ($q2) use ($warehouseId) {
                    $q2->where('source_warehouse_id', $warehouseId)
                        ->orWhere('target_warehouse_id', $warehouseId);
                });
            })
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        return $this->card('warehouse_sales', 'Penjualan Antar Gudang', $count, '/warehouse-sales', 'fas fa-exchange-alt', $byStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function countOutletRejections(string $from, string $to, int $warehouseId): array
    {
        $q = DB::table('outlet_rejections')->whereBetween('rejection_date', [$from, $to]);
        if ($warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }
        $count = (int) $q->count();
        $byStatus = DB::table('outlet_rejections')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('rejection_date', [$from, $to])
            ->when($warehouseId > 0, fn ($qq) => $qq->where('warehouse_id', $warehouseId))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        return $this->card('outlet_rejection', 'Penolakan Outlet', $count, '/outlet-rejections', 'fas fa-undo', $byStatus);
    }

    /**
     * @param  array<string, int|string>  $byStatus
     * @return array<string, mixed>
     */
    private function card(
        string $key,
        string $label,
        int $count,
        string $route,
        string $icon,
        array $byStatus = [],
        ?string $note = null,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'route' => $route,
            'icon' => $icon,
            'by_status' => $byStatus,
            'note' => $note,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentTransactions(string $from, string $to, int $warehouseId, int $limit = 20): array
    {
        $rows = [];

        $gr = DB::table('food_good_receives')
            ->whereBetween('receive_date', [$from, $to])
            ->orderByDesc('receive_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'gr_number as number', 'receive_date as txn_date']);
        foreach ($gr as $r) {
            $rows[] = [
                'type' => 'Penerimaan Barang',
                'type_key' => 'food_good_receive',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => '—',
                'status' => null,
                'url' => '/food-good-receive/' . $r->id,
            ];
        }

        $tfQ = DB::table('warehouse_transfers as t')
            ->leftJoin('warehouses as wf', 'wf.id', '=', 't.warehouse_from_id')
            ->leftJoin('warehouses as wt', 'wt.id', '=', 't.warehouse_to_id')
            ->whereBetween('t.transfer_date', [$from, $to])
            ->when($warehouseId > 0, function ($q) use ($warehouseId) {
                $q->where(function ($qq) use ($warehouseId) {
                    $qq->where('t.warehouse_from_id', $warehouseId)
                        ->orWhere('t.warehouse_to_id', $warehouseId);
                });
            })
            ->orderByDesc('t.transfer_date')
            ->orderByDesc('t.id')
            ->limit(8)
            ->get([
                't.id',
                't.transfer_number as number',
                't.transfer_date as txn_date',
                DB::raw("CONCAT(COALESCE(wf.name,'?'), ' → ', COALESCE(wt.name,'?')) as warehouse_name"),
            ]);
        foreach ($tfQ as $r) {
            $rows[] = [
                'type' => 'Pindah Gudang',
                'type_key' => 'warehouse_transfer',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name,
                'status' => null,
                'url' => '/warehouse-transfer/' . $r->id,
            ];
        }

        $retailQ = DB::table('retail_warehouse_food as r')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->whereNull('r.deleted_at')
            ->whereBetween('r.transaction_date', [$from, $to])
            ->when($warehouseId > 0, fn ($q) => $q->where('r.warehouse_id', $warehouseId))
            ->orderByDesc('r.transaction_date')
            ->orderByDesc('r.id')
            ->limit(8)
            ->get([
                'r.id',
                'r.retail_number as number',
                'r.transaction_date as txn_date',
                'r.status',
                'w.name as warehouse_name',
                'r.total_amount',
            ]);
        foreach ($retailQ as $r) {
            $rows[] = [
                'type' => 'Retail Food',
                'type_key' => 'retail_warehouse_food',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name ?? '—',
                'status' => $r->status,
                'amount' => $r->total_amount,
                'url' => '/retail-warehouse-food/' . $r->id,
            ];
        }

        $adjQ = DB::table('food_inventory_adjustments as a')
            ->leftJoin('warehouses as w', 'w.id', '=', 'a.warehouse_id')
            ->whereBetween('a.date', [$from, $to])
            ->when($warehouseId > 0, fn ($q) => $q->where('a.warehouse_id', $warehouseId))
            ->orderByDesc('a.date')
            ->orderByDesc('a.id')
            ->limit(6)
            ->get(['a.id', 'a.number', 'a.date as txn_date', 'a.status', 'w.name as warehouse_name']);
        foreach ($adjQ as $r) {
            $rows[] = [
                'type' => 'Penyesuaian Stok',
                'type_key' => 'stock_adjustment',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name ?? '—',
                'status' => $r->status,
                'url' => '/food-inventory-adjustment/' . $r->id,
            ];
        }

        $soQ = DB::table('warehouse_stock_opnames as s')
            ->leftJoin('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->whereBetween('s.opname_date', [$from, $to])
            ->when($warehouseId > 0, fn ($q) => $q->where('s.warehouse_id', $warehouseId))
            ->orderByDesc('s.opname_date')
            ->orderByDesc('s.id')
            ->limit(6)
            ->get(['s.id', 's.opname_number as number', 's.opname_date as txn_date', 's.status', 'w.name as warehouse_name']);
        foreach ($soQ as $r) {
            $rows[] = [
                'type' => 'Stock Opname',
                'type_key' => 'warehouse_stock_opname',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name ?? '—',
                'status' => $r->status,
                'url' => '/warehouse-stock-opnames/' . $r->id,
            ];
        }

        $doQ = DB::table('delivery_orders as do')
            ->leftJoin('packing_lists as pl', 'pl.id', '=', 'do.packing_list_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'pl.warehouse_id')
            ->whereDate('do.created_at', '>=', $from)
            ->whereDate('do.created_at', '<=', $to)
            ->when($warehouseId > 0, fn ($q) => $q->where('pl.warehouse_id', $warehouseId))
            ->orderByDesc('do.created_at')
            ->orderByDesc('do.id')
            ->limit(6)
            ->get([
                'do.id',
                'do.number',
                DB::raw('DATE(do.created_at) as txn_date'),
                'w.name as warehouse_name',
            ]);
        foreach ($doQ as $r) {
            $rows[] = [
                'type' => 'Delivery Order',
                'type_key' => 'delivery_order',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name ?? '—',
                'status' => null,
                'url' => '/delivery-order/' . $r->id,
            ];
        }

        $wsQ = DB::table('warehouse_sales as s')
            ->leftJoin('warehouses as wf', 'wf.id', '=', 's.source_warehouse_id')
            ->leftJoin('warehouses as wt', 'wt.id', '=', 's.target_warehouse_id')
            ->whereNull('s.deleted_at')
            ->whereBetween('s.date', [$from, $to])
            ->when($warehouseId > 0, function ($q) use ($warehouseId) {
                $q->where(function ($qq) use ($warehouseId) {
                    $qq->where('s.source_warehouse_id', $warehouseId)
                        ->orWhere('s.target_warehouse_id', $warehouseId);
                });
            })
            ->orderByDesc('s.date')
            ->orderByDesc('s.id')
            ->limit(6)
            ->get([
                's.id',
                's.number',
                's.date as txn_date',
                's.status',
                DB::raw("CONCAT(COALESCE(wf.name,'?'), ' → ', COALESCE(wt.name,'?')) as warehouse_name"),
            ]);
        foreach ($wsQ as $r) {
            $rows[] = [
                'type' => 'Penjualan Antar Gudang',
                'type_key' => 'warehouse_sales',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name,
                'status' => $r->status,
                'url' => '/warehouse-sales/' . $r->id,
            ];
        }

        $rejQ = DB::table('outlet_rejections as r')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->whereBetween('r.rejection_date', [$from, $to])
            ->when($warehouseId > 0, fn ($q) => $q->where('r.warehouse_id', $warehouseId))
            ->orderByDesc('r.rejection_date')
            ->orderByDesc('r.id')
            ->limit(6)
            ->get(['r.id', 'r.number', 'r.rejection_date as txn_date', 'r.status', 'w.name as warehouse_name']);
        foreach ($rejQ as $r) {
            $rows[] = [
                'type' => 'Penolakan Outlet',
                'type_key' => 'outlet_rejection',
                'number' => $r->number,
                'date' => $r->txn_date,
                'warehouse_name' => $r->warehouse_name ?? '—',
                'status' => $r->status,
                'url' => '/outlet-rejections/' . $r->id,
            ];
        }

        usort($rows, fn ($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dailyCounts(string $from, string $to, int $warehouseId): array
    {
        $days = [];
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            $days[$key] = [
                'date' => $key,
                'gr' => 0,
                'transfer' => 0,
                'retail' => 0,
                'do' => 0,
                'adjustment' => 0,
            ];
            $cursor->addDay();
        }

        $grRows = DB::table('food_good_receives')
            ->select('receive_date as d', DB::raw('COUNT(*) as c'))
            ->whereBetween('receive_date', [$from, $to])
            ->groupBy('receive_date')
            ->get();
        foreach ($grRows as $r) {
            if (isset($days[$r->d])) {
                $days[$r->d]['gr'] = (int) $r->c;
            }
        }

        $tfQ = DB::table('warehouse_transfers')
            ->select('transfer_date as d', DB::raw('COUNT(*) as c'))
            ->whereBetween('transfer_date', [$from, $to])
            ->when($warehouseId > 0, function ($q) use ($warehouseId) {
                $q->where(function ($qq) use ($warehouseId) {
                    $qq->where('warehouse_from_id', $warehouseId)
                        ->orWhere('warehouse_to_id', $warehouseId);
                });
            })
            ->groupBy('transfer_date')
            ->get();
        foreach ($tfQ as $r) {
            if (isset($days[$r->d])) {
                $days[$r->d]['transfer'] = (int) $r->c;
            }
        }

        $retailRows = DB::table('retail_warehouse_food')
            ->select('transaction_date as d', DB::raw('COUNT(*) as c'))
            ->whereNull('deleted_at')
            ->whereBetween('transaction_date', [$from, $to])
            ->when($warehouseId > 0, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->groupBy('transaction_date')
            ->get();
        foreach ($retailRows as $r) {
            if (isset($days[$r->d])) {
                $days[$r->d]['retail'] = (int) $r->c;
            }
        }

        $doRows = DB::table('delivery_orders as do')
            ->leftJoin('packing_lists as pl', 'pl.id', '=', 'do.packing_list_id')
            ->select(DB::raw('DATE(do.created_at) as d'), DB::raw('COUNT(*) as c'))
            ->whereDate('do.created_at', '>=', $from)
            ->whereDate('do.created_at', '<=', $to)
            ->when($warehouseId > 0, fn ($q) => $q->where('pl.warehouse_id', $warehouseId))
            ->groupBy(DB::raw('DATE(do.created_at)'))
            ->get();
        foreach ($doRows as $r) {
            if (isset($days[$r->d])) {
                $days[$r->d]['do'] = (int) $r->c;
            }
        }

        $adjRows = DB::table('food_inventory_adjustments')
            ->select('date as d', DB::raw('COUNT(*) as c'))
            ->whereBetween('date', [$from, $to])
            ->when($warehouseId > 0, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->groupBy('date')
            ->get();
        foreach ($adjRows as $r) {
            if (isset($days[$r->d])) {
                $days[$r->d]['adjustment'] = (int) $r->c;
            }
        }

        return array_values($days);
    }
}
