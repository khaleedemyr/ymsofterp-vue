<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class MemberReportsController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->subMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        // Get summary data
        $summary = $this->getSummaryData($dateFrom, $dateTo);
        
        // Get top 20 members by transaction value
        $topByValue = $this->getTopMembersByValue($dateFrom, $dateTo);
        
        // Get top 20 members by frequency
        $topByFrequency = $this->getTopMembersByFrequency($dateFrom, $dateTo);

        return Inertia::render('Crm/MemberReports', [
            'reportData' => [
                'summary' => $summary,
                'top_by_value' => $topByValue,
                'top_by_frequency' => $topByFrequency,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    private function getSummaryData($dateFrom, $dateTo)
    {
        $summary = DB::connection('mysql_second')
            ->table('point as p')
            ->join('costumers as c', 'p.costumer_id', '=', 'c.id')
            ->whereBetween('p.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->selectRaw('
                COUNT(DISTINCT c.id) as total_members,
                COUNT(p.id) as total_transactions,
                SUM(p.jml_trans) as total_value
            ')
            ->first();

        return [
            'total_members' => $summary->total_members ?? 0,
            'total_transactions' => $summary->total_transactions ?? 0,
            'total_value' => $summary->total_value ?? 0,
            'total_value_formatted' => 'Rp ' . number_format($summary->total_value ?? 0, 0, ',', '.'),
        ];
    }

    private function getTopMembersByValue($dateFrom, $dateTo)
    {
        return DB::connection('mysql_second')
            ->table('point as p')
            ->join('costumers as c', 'p.costumer_id', '=', 'c.id')
            ->whereBetween('p.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->selectRaw('
                c.id as customer_id,
                c.name as customer_name,
                c.telepon as phone,
                c.email,
                COUNT(p.id) as transaction_count,
                SUM(p.jml_trans) as total_value,
                AVG(p.jml_trans) as average_value
            ')
            ->groupBy('c.id', 'c.name', 'c.telepon', 'c.email')
            ->having('transaction_count', '>', 0)
            ->orderBy('total_value', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($member) {
                return [
                    'customer_id' => $member->customer_id,
                    'customer_name' => $member->customer_name,
                    'phone' => $member->phone,
                    'email' => $member->email,
                    'transaction_count' => $member->transaction_count,
                    'total_value' => $member->total_value,
                    'average_value' => $member->average_value,
                ];
            });
    }

    private function getTopMembersByFrequency($dateFrom, $dateTo)
    {
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);
        $totalDays = $startDate->diffInDays($endDate) + 1;

        return DB::connection('mysql_second')
            ->table('point as p')
            ->join('costumers as c', 'p.costumer_id', '=', 'c.id')
            ->whereBetween('p.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->selectRaw('
                c.id as customer_id,
                c.name as customer_name,
                c.telepon as phone,
                c.email,
                COUNT(p.id) as transaction_count,
                SUM(p.jml_trans) as total_value,
                COUNT(p.id) / ? as frequency_per_day
            ', [$totalDays])
            ->groupBy('c.id', 'c.name', 'c.telepon', 'c.email')
            ->having('transaction_count', '>', 0)
            ->orderBy('transaction_count', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($member) {
                return [
                    'customer_id' => $member->customer_id,
                    'customer_name' => $member->customer_name,
                    'phone' => $member->phone,
                    'email' => $member->email,
                    'transaction_count' => $member->transaction_count,
                    'total_value' => $member->total_value,
                    'frequency_per_day' => $member->frequency_per_day,
                ];
            });
    }
} 