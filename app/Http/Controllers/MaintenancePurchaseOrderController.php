<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MaintenancePurchaseOrder;

class MaintenancePurchaseOrderController extends Controller
{
    public function preview($id)
    {
        $po = \App\Models\MaintenancePurchaseOrder::with([
            'maintenanceTask.outlet',
            'supplier',
            'items.unit',
            'createdBy'
        ])->findOrFail($id);

        // Creator info
        $creator = $po->createdBy;

        // Siapkan signatures array
        $signatures = [
            'purchasing_manager' => [
                'official' => null,
                'approver' => null
            ],
            'gm_finance' => [
                'official' => null,
                'approver' => null
            ],
            'coo' => [
                'official' => null,
                'approver' => null
            ],
            'ceo' => [
                'official' => null,
                'approver' => null
            ]
        ];

        // Ambil official dan approver untuk setiap level
        $levels = [
            'purchasing_manager' => 168,
            'gm_finance' => 152,
            'coo' => 151,
            'ceo' => 149
        ];
        foreach ($levels as $key => $id_jabatan) {
            // Official
            $signatures[$key]['official'] = \DB::table('users as u')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->where('u.id_jabatan', $id_jabatan)
                ->where('u.status', 'A')
                ->orderBy('u.id')
                ->select('u.*', 'j.nama_jabatan')
                ->first();

            // Approver (jika sudah approve)
            $approval_by = $po->{$key . '_approval_by'};
            if ($approval_by) {
                $signatures[$key]['approver'] = \DB::table('users as u')
                    ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                    ->where('u.id', $approval_by)
                    ->select('u.*', 'j.nama_jabatan')
                    ->first();
            }
        }

        return view('maintenance.po-preview', [
            'po' => $po,
            'signatures' => $signatures
        ]);
    }
} 