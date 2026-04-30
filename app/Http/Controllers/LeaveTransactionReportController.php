<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LeaveTransactionReportController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth()->user();
        $userId = $request->input('user_id');
        $type = $request->input('type', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 25);

        $query = DB::table('leave_transactions as lt')
            ->leftJoin('users as u', 'lt.user_id', '=', 'u.id')
            ->leftJoin('users as c', 'lt.created_by', '=', 'c.id')
            ->selectRaw('
                lt.id,
                lt.user_id,
                u.nik,
                u.nama_lengkap,
                lt.transaction_type,
                lt.amount,
                lt.balance_after,
                lt.description,
                lt.created_at,
                c.nama_lengkap as created_by_name
            ');

        $this->applyFilters($query, $authUser, $userId, $type, $dateFrom, $dateTo, $search);

        $summaryQuery = clone $query;

        $rows = $query
            ->orderByDesc('lt.created_at')
            ->paginate($perPage)
            ->withQueryString();

        $summary = [
            'total_transactions' => (clone $summaryQuery)->count(),
            'total_credit_days' => (float) ((clone $summaryQuery)->where('lt.amount', '>', 0)->sum('lt.amount') ?? 0),
            'total_usage_days' => abs((float) ((clone $summaryQuery)->where('lt.amount', '<', 0)->sum('lt.amount') ?? 0)),
            'total_users' => (clone $summaryQuery)->distinct('lt.user_id')->count('lt.user_id'),
        ];

        $users = DB::table('users')
            ->select('id', 'nik', 'nama_lengkap')
            ->whereIn('status', ['A', 'B'])
            ->orderBy('nama_lengkap')
            ->get();

        return Inertia::render('Users/LeaveTransactionReport', [
            'rows' => $rows,
            'users' => $users,
            'summary' => $summary,
            'filters' => [
                'user_id' => $userId ?? '',
                'type' => $type,
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'search' => $search ?? '',
                'per_page' => $perPage,
            ],
            'authUser' => [
                'id' => $authUser?->id,
                'id_outlet' => $authUser?->id_outlet,
            ],
        ]);
    }

    private function applyFilters($query, $authUser, $userId, $type, $dateFrom, $dateTo, $search): void
    {
        if ($authUser && $authUser->id_outlet && (int) $authUser->id_outlet !== 1) {
            $query->where('u.id_outlet', $authUser->id_outlet);
        }

        if ($userId !== null && $userId !== '') {
            $query->where('lt.user_id', $userId);
        }

        if ($type === 'credit') {
            $query->whereIn('lt.transaction_type', ['monthly_credit', 'initial_balance']);
        } elseif ($type === 'usage') {
            $query->where('lt.transaction_type', 'leave_usage');
        } elseif ($type === 'adjustment') {
            $query->where('lt.transaction_type', 'manual_adjustment');
        } elseif ($type === 'burning') {
            $query->where('lt.transaction_type', 'burning');
        }

        if ($dateFrom) {
            $query->whereDate('lt.created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('lt.created_at', '<=', $dateTo);
        }

        if ($search) {
            $s = '%' . $search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('u.nama_lengkap', 'like', $s)
                    ->orWhere('u.nik', 'like', $s)
                    ->orWhere('lt.description', 'like', $s);
            });
        }
    }
}

