<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MacAnomalyTrackingController extends Controller
{
    public function index()
    {
        return Inertia::render('MacAnomalyTracking/Index');
    }

    public function data(Request $request)
    {
        $idOutlet = (int) $request->input('id_outlet');
        $jumpThresholdPercent = (float) $request->input('jump_threshold_percent', 50);

        if (!$idOutlet) {
            return response()->json([
                'status' => 'error',
                'message' => 'Outlet wajib dipilih',
            ], 422);
        }

        if ($jumpThresholdPercent < 1) {
            $jumpThresholdPercent = 1;
        }

        $latestRows = DB::table('outlet_food_inventory_cost_histories as h')
            ->join('outlet_food_inventory_items as ofii', 'h.inventory_item_id', '=', 'ofii.id')
            ->join('items as i', 'ofii.item_id', '=', 'i.id')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('outlet_food_inventory_stocks as s', function ($join) {
                $join->on('s.inventory_item_id', '=', 'h.inventory_item_id')
                    ->on('s.id_outlet', '=', 'h.id_outlet')
                    ->on('s.warehouse_outlet_id', '=', 'h.warehouse_outlet_id');
            })
            ->where('h.id_outlet', $idOutlet)
            ->whereIn('h.id', function ($query) use ($idOutlet) {
                $query->selectRaw('MAX(id)')
                    ->from('outlet_food_inventory_cost_histories')
                    ->where('id_outlet', $idOutlet)
                    ->groupBy('inventory_item_id', 'id_outlet', 'warehouse_outlet_id');
            })
            ->select(
                'h.id',
                'h.inventory_item_id',
                'h.id_outlet',
                'h.warehouse_outlet_id',
                'h.date',
                'h.reference_type',
                'h.new_cost as current_mac',
                'i.id as item_id',
                'i.name as item_name',
                'i.sku as item_code',
                'wo.name as warehouse_name',
                DB::raw('COALESCE(s.qty_small, 0) as current_qty_small'),
                DB::raw('(SELECT p.new_cost FROM outlet_food_inventory_cost_histories p WHERE p.inventory_item_id = h.inventory_item_id AND p.id_outlet = h.id_outlet AND p.warehouse_outlet_id = h.warehouse_outlet_id AND p.id < h.id ORDER BY p.id DESC LIMIT 1) as previous_mac'),
                DB::raw('(SELECT p.date FROM outlet_food_inventory_cost_histories p WHERE p.inventory_item_id = h.inventory_item_id AND p.id_outlet = h.id_outlet AND p.warehouse_outlet_id = h.warehouse_outlet_id AND p.id < h.id ORDER BY p.id DESC LIMIT 1) as previous_date')
            )
            ->orderBy('i.name')
            ->get();

        $anomalies = [];
        $severityCounter = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
        ];

        foreach ($latestRows as $row) {
            $currentMac = (float) ($row->current_mac ?? 0);
            $previousMac = $row->previous_mac !== null ? (float) $row->previous_mac : null;
            $qtySmall = (float) ($row->current_qty_small ?? 0);

            $reasons = [];
            $severity = null;
            $changePercent = null;

            if ($currentMac <= 0) {
                $reasons[] = 'MAC <= 0';
                $severity = 'critical';
            }

            if ($currentMac == 0.0 && $qtySmall > 0) {
                $reasons[] = 'MAC 0 dengan stok masih ada';
                $severity = 'critical';
            }

            if ($previousMac !== null && $previousMac > 0) {
                $changePercent = (($currentMac - $previousMac) / $previousMac) * 100;
                $absChange = abs($changePercent);

                if ($absChange >= $jumpThresholdPercent) {
                    $reasons[] = 'Perubahan MAC terlalu besar (' . number_format($changePercent, 2, '.', '') . '%)';

                    if ($absChange >= 200) {
                        $severity = 'critical';
                    } elseif ($absChange >= 100) {
                        if ($severity !== 'critical') {
                            $severity = 'high';
                        }
                    } else {
                        if (!in_array($severity, ['critical', 'high'], true)) {
                            $severity = 'medium';
                        }
                    }
                }
            }

            if (empty($reasons)) {
                continue;
            }

            $severityCounter[$severity]++;

            $anomalies[] = [
                'history_id' => (int) $row->id,
                'item_id' => (int) $row->item_id,
                'item_name' => $row->item_name,
                'item_code' => $row->item_code,
                'warehouse_name' => $row->warehouse_name ?? '-',
                'reference_type' => $row->reference_type ?? '-',
                'date' => $row->date,
                'previous_date' => $row->previous_date,
                'current_mac' => number_format($currentMac, 4, '.', ''),
                'previous_mac' => $previousMac !== null ? number_format($previousMac, 4, '.', '') : null,
                'change_percent' => $changePercent !== null ? number_format($changePercent, 2, '.', '') : null,
                'current_qty_small' => number_format($qtySmall, 4, '.', ''),
                'severity' => $severity,
                'reasons' => $reasons,
            ];
        }

        usort($anomalies, function ($a, $b) {
            $rank = ['critical' => 3, 'high' => 2, 'medium' => 1];
            $rankA = $rank[$a['severity']] ?? 0;
            $rankB = $rank[$b['severity']] ?? 0;

            if ($rankA === $rankB) {
                return strcmp($a['item_name'], $b['item_name']);
            }

            return $rankB <=> $rankA;
        });

        return response()->json([
            'status' => 'success',
            'anomalies' => $anomalies,
            'summary' => [
                'total_checked' => $latestRows->count(),
                'total_anomalies' => count($anomalies),
                'critical' => $severityCounter['critical'],
                'high' => $severityCounter['high'],
                'medium' => $severityCounter['medium'],
                'jump_threshold_percent' => $jumpThresholdPercent,
            ],
        ]);
    }
}
