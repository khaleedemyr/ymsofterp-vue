<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceBAController extends Controller
{
    public function preview($prId)
    {
        // Get PR data with task and outlet info
        $pr = DB::table('maintenance_purchase_requisitions as pr')
            ->join('maintenance_tasks as mt', 'pr.task_id', '=', 'mt.id')
            ->join('tbl_data_outlet as do', 'mt.id_outlet', '=', 'do.id_outlet')
            ->where('pr.id', $prId)
            ->select('pr.*', 'mt.task_number', 'mt.description as task_description', 'pr.description as pr_description', 'do.nama_outlet')
            ->first();

        if (!$pr) {
            abort(404);
        }

        // Get PR items with unit info
        $items = DB::table('maintenance_purchase_requisition_items as pri')
            ->leftJoin('units as u', 'pri.unit_id', '=', 'u.id')
            ->where('pri.pr_id', $prId)
            ->select('pri.*', 'u.name as unit_name')
            ->get();

        // Get signatures of officials and approvers
        $signatures = [
            'chief_engineering' => [
                'official' => DB::table('users as u')
                    ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                    ->where('u.id_jabatan', 165)
                    ->where('u.status', 'A')
                    ->select('u.nama_lengkap', 'u.signature_path', 'j.nama_jabatan', 'u.id')
                    ->first(),
                'approver' => $pr->chief_engineering_approval_by ? DB::table('users as u')
                    ->where('u.id', $pr->chief_engineering_approval_by)
                    ->value('nama_lengkap') : null
            ],
            'purchasing_manager' => [
                'official' => DB::table('users as u')
                    ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                    ->where('u.id_jabatan', 168)
                    ->where('u.status', 'A')
                    ->select('u.nama_lengkap', 'u.signature_path', 'j.nama_jabatan', 'u.id')
                    ->first(),
            ],
            'coo' => [
                'official' => DB::table('users as u')
                    ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                    ->where('u.id_jabatan', 151)
                    ->where('u.status', 'A')
                    ->select('u.nama_lengkap', 'u.signature_path', 'j.nama_jabatan', 'u.id')
                    ->first(),
                'approver' => $pr->coo_approval_by ? DB::table('users as u')
                    ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                    ->where('u.id', $pr->coo_approval_by)
                    ->select('u.nama_lengkap', 'u.signature_path', 'j.nama_jabatan', 'u.id')
                    ->first() : null
            ],
            'ceo' => [
                'official' => DB::table('users as u')
                    ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                    ->where('u.id_jabatan', 149)
                    ->where('u.status', 'A')
                    ->select('u.nama_lengkap', 'u.signature_path', 'j.nama_jabatan', 'u.id')
                    ->first(),
                'approver' => $pr->ceo_approval_by ? DB::table('users as u')
                    ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                    ->where('u.id', $pr->ceo_approval_by)
                    ->select('u.nama_lengkap', 'u.signature_path', 'j.nama_jabatan', 'u.id')
                    ->first() : null
            ]
        ];

        // Get creator info with position
        $creator = DB::table('users as u')
            ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('u.id', $pr->created_by)
            ->select('u.nama_lengkap', 'u.signature_path', 'j.nama_jabatan')
            ->first();

        // Get task media (images only)
        $taskMedia = DB::table('maintenance_media')
            ->where('task_id', $pr->task_id)
            ->where(function($query) {
                $query->where('file_type', 'image/jpeg')
                      ->orWhere('file_type', 'image/png');
            })
            ->get();

        // Get action plans with media and creator info
        $actionPlans = DB::table('action_plans as ap')
            ->join('users as u', 'ap.created_by', '=', 'u.id')
            ->where('ap.task_id', $pr->task_id)
            ->select('ap.*', 'u.nama_lengkap as created_by_name')
            ->orderBy('ap.created_at', 'desc')
            ->get();

        // Add media for each action plan
        foreach ($actionPlans as $plan) {
            $plan->media = DB::table('action_plan_media')
                ->where('action_plan_id', $plan->id)
                ->where('media_type', 'image')
                ->orderBy('created_at', 'asc')
                ->get();
        }

        // Debug information
        \Log::info('Action Plans Data:', [
            'task_id' => $pr->task_id,
            'action_plans_count' => $actionPlans->count(),
            'action_plans' => $actionPlans
        ]);

        return view('maintenance.ba-preview', compact('pr', 'items', 'signatures', 'creator', 'taskMedia', 'actionPlans'));
    }
} 