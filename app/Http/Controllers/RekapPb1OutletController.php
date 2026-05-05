<?php

namespace App\Http\Controllers;

use App\Exports\RekapPb1OutletExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class RekapPb1OutletController extends Controller
{
    /**
     * Kolom yang didukung pada {@see order_dummy}. Kolom service bisa bernama `service_amount` atau `service`.
     *
     * @var array<string, string|null>
     */
    private array $resolvedColumns = [];

    public function index(Request $request)
    {
        return Inertia::render('Reports/RekapPb1Outlet', $this->buildPayload($request));
    }

    public function export(Request $request)
    {
        $payload = $this->buildPayload($request);
        $month = preg_replace('/[^0-9\-]/', '', (string) ($payload['selectedMonth'] ?? now()->format('Y-m')));

        return Excel::download(
            new RekapPb1OutletExport($payload),
            'rekap_pb1_outlet_'.$month.'_outlet_'.$payload['selectedOutletId'].'_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Request $request): array
    {
        $user = auth()->user();
        $isAdminOutlet = (int) ($user->id_outlet ?? 0) === 1;

        $month = $request->input('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $selectedOutletId = (int) ($request->input('outlet_id') ?: 0);
        if (! $isAdminOutlet) {
            $selectedOutletId = (int) ($user->id_outlet ?? 0);
        } elseif ($selectedOutletId <= 0) {
            $selectedOutletId = 1;
        }

        $outletsQuery = DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet');

        if (! $isAdminOutlet) {
            $outletsQuery->where('id_outlet', $selectedOutletId);
        }

        $outlets = $outletsQuery->get();

        $tableExists = Schema::hasTable('order_dummy');
        $this->resolvedColumns = $tableExists ? $this->resolveOrderDummyColumns() : [];

        $byDate = [];
        if ($tableExists && $selectedOutletId > 0 && $this->hasRequiredColumns()) {
            $q = DB::table('order_dummy')
                ->where('id_outlet', $selectedOutletId)
                ->whereBetween('tanggal', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->select(
                    DB::raw('DATE(tanggal) as d'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('total').'),0) as total'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('disc').'),0) as disc'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('dpp').'),0) as dpp'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('pb1').'),0) as pb1'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('service').'),0) as service'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('grand_total').'),0) as grand_total'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('pax').'),0) as pax'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('commfee').'),0) as commfee')
                )
                ->groupBy(DB::raw('DATE(tanggal)'));

            foreach ($q->get() as $r) {
                $key = Carbon::parse($r->d)->toDateString();
                $byDate[$key] = [
                    'total' => (float) $r->total,
                    'disc' => (float) $r->disc,
                    'dpp' => (float) $r->dpp,
                    'pb1' => (float) $r->pb1,
                    'service' => (float) $r->service,
                    'grand_total' => (float) $r->grand_total,
                    'pax' => (int) $r->pax,
                    'commfee' => (float) $r->commfee,
                ];
            }
        }

        $rows = $this->buildCalendarRows($monthStart, $monthEnd, $byDate);
        $totals = $this->sumRows($rows);

        return [
            'outlets' => $outlets,
            'selectedOutletId' => $selectedOutletId,
            'selectedMonth' => $month,
            'monthLabel' => $monthStart->copy()->locale(app()->getLocale())->translatedFormat('F Y'),
            'rows' => $rows,
            'totals' => $totals,
            'tableExists' => $tableExists,
            'tableReady' => $tableExists && $this->hasRequiredColumns(),
            'canSelectOutlet' => $isAdminOutlet,
        ];
    }

    private function buildCalendarRows(Carbon $monthStart, Carbon $monthEnd, array $byDate): array
    {
        $rows = [];
        for ($d = $monthStart->copy(); $d->lte($monthEnd); $d->addDay()) {
            $key = $d->toDateString();
            $v = $byDate[$key] ?? null;
            $rows[] = [
                'date' => $key,
                'date_display' => $d->format('n/j/Y'),
                'total' => $v['total'] ?? 0,
                'disc' => $v['disc'] ?? 0,
                'dpp' => $v['dpp'] ?? 0,
                'pb1' => $v['pb1'] ?? 0,
                'service' => $v['service'] ?? 0,
                'grand_total' => $v['grand_total'] ?? 0,
                'pax' => $v['pax'] ?? 0,
                'commfee' => $v['commfee'] ?? 0,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, float|int>
     */
    private function sumRows(array $rows): array
    {
        $keys = ['total', 'disc', 'dpp', 'pb1', 'service', 'grand_total', 'pax', 'commfee'];
        $acc = array_fill_keys($keys, 0);
        foreach ($rows as $r) {
            foreach ($keys as $k) {
                $acc[$k] += $k === 'pax' ? (int) ($r[$k] ?? 0) : (float) ($r[$k] ?? 0);
            }
        }

        return $acc;
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveOrderDummyColumns(): array
    {
        $serviceCol = null;
        if (Schema::hasColumn('order_dummy', 'service_amount')) {
            $serviceCol = 'service_amount';
        } elseif (Schema::hasColumn('order_dummy', 'service')) {
            $serviceCol = 'service';
        }

        return [
            'total' => Schema::hasColumn('order_dummy', 'total') ? 'total' : null,
            'disc' => Schema::hasColumn('order_dummy', 'disc') ? 'disc' : null,
            'dpp' => Schema::hasColumn('order_dummy', 'dpp') ? 'dpp' : null,
            'pb1' => Schema::hasColumn('order_dummy', 'pb1') ? 'pb1' : null,
            'service' => $serviceCol,
            'grand_total' => Schema::hasColumn('order_dummy', 'grand_total') ? 'grand_total' : null,
            'pax' => Schema::hasColumn('order_dummy', 'pax') ? 'pax' : null,
            'commfee' => Schema::hasColumn('order_dummy', 'commfee') ? 'commfee' : null,
        ];
    }

    private function hasRequiredColumns(): bool
    {
        if (! Schema::hasColumn('order_dummy', 'tanggal') || ! Schema::hasColumn('order_dummy', 'id_outlet')) {
            return false;
        }
        foreach ($this->resolvedColumns as $col) {
            if ($col === null) {
                return false;
            }
        }

        return true;
    }

    private function sumInner(string $key): string
    {
        $c = $this->resolvedColumns[$key] ?? null;
        if ($c === null) {
            return '0';
        }

        return 'COALESCE(`'.$c.'`,0)';
    }
}
