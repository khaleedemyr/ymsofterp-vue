<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardRepository
{
    public function getStats($filters = [])
    {
        $taskQuery = $this->applyFilters(DB::table('maintenance_tasks'), $filters);
        $total = (clone $taskQuery)->count();
        $done = (clone $taskQuery)->where('status', 'DONE')->count();
        $ongoing = (clone $taskQuery)->where('status', '!=', 'DONE')->count();
        $overdue = (clone $taskQuery)
            ->whereNotNull('completed_at')
            ->whereColumn('completed_at', '>', 'due_date')
            ->count();
        $taskIds = (clone $taskQuery)->pluck('id');
        $pr = DB::table('maintenance_purchase_requisitions')->whereIn('task_id', $taskIds)->count();
        $po = DB::table('maintenance_purchase_orders')->whereIn('task_id', $taskIds)->count();
        return [
            ['label' => 'Total Task', 'value' => $total],
            ['label' => 'Selesai', 'value' => $done],
            ['label' => 'Ongoing', 'value' => $ongoing],
            ['label' => 'Overdue', 'value' => $overdue],
            ['label' => 'PR', 'value' => $pr],
            ['label' => 'PO', 'value' => $po],
        ];
    }

    public function getStatusData($filters = [])
    {
        $taskQuery = $this->applyFilters(DB::table('maintenance_tasks'), $filters);
        $rows = $taskQuery->select('status', DB::raw('count(*) as total'))->groupBy('status')->get();
        $result = [];
        foreach ($rows as $row) {
            $result[$row->status] = $row->total;
        }
        return $result;
    }

    public function getTrendData($filters = [])
    {
        $months = collect(range(0, 5))->map(function ($i) {
            return Carbon::now()->subMonths(5 - $i);
        });
        $labels = $months->map(fn($m) => $m->format('M'))->toArray();
        $created = [];
        $done = [];
        foreach ($months as $month) {
            $created[] = DB::table('maintenance_tasks')
                ->whereYear('maintenance_tasks.created_at', $month->year)
                ->whereMonth('maintenance_tasks.created_at', $month->month)
                ->count();
            $done[] = DB::table('maintenance_tasks')
                ->whereYear('completed_at', $month->year)
                ->whereMonth('completed_at', $month->month)
                ->count();
        }
        return [
            'labels' => $labels,
            'created' => $created,
            'done' => $done,
        ];
    }

    public function getBarOutletData($filters = [])
    {
        $taskQuery = $this->applyFilters(DB::table('maintenance_tasks'), $filters);
        $rows = $taskQuery
            ->join('tbl_data_outlet', 'maintenance_tasks.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select('tbl_data_outlet.nama_outlet as outlet', DB::raw('count(*) as total'))
            ->groupBy('maintenance_tasks.id_outlet', 'tbl_data_outlet.nama_outlet')
            ->get();
        $result = [];
        foreach ($rows as $row) {
            $result[$row->outlet] = $row->total;
        }
        return $result;
    }

    public function getLeaderboard($filters = [])
    {
        $taskQuery = $this->applyFilters(DB::table('maintenance_tasks'), $filters);
        $taskIds = (clone $taskQuery)->pluck('id');
        $rows = DB::table('maintenance_members')
            ->join('users', 'maintenance_members.user_id', '=', 'users.id')
            ->join('maintenance_tasks', 'maintenance_members.task_id', '=', 'maintenance_tasks.id')
            ->where('maintenance_tasks.status', 'DONE')
            ->whereIn('maintenance_tasks.id', $taskIds)
            ->select('users.id', 'users.nama_lengkap as name', DB::raw('count(*) as done'))
            ->groupBy('users.id', 'users.nama_lengkap')
            ->orderByDesc('done')
            ->limit(5)
            ->get();
        return $rows->toArray();
    }

    public function getHeatmap($filters = [])
    {
        $days = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
        $weeks = [];
        for ($w = 1; $w <= 4; $w++) {
            $data = [];
            foreach ($days as $i => $day) {
                $date = Carbon::now()->startOfWeek()->addWeeks($w - 1)->addDays($i);
                $count = DB::table('maintenance_tasks')
                    ->whereDate('maintenance_tasks.created_at', $date->format('Y-m-d'))
                    ->count();
                $data[] = $count;
            }
            $weeks[] = [
                'name' => "Minggu $w",
                'data' => $data,
            ];
        }
        return $weeks;
    }

    public function getOverdueTasks($filters = [])
    {
        $taskQuery = $this->applyFilters(DB::table('maintenance_tasks'), $filters);
        $rows = $taskQuery
            ->whereNotNull('completed_at')
            ->whereColumn('completed_at', '>', 'due_date')
            ->join('tbl_data_outlet', 'maintenance_tasks.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select(
                'maintenance_tasks.id',
                'maintenance_tasks.task_number',
                'maintenance_tasks.title',
                'tbl_data_outlet.nama_outlet as outlet',
                'maintenance_tasks.due_date',
                'maintenance_tasks.completed_at'
            )
            ->get();
        $result = [];
        foreach ($rows as $row) {
            $late_days = ceil(abs(\Carbon\Carbon::parse($row->completed_at)->floatDiffInDays($row->due_date, false)));
            $members = DB::table('maintenance_members')
                ->join('users', 'maintenance_members.user_id', '=', 'users.id')
                ->where('maintenance_members.task_id', $row->id)
                ->where('maintenance_members.role', 'ASSIGNEE')
                ->pluck('users.nama_lengkap');
            $result[] = [
                'id' => $row->id,
                'task_number' => $row->task_number,
                'title' => $row->title,
                'outlet' => $row->outlet,
                'due_date' => $row->due_date ? \Carbon\Carbon::parse($row->due_date)->format('Y-m-d') : null,
                'completed_at' => $row->completed_at ? \Carbon\Carbon::parse($row->completed_at)->format('Y-m-d') : null,
                'late_days' => $late_days,
                'assigned_to' => $members,
            ];
        }
        return $result;
    }

    public function getLatestTasks($filters = [])
    {
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $perPage = isset($filters['perPage']) ? (int)$filters['perPage'] : 5;
        $query = DB::table('maintenance_tasks')
            ->join('tbl_data_outlet', 'maintenance_tasks.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->orderBy('maintenance_tasks.due_date', 'asc')
            ->select('maintenance_tasks.*', 'tbl_data_outlet.nama_outlet as outlet_name');
        if (!empty($filters['status'])) {
            $query->whereRaw('UPPER(maintenance_tasks.status) = ?', [strtoupper($filters['status'])]);
        } else {
            $query->where('maintenance_tasks.status', '!=', 'DONE');
        }
        if (!empty($filters['search'])) {
            $search = '%' . strtolower($filters['search']) . '%';
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(maintenance_tasks.task_number) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(maintenance_tasks.title) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(tbl_data_outlet.nama_outlet) LIKE ?', [$search])
                  ->orWhereExists(function($sq) use ($search) {
                      $sq->select(DB::raw(1))
                        ->from('maintenance_members')
                        ->join('users', 'maintenance_members.user_id', '=', 'users.id')
                        ->whereRaw('maintenance_members.task_id = maintenance_tasks.id')
                        ->whereRaw('LOWER(users.nama_lengkap) LIKE ?', [$search]);
                  });
            });
        }
        $total = $query->count();
        $rows = $query->forPage($page, $perPage)->get();
        $data = $rows->map(function ($row) {
            $members = DB::table('maintenance_members')
                ->join('users', 'maintenance_members.user_id', '=', 'users.id')
                ->where('maintenance_members.task_id', $row->id)
                ->where('maintenance_members.role', 'ASSIGNEE')
                ->pluck('users.nama_lengkap');
            return [
                'id' => $row->id,
                'task_number' => $row->task_number,
                'title' => $row->title,
                'outlet' => $row->outlet_name,
                'assigned_to' => $members,
                'due_date' => $row->due_date,
                'status' => $row->status,
            ];
        });
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => ceil($total / $perPage),
        ];
    }

    public function getEvidenceList($filters = [])
    {
        $rows = DB::table('maintenance_evidence')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
        return $rows->map(function ($row) {
            $photo = DB::table('maintenance_evidence_photos')->where('evidence_id', $row->id)->first();
            $video = DB::table('maintenance_evidence_videos')->where('evidence_id', $row->id)->first();
            $type = $photo ? 'photo' : ($video ? 'video' : 'photo');
            $path = $photo ? $photo->path : ($video ? $video->path : '');
            $task = DB::table('maintenance_tasks')->where('id', $row->task_id)->first();
            return [
                'id' => $row->id,
                'type' => $type,
                'url' => $path ? '/storage/' . $path : '',
                'title' => $row->notes ?? 'Evidence',
                'task_number' => $task ? $task->task_number : '',
                'outlet' => $task ? $task->id_outlet : '',
                'date' => $row->created_at ? date('Y-m-d', strtotime($row->created_at)) : '',
            ];
        });
    }

    public function getActivityList($filters = [])
    {
        $rows = DB::table('maintenance_activity_logs')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
        return $rows->map(function ($row) {
            $user = DB::table('users')->where('id', $row->user_id)->first();
            return [
                'id' => $row->id,
                'user_initials' => $user ? strtoupper(substr($user->nama_lengkap,0,2)) : '',
                'user_name' => $user ? $user->nama_lengkap : '',
                'type' => $row->activity_type,
                'description' => $row->description,
                'time_ago' => $row->created_at ? $row->created_at : '',
            ];
        });
    }

    public function getMemberBarData($filters = [])
    {
        $rows = DB::table('maintenance_members')
            ->join('users', 'maintenance_members.user_id', '=', 'users.id')
            ->select('users.nama_lengkap as name', DB::raw('count(*) as total'))
            ->groupBy('users.nama_lengkap')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
        return $rows->map(function ($row) {
            $done = DB::table('maintenance_members')
                ->join('maintenance_tasks', 'maintenance_members.task_id', '=', 'maintenance_tasks.id')
                ->where('maintenance_members.user_id', function($q) use ($row) {
                    $q->select('id')->from('users')->where('nama_lengkap', $row->name)->limit(1);
                })
                ->where('maintenance_tasks.status', 'DONE')
                ->count();
            return [
                'name' => $row->name,
                'total' => $row->total,
                'done' => $done,
            ];
        });
    }

    public function getMediaGallery($filters = [])
    {
        $rows = DB::table('maintenance_media')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();
        return $rows->map(function ($row) {
            $task = DB::table('maintenance_tasks')->where('id', $row->task_id)->first();
            $type = str_starts_with($row->file_type, 'image/') ? 'photo' : 'video';
            return [
                'id' => $row->id,
                'type' => $type,
                'url' => '/storage/' . $row->file_path,
                'title' => $row->file_name ?? 'Media',
                'task_number' => $task ? $task->task_number : '',
                'outlet' => $task ? $task->id_outlet : '',
            ];
        });
    }

    public function getPOLatest($filters = [])
    {
        $query = DB::table('maintenance_purchase_orders')
            ->leftJoin('maintenance_tasks', 'maintenance_purchase_orders.task_id', '=', 'maintenance_tasks.id')
            ->orderByDesc('maintenance_purchase_orders.created_at');
        if (!empty($filters['startDate'])) {
            $query->where('maintenance_tasks.created_at', '>=', $filters['startDate']);
        }
        if (!empty($filters['endDate'])) {
            $query->where('maintenance_tasks.created_at', '<=', $filters['endDate']);
        }
        $rows = $query->limit(5)->get();
        return $rows;
    }

    public function getCategoryData($filters = [])
    {
        $taskQuery = $this->applyFilters(DB::table('maintenance_tasks'), $filters);
        $rows = $taskQuery
            ->join('maintenance_labels', 'maintenance_tasks.label_id', '=', 'maintenance_labels.id')
            ->select('maintenance_labels.name as label', DB::raw('count(*) as total'))
            ->groupBy('maintenance_tasks.label_id', 'maintenance_labels.name')
            ->get();
        $result = [];
        foreach ($rows as $row) {
            $result[$row->label] = $row->total;
        }
        return $result;
    }

    public function getPriorityData($filters = [])
    {
        $taskQuery = $this->applyFilters(DB::table('maintenance_tasks'), $filters);
        $rows = $taskQuery
            ->join('maintenance_priorities', 'maintenance_tasks.priority_id', '=', 'maintenance_priorities.id')
            ->select('maintenance_priorities.priority as priority', DB::raw('count(*) as total'))
            ->groupBy('maintenance_tasks.priority_id', 'maintenance_priorities.priority')
            ->get();
        $result = [];
        foreach ($rows as $row) {
            $result[$row->priority] = $row->total;
        }
        return $result;
    }

    public function getTaskDetail($id)
    {
        $task = DB::table('maintenance_tasks')
            ->join('tbl_data_outlet', 'maintenance_tasks.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->leftJoin('users', 'maintenance_tasks.created_by', '=', 'users.id')
            ->where('maintenance_tasks.id', $id)
            ->select('maintenance_tasks.*', 'tbl_data_outlet.nama_outlet as outlet_name', 'users.nama_lengkap as created_by')
            ->first();
        if (!$task) return null;
        $assigned = DB::table('maintenance_members')
            ->join('users', 'maintenance_members.user_id', '=', 'users.id')
            ->where('maintenance_members.task_id', $id)
            ->where('maintenance_members.role', 'ASSIGNEE')
            ->pluck('users.nama_lengkap');
        $attachments = DB::table('maintenance_media')->where('task_id', $id)->get();
        $documents = DB::table('maintenance_documents')->where('task_id', $id)->get();
        $action_plans = DB::table('action_plans')->where('task_id', $id)->get()->map(function($ap) {
            $media = DB::table('action_plan_media')->where('action_plan_id', $ap->id)->get();
            return array_merge((array)$ap, ['media' => $media]);
        });
        $pr = DB::table('maintenance_purchase_requisitions')->where('task_id', $id)->get()->map(function($pr) {
            $items = DB::table('maintenance_purchase_requisition_items')->where('pr_id', $pr->id)->get();
            return array_merge((array)$pr, ['items' => $items]);
        });
        $po = DB::table('maintenance_purchase_orders')->where('task_id', $id)->get()->map(function($po) {
            $items = DB::table('maintenance_purchase_order_items')->where('po_id', $po->id)->get();
            $invoices = DB::table('maintenance_purchase_order_invoices')->where('po_id', $po->id)->get();
            $receives = DB::table('maintenance_purchase_order_receives')->where('po_id', $po->id)->get();
            return array_merge((array)$po, ['items' => $items, 'invoices' => $invoices, 'receives' => $receives]);
        });
        $retail = DB::table('retail')->where('task_id', $id)->get()->map(function($r) {
            $items = DB::table('retail_items')->where('retail_id', $r->id)->get()->map(function($item) {
                $invoice_images = DB::table('retail_invoice_images')->where('retail_item_id', $item->id)->get();
                $barang_images = DB::table('retail_barang_images')->where('retail_item_id', $item->id)->get();
                return array_merge((array)$item, [
                    'invoice_images' => $invoice_images,
                    'barang_images' => $barang_images
                ]);
            });
            // Gabungkan semua invoice_images dari semua item
            $all_invoice_images = collect($items)->flatMap(function($item) {
                return $item['invoice_images'];
            })->values();
            return array_merge((array)$r, [
                'items' => $items,
                'all_invoice_images' => $all_invoice_images
            ]);
        });
        $evidence = DB::table('maintenance_evidence')->where('task_id', $id)->get()->map(function($ev) {
            $photos = DB::table('maintenance_evidence_photos')->where('evidence_id', $ev->id)->get();
            $videos = DB::table('maintenance_evidence_videos')->where('evidence_id', $ev->id)->get();
            return array_merge((array)$ev, ['photos' => $photos, 'videos' => $videos]);
        });
        return [
            'info' => $task,
            'assigned_to' => $assigned,
            'attachments' => $attachments,
            'documents' => $documents,
            'action_plans' => $action_plans,
            'pr' => $pr,
            'po' => $po,
            'retail' => $retail,
            'evidence' => $evidence,
        ];
    }

    public function getDoneTasks($filters = [])
    {
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $perPage = isset($filters['perPage']) ? (int)$filters['perPage'] : 5;
        $query = DB::table('maintenance_tasks')
            ->join('tbl_data_outlet', 'maintenance_tasks.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->whereRaw('UPPER(maintenance_tasks.status) = ?', ['DONE'])
            ->orderBy('maintenance_tasks.due_date', 'asc')
            ->select('maintenance_tasks.*', 'tbl_data_outlet.nama_outlet as outlet_name');
        if (!empty($filters['search'])) {
            $search = '%' . strtolower($filters['search']) . '%';
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(maintenance_tasks.task_number) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(maintenance_tasks.title) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(tbl_data_outlet.nama_outlet) LIKE ?', [$search])
                  ->orWhereExists(function($sq) use ($search) {
                      $sq->select(DB::raw(1))
                        ->from('maintenance_members')
                        ->join('users', 'maintenance_members.user_id', '=', 'users.id')
                        ->whereRaw('maintenance_members.task_id = maintenance_tasks.id')
                        ->whereRaw('LOWER(users.nama_lengkap) LIKE ?', [$search]);
                  });
            });
        }
        $total = $query->count();
        $rows = $query->forPage($page, $perPage)->get();
        $data = $rows->map(function ($row) {
            $members = DB::table('maintenance_members')
                ->join('users', 'maintenance_members.user_id', '=', 'users.id')
                ->where('maintenance_members.task_id', $row->id)
                ->where('maintenance_members.role', 'ASSIGNEE')
                ->pluck('users.nama_lengkap');
            return [
                'id' => $row->id,
                'task_number' => $row->task_number,
                'title' => $row->title,
                'outlet' => $row->outlet_name,
                'assigned_to' => $members,
                'due_date' => $row->due_date,
                'status' => $row->status,
            ];
        });
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => ceil($total / $perPage),
        ];
    }

    public function getDoneTasksLeaderboard($filters = [])
    {
        $taskQuery = $this->applyFilters(DB::table('maintenance_tasks'), $filters);
        $taskIds = (clone $taskQuery)->pluck('id');
        $members = DB::table('maintenance_members')
            ->join('users', 'maintenance_members.user_id', '=', 'users.id')
            ->where('maintenance_members.role', 'ASSIGNEE')
            ->whereIn('maintenance_members.task_id', $taskIds)
            ->select('users.id', 'users.nama_lengkap as name')
            ->groupBy('users.id', 'users.nama_lengkap')
            ->get();
        $result = [];
        foreach ($members as $member) {
            $total = DB::table('maintenance_members')
                ->where('role', 'ASSIGNEE')
                ->where('user_id', $member->id)
                ->whereIn('task_id', $taskIds)
                ->count();
            $done = DB::table('maintenance_members')
                ->join('maintenance_tasks', 'maintenance_members.task_id', '=', 'maintenance_tasks.id')
                ->where('maintenance_members.role', 'ASSIGNEE')
                ->where('maintenance_members.user_id', $member->id)
                ->whereRaw('UPPER(maintenance_tasks.status) = ?', ['DONE'])
                ->whereIn('maintenance_tasks.id', $taskIds)
                ->count();
            $produktivitas = $total > 0 ? round(($done / $total) * 100, 2) : 0;
            $result[] = [
                'id' => $member->id,
                'name' => $member->name,
                'total' => $total,
                'done' => $done,
                'produktivitas' => $produktivitas,
            ];
        }
        usort($result, function($a, $b) {
            return $b['done'] <=> $a['done'];
        });
        return $result;
    }

    public function getPOLatestWithDetail($filters = [])
    {
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $perPage = isset($filters['perPage']) ? (int)$filters['perPage'] : 5;
        $search = isset($filters['search']) ? $filters['search'] : '';

        $query = DB::table('maintenance_purchase_orders')
            ->leftJoin('maintenance_tasks', 'maintenance_purchase_orders.task_id', '=', 'maintenance_tasks.id')
            ->leftJoin('tbl_data_outlet', 'maintenance_tasks.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->leftJoin('suppliers', 'maintenance_purchase_orders.supplier_id', '=', 'suppliers.id')
            ->leftJoin('users', 'maintenance_purchase_orders.created_by', '=', 'users.id')
            ->select('maintenance_purchase_orders.*', 'suppliers.name as supplier_name', 'tbl_data_outlet.nama_outlet', 'users.nama_lengkap as created_by', 'maintenance_tasks.task_number', 'maintenance_tasks.title');

        if (!empty($search)) {
            $search = '%' . strtolower($search) . '%';
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(maintenance_purchase_orders.po_number) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(suppliers.name) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(tbl_data_outlet.nama_outlet) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(maintenance_purchase_orders.status) LIKE ?', [$search]);
            });
        }

        $total = $query->count();
        $lastPage = ceil($total / $perPage);

        $poList = $query->orderByDesc('maintenance_purchase_orders.created_at')
            ->forPage($page, $perPage)
            ->get()
            ->map(function($po) {
                $items = DB::table('maintenance_purchase_order_items')->where('po_id', $po->id)->get();
                $invoices = DB::table('maintenance_purchase_order_invoices')->where('po_id', $po->id)->get();
                $receives = DB::table('maintenance_purchase_order_receives')->where('po_id', $po->id)->get();
                // Ambil nama approver dan notes
                $purchasing_manager = $po->purchasing_manager_approval_by ? DB::table('users')->where('id', $po->purchasing_manager_approval_by)->value('nama_lengkap') : null;
                $gm_finance = $po->gm_finance_approval_by ? DB::table('users')->where('id', $po->gm_finance_approval_by)->value('nama_lengkap') : null;
                $coo = $po->coo_approval_by ? DB::table('users')->where('id', $po->coo_approval_by)->value('nama_lengkap') : null;
                $ceo = $po->ceo_approval_by ? DB::table('users')->where('id', $po->ceo_approval_by)->value('nama_lengkap') : null;
                return array_merge((array)$po, [
                    'items' => $items,
                    'invoices' => $invoices,
                    'receives' => $receives,
                    'purchasing_manager_approver' => $purchasing_manager,
                    'gm_finance_approver' => $gm_finance,
                    'coo_approver' => $coo,
                    'ceo_approver' => $ceo,
                    'purchasing_manager_approval_notes' => $po->purchasing_manager_approval_notes,
                    'gm_finance_approval_notes' => $po->gm_finance_approval_notes,
                    'coo_approval_notes' => $po->coo_approval_notes,
                    'ceo_approval_notes' => $po->ceo_approval_notes,
                    'task_number' => $po->task_number,
                    'title' => $po->title
                ]);
            });

        return [
            'data' => $poList,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => $lastPage,
        ];
    }

    public function getPRLatestWithDetail($filters = [])
    {
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $perPage = isset($filters['perPage']) ? (int)$filters['perPage'] : 5;
        $search = isset($filters['search']) ? $filters['search'] : '';

        $query = DB::table('maintenance_purchase_requisitions')
            ->leftJoin('maintenance_tasks', 'maintenance_purchase_requisitions.task_id', '=', 'maintenance_tasks.id')
            ->leftJoin('tbl_data_outlet', 'maintenance_tasks.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->leftJoin('users', 'maintenance_purchase_requisitions.created_by', '=', 'users.id')
            ->select('maintenance_purchase_requisitions.*', 'maintenance_tasks.task_number', 'maintenance_tasks.title', 'tbl_data_outlet.nama_outlet', 'users.nama_lengkap as created_by');

        if (!empty($filters['startDate'])) {
            $query->where('maintenance_tasks.created_at', '>=', $filters['startDate']);
        }
        if (!empty($filters['endDate'])) {
            $query->where('maintenance_tasks.created_at', '<=', $filters['endDate']);
        }
        if (!empty($search)) {
            $search = '%' . strtolower($search) . '%';
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(maintenance_purchase_requisitions.pr_number) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(maintenance_tasks.task_number) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(tbl_data_outlet.nama_outlet) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(maintenance_purchase_requisitions.status) LIKE ?', [$search]);
            });
        }

        $total = $query->count();
        $lastPage = ceil($total / $perPage);

        $prList = $query->orderByDesc('maintenance_purchase_requisitions.created_at')
            ->forPage($page, $perPage)
            ->get()
            ->map(function($pr) {
                $items = DB::table('maintenance_purchase_requisition_items')->where('pr_id', $pr->id)->get();
                $chief_engineering = $pr->chief_engineering_approval_by ? DB::table('users')->where('id', $pr->chief_engineering_approval_by)->value('nama_lengkap') : null;
                $coo = $pr->coo_approval_by ? DB::table('users')->where('id', $pr->coo_approval_by)->value('nama_lengkap') : null;
                $ceo = $pr->ceo_approval_by ? DB::table('users')->where('id', $pr->ceo_approval_by)->value('nama_lengkap') : null;
                return array_merge((array)$pr, [
                    'items' => $items,
                    'chief_engineering_approver' => $chief_engineering,
                    'coo_approver' => $coo,
                    'ceo_approver' => $ceo,
                    'title' => $pr->title
                ]);
            });

        return [
            'data' => $prList,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => $lastPage,
        ];
    }

    public function getRetailLatestWithDetail($filters = [])
    {
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $perPage = isset($filters['perPage']) ? (int)$filters['perPage'] : 5;
        $search = isset($filters['search']) ? $filters['search'] : '';

        $query = DB::table('retail')
            ->leftJoin('users', 'retail.created_by', '=', 'users.id')
            ->leftJoin('maintenance_tasks', 'retail.task_id', '=', 'maintenance_tasks.id')
            ->leftJoin('tbl_data_outlet', 'maintenance_tasks.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select('retail.*', 'users.nama_lengkap as created_by', 'maintenance_tasks.task_number', 'maintenance_tasks.title', 'tbl_data_outlet.nama_outlet');

        if (!empty($filters['startDate'])) {
            $query->where('maintenance_tasks.created_at', '>=', $filters['startDate']);
        }
        if (!empty($filters['endDate'])) {
            $query->where('maintenance_tasks.created_at', '<=', $filters['endDate']);
        }
        if (!empty($search)) {
            $search = '%' . strtolower($search) . '%';
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(retail.nama_toko) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(retail.alamat_toko) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(users.nama_lengkap) LIKE ?', [$search])
                  ->orWhereExists(function($sq) use ($search) {
                      $sq->select(DB::raw(1))
                        ->from('retail_items')
                        ->whereRaw('retail_items.retail_id = retail.id')
                        ->whereRaw('LOWER(retail_items.nama_barang) LIKE ?', [$search]);
                  });
            });
        }

        $total = $query->count();
        $lastPage = ceil($total / $perPage);

        $retailList = $query->orderByDesc('retail.created_at')
            ->forPage($page, $perPage)
            ->get()
            ->map(function($r) {
                $items = DB::table('retail_items')->where('retail_id', $r->id)->get()->map(function($item) {
                    $invoice_images = DB::table('retail_invoice_images')->where('retail_item_id', $item->id)->get();
                    $barang_images = DB::table('retail_barang_images')->where('retail_item_id', $item->id)->get();
                    return array_merge((array)$item, [
                        'invoice_images' => $invoice_images,
                        'barang_images' => $barang_images
                    ]);
                });
                $total_amount = collect($items)->sum('subtotal');
                return array_merge((array)$r, [
                    'items' => $items,
                    'task_number' => $r->task_number,
                    'nama_outlet' => $r->nama_outlet,
                    'title' => $r->title,
                    'total_amount' => $total_amount
                ]);
            });

        return [
            'data' => $retailList,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => $lastPage,
        ];
    }

    public function getActivityLatestWithDetail($filters = [])
    {
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $perPage = isset($filters['perPage']) ? (int)$filters['perPage'] : 10;
        $search = isset($filters['search']) ? $filters['search'] : '';

        $query = DB::table('maintenance_activity_logs')
            ->leftJoin('users', 'maintenance_activity_logs.user_id', '=', 'users.id')
            ->select('maintenance_activity_logs.*', 'users.nama_lengkap');

        if (!empty($search)) {
            $search = '%' . strtolower($search) . '%';
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(users.nama_lengkap) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(maintenance_activity_logs.activity_type) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(maintenance_activity_logs.description) LIKE ?', [$search]);
            });
        }

        $total = $query->count();
        $lastPage = ceil($total / $perPage);

        $activityList = $query->orderByDesc('maintenance_activity_logs.created_at')
            ->forPage($page, $perPage)
            ->get()
            ->map(function($row) {
                $user_initials = collect(explode(' ', $row->nama_lengkap))->map(function($part) {
                    return mb_strtoupper(mb_substr($part, 0, 1));
                })->implode('');
                return [
                    'id' => $row->id,
                    'user_initials' => $user_initials,
                    'user_name' => $row->nama_lengkap,
                    'type' => $row->activity_type,
                    'description' => $row->description,
                    'time_ago' => $row->created_at ? $row->created_at : '',
                ];
            });

        return [
            'data' => $activityList,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => $lastPage,
        ];
    }

    public function getAllOverdueTasks($filters = [])
    {
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $perPage = isset($filters['perPage']) ? (int)$filters['perPage'] : 10;
        $search = isset($filters['search']) ? $filters['search'] : '';

        $query = DB::table('maintenance_tasks')
            ->whereNotNull('completed_at')
            ->whereColumn('completed_at', '>', 'due_date')
            ->join('tbl_data_outlet', 'maintenance_tasks.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select(
                'maintenance_tasks.id',
                'maintenance_tasks.task_number',
                'maintenance_tasks.title',
                'tbl_data_outlet.nama_outlet as outlet',
                'maintenance_tasks.due_date',
                'maintenance_tasks.completed_at'
            );

        if (!empty($search)) {
            $searchLike = '%' . strtolower($search) . '%';
            $query->where(function($q) use ($searchLike) {
                $q->whereRaw('LOWER(maintenance_tasks.task_number) LIKE ?', [$searchLike])
                  ->orWhereRaw('LOWER(maintenance_tasks.title) LIKE ?', [$searchLike])
                  ->orWhereRaw('LOWER(tbl_data_outlet.nama_outlet) LIKE ?', [$searchLike]);
            });
        }

        $total = $query->count();
        $rows = $query->orderByDesc('maintenance_tasks.completed_at')->forPage($page, $perPage)->get();
        $data = [];
        foreach ($rows as $row) {
            $late_days = ceil(abs(\Carbon\Carbon::parse($row->completed_at)->floatDiffInDays($row->due_date, false)));
            $members = DB::table('maintenance_members')
                ->join('users', 'maintenance_members.user_id', '=', 'users.id')
                ->where('maintenance_members.task_id', $row->id)
                ->where('maintenance_members.role', 'ASSIGNEE')
                ->pluck('users.nama_lengkap');
            $data[] = [
                'id' => $row->id,
                'task_number' => $row->task_number,
                'title' => $row->title,
                'outlet' => $row->outlet,
                'due_date' => $row->due_date ? \Carbon\Carbon::parse($row->due_date)->format('Y-m-d') : null,
                'completed_at' => $row->completed_at ? \Carbon\Carbon::parse($row->completed_at)->format('Y-m-d') : null,
                'late_days' => $late_days,
                'assigned_to' => $members,
            ];
        }
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => ceil($total / $perPage),
        ];
    }

    public function getTaskCompletionStats($filters = [])
    {
        $taskQuery = $this->applyFilters(DB::table('maintenance_tasks'), $filters);
        $total = (clone $taskQuery)->count();
        $done = (clone $taskQuery)->where('status', 'DONE')->count();
        $not_done = $total - $done;
        return [
            'total' => $total,
            'done' => $done,
            'not_done' => $not_done,
        ];
    }

    public function getTaskByDueDateStats($filters = [])
    {
        $taskQuery = $this->applyFilters(DB::table('maintenance_tasks'), $filters);
        $total_done = (clone $taskQuery)->where('status', 'DONE')->count();
        $on_time = (clone $taskQuery)
            ->where('status', 'DONE')
            ->whereColumn('completed_at', '<=', 'due_date')
            ->count();
        $late = (clone $taskQuery)
            ->where('status', 'DONE')
            ->whereColumn('completed_at', '>', 'due_date')
            ->count();
        return [
            'total_done' => $total_done,
            'on_time' => $on_time,
            'late' => $late,
        ];
    }

    public function getTaskCountPerMember($filters = []) {
        $query = DB::table('users')
            ->where('division_id', 20)
            ->where('status', 'A');
        $users = $query->get(['id', 'nama_lengkap']);
        $result = [];
        foreach ($users as $user) {
            $taskQuery = DB::table('maintenance_members')
                ->join('maintenance_tasks', 'maintenance_members.task_id', '=', 'maintenance_tasks.id')
                ->where('maintenance_members.user_id', $user->id)
                ->where('maintenance_members.role', 'ASSIGNEE');
            if (!empty($filters['startDate'])) {
                $taskQuery->where('maintenance_tasks.created_at', '>=', $filters['startDate']);
            }
            if (!empty($filters['endDate'])) {
                $taskQuery->where('maintenance_tasks.created_at', '<=', $filters['endDate']);
            }
            $result[] = [
                'id' => $user->id,
                'name' => $user->nama_lengkap,
                'total_task' => $taskQuery->count(),
            ];
        }
        return $result;
    }

    protected function applyFilters($query, $filters)
    {
        if (!empty($filters['outlet'])) {
            $query->where('id_outlet', $filters['outlet']);
        }
        if (!empty($filters['startDate'])) {
            $query->where('maintenance_tasks.created_at', '>=', $filters['startDate']);
        }
        if (!empty($filters['endDate'])) {
            $query->where('maintenance_tasks.created_at', '<=', $filters['endDate']);
        }
        if (!empty($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }
        if (!empty($filters['priority'])) {
            $query->where('priority_id', $filters['priority']);
        }
        if (!empty($filters['member'])) {
            $query->whereExists(function($q) use ($filters) {
                $q->select(DB::raw(1))
                  ->from('maintenance_members')
                  ->whereRaw('maintenance_members.task_id = maintenance_tasks.id')
                  ->where('maintenance_members.user_id', $filters['member']);
            });
        }
        return $query;
    }
} 