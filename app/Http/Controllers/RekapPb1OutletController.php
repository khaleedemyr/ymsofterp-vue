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
    private const TABLE = 'orders_dummy';

    /**
     * Map key laporan -> nama kolom DB di {@see orders_dummy} (tanpa Disc; Disc = discount + manual_discount_amount).
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
        $suffix = $payload['selectedOutletKode'] ?? $payload['selectedOutletId'];

        return Excel::download(
            new RekapPb1OutletExport($payload),
            'rekap_pb1_outlet_'.$month.'_outlet_'.$suffix.'_'.now()->format('Ymd_His').'.xlsx'
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

        $outletsSelect = ['id_outlet as id', 'nama_outlet as name'];
        if (Schema::hasTable('tbl_data_outlet') && Schema::hasColumn('tbl_data_outlet', 'qr_code')) {
            $outletsSelect[] = 'qr_code';
        }

        $outletsQuery = DB::table('tbl_data_outlet')->select($outletsSelect)->orderBy('nama_outlet');

        if (! $isAdminOutlet) {
            $outletsQuery->where('id_outlet', $selectedOutletId);
        }

        $outlets = $outletsQuery->get();

        $outletKodeCandidates = $this->resolveOutletKodeCandidates($selectedOutletId);
        $selectedKode = $outletKodeCandidates[0] ?? (string) $selectedOutletId;

        $tableExists = Schema::hasTable(self::TABLE);
        $this->resolvedColumns = $tableExists ? $this->resolveOrdersDummyColumns() : [];

        $from = $monthStart->copy()->startOfDay()->toDateTimeString();
        $to = $monthEnd->copy()->endOfDay()->toDateTimeString();

        $sampleKodeOutletInPeriod = [];
        if ($tableExists && $this->hasRequiredColumns()) {
            $sampleKodeOutletInPeriod = DB::table(self::TABLE)
                ->whereBetween('created_at', [$from, $to])
                ->select('kode_outlet')
                ->groupBy('kode_outlet')
                ->orderBy('kode_outlet')
                ->limit(40)
                ->pluck('kode_outlet')
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->values()
                ->all();
        }

        $byDate = [];
        if ($tableExists && $outletKodeCandidates !== [] && $this->hasRequiredColumns()) {
            $q = DB::table(self::TABLE)
                ->whereIn('kode_outlet', $outletKodeCandidates)
                ->whereBetween('created_at', [$from, $to])
                ->select(
                    DB::raw('DATE(created_at) as d'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('total').'),0) as total'),
                    DB::raw('COALESCE(SUM('.$this->sumDiscPerRow().'),0) as disc'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('dpp').'),0) as dpp'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('pb1').'),0) as pb1'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('service').'),0) as service'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('grand_total').'),0) as grand_total'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('pax').'),0) as pax'),
                    DB::raw('COALESCE(SUM('.$this->sumInner('commfee').'),0) as commfee')
                )
                ->groupBy(DB::raw('DATE(created_at)'));

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
            'selectedOutletKode' => $selectedKode,
            'outletKodeCandidates' => $outletKodeCandidates,
            'sampleKodeOutletInPeriod' => $sampleKodeOutletInPeriod,
            'selectedMonth' => $month,
            'monthLabel' => $monthStart->copy()->locale(app()->getLocale())->translatedFormat('F Y'),
            'rows' => $rows,
            'totals' => $totals,
            'tableExists' => $tableExists,
            'tableReady' => $tableExists && $this->hasRequiredColumns(),
            'canSelectOutlet' => $isAdminOutlet,
        ];
    }

    /**
     * Kode yang dipakai untuk cocokkan orders_dummy.kode_outlet.
     * POS/sync kadang mengisi kode_outlet dengan qr_code, kode master, atau id_outlet — dicoba semua (unik).
     *
     * @return list<string>
     */
    private function resolveOutletKodeCandidates(int $id): array
    {
        $seen = [];
        $push = function (?string $v) use (&$seen): void {
            $t = trim((string) $v);
            if ($t !== '' && ! isset($seen[$t])) {
                $seen[$t] = true;
            }
        };

        if (Schema::hasTable('tbl_data_outlet')) {
            $cols = [];
            if (Schema::hasColumn('tbl_data_outlet', 'qr_code')) {
                $cols[] = 'qr_code';
            }
            if (Schema::hasColumn('tbl_data_outlet', 'kode_outlet')) {
                $cols[] = 'kode_outlet';
            }
            if ($cols !== []) {
                $row = DB::table('tbl_data_outlet')->where('id_outlet', $id)->first($cols);
                if ($row) {
                    foreach ($cols as $c) {
                        $push($row->{$c} ?? null);
                    }
                }
            }
        }

        $push((string) $id);

        return array_keys($seen);
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
     * Kolom aktual di orders_dummy (kolom Disc = discount + manual_discount_amount, dihitung terpisah).
     *
     * @return array<string, string|null>
     */
    private function resolveOrdersDummyColumns(): array
    {
        $t = self::TABLE;

        return [
            'total' => Schema::hasColumn($t, 'total') ? 'total' : null,
            'dpp' => Schema::hasColumn($t, 'dpp') ? 'dpp' : null,
            'pb1' => Schema::hasColumn($t, 'pb1') ? 'pb1' : null,
            'service' => Schema::hasColumn($t, 'service') ? 'service' : null,
            'grand_total' => Schema::hasColumn($t, 'grand_total') ? 'grand_total' : null,
            'pax' => Schema::hasColumn($t, 'pax') ? 'pax' : null,
            'commfee' => Schema::hasColumn($t, 'commfee') ? 'commfee' : null,
        ];
    }

    private function hasRequiredColumns(): bool
    {
        $t = self::TABLE;
        if (! Schema::hasColumn($t, 'created_at') || ! Schema::hasColumn($t, 'kode_outlet')) {
            return false;
        }
        if (! Schema::hasColumn($t, 'discount') && ! Schema::hasColumn($t, 'manual_discount_amount')) {
            return false;
        }
        foreach ($this->resolvedColumns as $col) {
            if ($col === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ekspresi per baris untuk Disc: discount + manual_discount_amount (nullable di-COALESCE ke 0).
     */
    private function sumDiscPerRow(): string
    {
        $t = self::TABLE;
        $hasD = Schema::hasColumn($t, 'discount');
        $hasM = Schema::hasColumn($t, 'manual_discount_amount');
        if (! $hasD && ! $hasM) {
            return '0';
        }
        $parts = [];
        if ($hasD) {
            $parts[] = 'COALESCE(`discount`,0)';
        }
        if ($hasM) {
            $parts[] = 'COALESCE(`manual_discount_amount`,0)';
        }

        return count($parts) === 1 ? $parts[0] : '('.implode('+', $parts).')';
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
