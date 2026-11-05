<?php

namespace App\Http\Controllers;

use App\Exports\ChillerTemperatureReportExport;
use App\Models\Divisi;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketHistory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Services\TicketTeamAutoAssignService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ChillerTemperatureController extends Controller
{
    public const CHECK_TIMES = ['10:00', '16:00', '22:00'];

    public function index(Request $request)
    {
        $user = $request->user();
        $userOutletId = (int) ($user->id_outlet ?? 0);
        $canChooseOutlet = $userOutletId === 1;

        if (!Schema::hasTable('chiller_temperature_logs')) {
            return Inertia::render('ChillerTemperature/Index', [
                'rows' => [],
                'filters' => [
                    'date' => now()->toDateString(),
                    'outlet_id' => '',
                    'search' => '',
                ],
                'outlets' => [],
                'canChooseOutlet' => $canChooseOutlet,
                'user' => $user,
                'tableReady' => false,
                'checkTimes' => self::CHECK_TIMES,
            ]);
        }

        $resolved = $this->resolveIndexFilters($request);
        $rows = $this->pivotRows($this->fetchLogs($resolved));

        $outlets = [];
        if ($canChooseOutlet) {
            $outlets = DB::table('tbl_data_outlet')
                ->select('id_outlet', 'nama_outlet')
                ->orderBy('nama_outlet')
                ->get();
        }

        return Inertia::render('ChillerTemperature/Index', [
            'rows' => $rows,
            'filters' => [
                'date' => $resolved['date'],
                'outlet_id' => $canChooseOutlet && $resolved['outlet_filter'] > 0 ? $resolved['outlet_filter'] : '',
                'search' => $resolved['search'],
            ],
            'outlets' => $outlets,
            'canChooseOutlet' => $canChooseOutlet,
            'user' => $user,
            'tableReady' => true,
            'checkTimes' => self::CHECK_TIMES,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->ensureTableReady();

        $resolved = $this->resolveIndexFilters($request);
        $rows = $this->pivotRows($this->fetchLogs($resolved));

        if (empty($rows)) {
            return back()->with('error', 'Tidak ada data untuk diekspor.');
        }

        $exportRows = [];
        $no = 1;
        foreach ($rows as $row) {
            $slot1000 = $row['slots']['10:00'] ?? null;
            $slot1600 = $row['slots']['16:00'] ?? null;
            $slot2200 = $row['slots']['22:00'] ?? null;

            $exportRows[] = [
                $no++,
                $row['check_date'] ?? $resolved['date'],
                $row['outlet_name'] ?? '-',
                $row['warehouse_name'] ?? '-',
                $row['item_name'] ?? '-',
                $row['serial_number'] ?? '-',
                $slot1000 ? (float) $slot1000['temp_display'] : '-',
                $slot1000 ? (float) $slot1000['temp_actual'] : '-',
                $slot1600 ? (float) $slot1600['temp_display'] : '-',
                $slot1600 ? (float) $slot1600['temp_actual'] : '-',
                $slot2200 ? (float) $slot2200['temp_display'] : '-',
                $slot2200 ? (float) $slot2200['temp_actual'] : '-',
                $slot1000['notes'] ?? '-',
                $slot1600['notes'] ?? '-',
                $slot2200['notes'] ?? '-',
            ];
        }

        $filename = 'kontrol-suhu-chiller-' . now()->format('YmdHis') . '.xlsx';

        return Excel::download(new ChillerTemperatureReportExport($exportRows), $filename);
    }

    public function exportPdf(Request $request)
    {
        $this->ensureTableReady();

        $resolved = $this->resolveIndexFilters($request);
        $rows = $this->pivotRows($this->fetchLogs($resolved));

        if (empty($rows)) {
            return back()->with('error', 'Tidak ada data untuk diekspor.');
        }

        $outletName = 'Semua Outlet';
        if ($resolved['outlet_filter'] > 0) {
            $outletName = DB::table('tbl_data_outlet')
                ->where('id_outlet', $resolved['outlet_filter'])
                ->value('nama_outlet') ?: ('Outlet #' . $resolved['outlet_filter']);
        } elseif (!$resolved['can_choose_outlet']) {
            $outletName = 'Outlet sendiri';
        }

        $pdf = Pdf::loadView('exports.chiller_temperature_report_pdf', [
            'rows' => $rows,
            'filters' => [
                'date' => $resolved['date'],
                'outlet_name' => $outletName,
                'search' => $resolved['search'],
            ],
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'kontrol-suhu-chiller-' . now()->format('YmdHis') . '.pdf';

        return $pdf->download($filename);
    }

    private function resolveIndexFilters(Request $request): array
    {
        $user = $request->user();
        $userOutletId = (int) ($user->id_outlet ?? 0);
        $canChooseOutlet = $userOutletId === 1;

        return [
            'date' => $request->input('date') ?: now()->toDateString(),
            'search' => trim((string) $request->input('search', '')),
            'outlet_filter' => $canChooseOutlet ? (int) $request->input('outlet_id', 0) : $userOutletId,
            'can_choose_outlet' => $canChooseOutlet,
            'user_outlet_id' => $userOutletId,
        ];
    }

    private function fetchLogs(array $resolved)
    {
        $query = DB::table('chiller_temperature_logs as l')
            ->leftJoin('tbl_data_outlet as o', 'l.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'l.warehouse_outlet_id', '=', 'wo.id')
            ->where('l.check_date', $resolved['date'])
            ->select(
                'l.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_name'
            )
            ->orderBy('o.nama_outlet')
            ->orderBy('wo.name')
            ->orderBy('l.item_name')
            ->orderBy('l.serial_number');

        if ($resolved['can_choose_outlet']) {
            if ($resolved['outlet_filter'] > 0) {
                $query->where('l.outlet_id', $resolved['outlet_filter']);
            }
        } elseif ($resolved['user_outlet_id'] > 0) {
            $query->where('l.outlet_id', $resolved['user_outlet_id']);
        } else {
            $query->whereRaw('0 = 1');
        }

        if ($resolved['search'] !== '') {
            $search = $resolved['search'];
            $query->where(function ($q) use ($search) {
                $q->where('l.item_name', 'like', "%{$search}%")
                    ->orWhere('l.serial_number', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $userOutletId = (int) ($user->id_outlet ?? 0);

        return Inertia::render('ChillerTemperature/Create', [
            'checkTimes' => self::CHECK_TIMES,
            'defaultDate' => now()->toDateString(),
            'canChooseOutlet' => $userOutletId === 1,
            'userOutletId' => $userOutletId,
            'tableReady' => Schema::hasTable('chiller_temperature_logs'),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureTableReady();

        $data = $request->validate([
            'asset_serial_id' => 'required|integer',
            'check_date' => 'required|date',
            'check_time' => ['required', 'string', Rule::in(self::CHECK_TIMES)],
            'temp_display' => 'required|numeric',
            'temp_actual' => 'required|numeric',
            'notes' => 'nullable|string|max:500',
        ]);

        $serial = $this->findAssetSerial((int) $data['asset_serial_id']);
        if (!$serial) {
            return back()->withErrors(['asset_serial_id' => 'Nomor seri asset tidak ditemukan.']);
        }

        $authError = $this->authorizeSerialOutlet($request, $serial);
        if ($authError) {
            return back()->withErrors(['asset_serial_id' => $authError]);
        }

        $exists = DB::table('chiller_temperature_logs')
            ->where('asset_serial_id', $serial->id)
            ->where('check_date', $data['check_date'])
            ->where('check_time', $data['check_time'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'check_time' => 'Data suhu untuk unit, tanggal, dan jam ini sudah ada. Silakan edit data yang ada.',
            ]);
        }

        $now = now();
        DB::table('chiller_temperature_logs')->insert([
            'asset_serial_id' => $serial->id,
            'serial_number' => $serial->serial_number,
            'inventory_item_id' => $serial->inventory_item_id,
            'item_id' => $serial->item_id,
            'item_name' => $serial->item_name,
            'outlet_id' => (int) $serial->owner_outlet_id,
            'warehouse_outlet_id' => $serial->warehouse_outlet_id,
            'check_date' => $data['check_date'],
            'check_time' => $data['check_time'],
            'temp_display' => $data['temp_display'],
            'temp_actual' => $data['temp_actual'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return redirect()
            ->route('chiller-temperature.index', ['date' => $data['check_date']])
            ->with('success', 'Data suhu berhasil disimpan.');
    }

    public function edit(int $id)
    {
        $this->ensureTableReady();

        $log = $this->findLog($id);
        if (!$log) {
            abort(404);
        }

        $this->authorizeLogAccess(request(), $log);

        return Inertia::render('ChillerTemperature/Edit', [
            'log' => $log,
            'checkTimes' => self::CHECK_TIMES,
            'canChooseOutlet' => (int) (request()->user()->id_outlet ?? 0) === 1,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureTableReady();

        $log = $this->findLog($id);
        if (!$log) {
            abort(404);
        }

        $this->authorizeLogAccess($request, $log);

        $data = $request->validate([
            'check_date' => 'required|date',
            'check_time' => ['required', 'string', Rule::in(self::CHECK_TIMES)],
            'temp_display' => 'required|numeric',
            'temp_actual' => 'required|numeric',
            'notes' => 'nullable|string|max:500',
        ]);

        $duplicate = DB::table('chiller_temperature_logs')
            ->where('asset_serial_id', $log->asset_serial_id)
            ->where('check_date', $data['check_date'])
            ->where('check_time', $data['check_time'])
            ->where('id', '!=', $id)
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'check_time' => 'Data suhu untuk unit, tanggal, dan jam ini sudah ada.',
            ]);
        }

        DB::table('chiller_temperature_logs')->where('id', $id)->update([
            'check_date' => $data['check_date'],
            'check_time' => $data['check_time'],
            'temp_display' => $data['temp_display'],
            'temp_actual' => $data['temp_actual'],
            'notes' => $data['notes'] ?? null,
            'updated_by' => $request->user()->id,
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('chiller-temperature.index', ['date' => $data['check_date']])
            ->with('success', 'Data suhu berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureTableReady();

        $log = $this->findLog($id);
        if (!$log) {
            abort(404);
        }

        $this->authorizeLogAccess($request, $log);

        $date = $log->check_date;
        DB::table('chiller_temperature_logs')->where('id', $id)->delete();

        return redirect()
            ->route('chiller-temperature.index', ['date' => $date])
            ->with('success', 'Data suhu berhasil dihapus.');
    }

    public function ticketOptions(Request $request)
    {
        $this->ensureHeadOfficeUser($request);

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => TicketCategory::active()->orderBy('name')->get(['id', 'name']),
                'priorities' => TicketPriority::active()->orderBy('level', 'desc')->get(['id', 'name', 'level', 'max_days', 'description']),
                'divisis' => Divisi::active()->orderBy('nama_divisi')->get(['id', 'nama_divisi']),
            ],
        ]);
    }

    public function createTicket(Request $request)
    {
        $this->ensureTableReady();
        $this->ensureHeadOfficeUser($request);

        $validator = Validator::make($request->all(), [
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'asset_serial_id' => 'required|integer',
            'serial_number' => 'nullable|string|max:100',
            'item_name' => 'nullable|string|max:255',
            'warehouse_name' => 'nullable|string|max:255',
            'check_date' => 'required|date',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:ticket_categories,id',
            'priority_id' => 'required|exists:ticket_priorities,id',
            'divisi_id' => 'required|exists:tbl_data_divisi,id',
            'temps' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        DB::beginTransaction();
        try {
            $defaultStatus = TicketStatus::where('slug', 'open')->first()
                ?: TicketStatus::active()->first()
                ?: TicketStatus::first();

            if (!$defaultStatus) {
                throw new \Exception('No ticket status found. Please seed ticket statuses.');
            }

            $priority = TicketPriority::findOrFail($data['priority_id']);
            $dueDate = now()->addDays($priority->max_days ?? 7);

            $description = trim($data['description']);
            $tempSummary = $this->buildTemperatureSummary($data['temps'] ?? []);
            if ($tempSummary !== '') {
                $description .= "\n\n--- Data Suhu (" . $data['check_date'] . ") ---\n" . $tempSummary;
            }

            $metaLines = [
                'Sumber: Kontrol Suhu Chiller & Freezer',
                'Outlet ID: ' . $data['outlet_id'],
                'Item: ' . ($data['item_name'] ?? '-'),
                'Serial: ' . ($data['serial_number'] ?? '-'),
                'Warehouse: ' . ($data['warehouse_name'] ?? '-'),
                'Tanggal cek: ' . $data['check_date'],
            ];
            $description .= "\n\n" . implode("\n", $metaLines);

            $ticket = Ticket::create([
                'ticket_number' => Ticket::generateTicketNumber(),
                'title' => $data['title'],
                'description' => $description,
                'category_id' => $data['category_id'],
                'priority_id' => $data['priority_id'],
                'status_id' => $defaultStatus->id,
                'divisi_id' => $data['divisi_id'],
                'outlet_id' => $data['outlet_id'],
                'created_by' => $request->user()->id,
                'due_date' => $dueDate,
                'source' => 'chiller_temperature',
                'source_id' => $data['asset_serial_id'],
            ]);

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'action' => 'created',
                'field_name' => null,
                'old_value' => null,
                'new_value' => null,
                'description' => 'Ticket created from Kontrol Suhu Chiller/Freezer (serial #' . ($data['serial_number'] ?? $data['asset_serial_id']) . ')',
            ]);

            app(TicketTeamAutoAssignService::class)->assignIfMatch($ticket, $request->user()->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket berhasil dibuat dari Kontrol Suhu',
                'data' => $ticket->load(['category', 'priority', 'status', 'divisi', 'outlet', 'creator']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function lookupSerial(Request $request)
    {
        if (!Schema::hasTable('asset_inventory_serials')) {
            return response()->json(['success' => false, 'message' => 'Tabel asset serial belum tersedia.'], 503);
        }

        $raw = trim((string) $request->input('serial_number', ''));
        if ($raw === '') {
            return response()->json(['success' => false, 'message' => 'Nomor seri wajib diisi.'], 422);
        }

        $serialNumber = $this->normalizeScannedSerial($raw);
        $serial = $this->findAssetSerialByNumber($serialNumber);

        if (!$serial) {
            return response()->json(['success' => false, 'message' => 'Nomor seri tidak ditemukan di Asset Serial.'], 404);
        }

        $authError = $this->authorizeSerialOutlet($request, $serial);
        if ($authError) {
            return response()->json(['success' => false, 'message' => $authError], 403);
        }

        return response()->json([
            'success' => true,
            'serial' => [
                'id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'item_name' => $serial->item_name,
                'owner_outlet_id' => $serial->owner_outlet_id,
                'owner_outlet_name' => $serial->owner_outlet_name,
                'warehouse_outlet_id' => $serial->warehouse_outlet_id,
                'warehouse_name' => $serial->warehouse_name,
                'status' => $serial->status,
            ],
        ]);
    }

    private function pivotRows($logs): array
    {
        $grouped = [];

        foreach ($logs as $log) {
            $key = $log->asset_serial_id . '|' . $log->check_date;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'asset_serial_id' => $log->asset_serial_id,
                    'serial_number' => $log->serial_number,
                    'item_name' => $log->item_name,
                    'outlet_id' => $log->outlet_id,
                    'outlet_name' => $log->outlet_name,
                    'warehouse_outlet_id' => $log->warehouse_outlet_id,
                    'warehouse_name' => $log->warehouse_name,
                    'check_date' => $log->check_date,
                    'slots' => [
                        '10:00' => null,
                        '16:00' => null,
                        '22:00' => null,
                    ],
                ];
            }

            $grouped[$key]['slots'][$log->check_time] = [
                'id' => $log->id,
                'temp_display' => $log->temp_display,
                'temp_actual' => $log->temp_actual,
                'notes' => $log->notes,
            ];
        }

        return array_values($grouped);
    }

    private function findLog(int $id): ?object
    {
        return DB::table('chiller_temperature_logs as l')
            ->leftJoin('tbl_data_outlet as o', 'l.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'l.warehouse_outlet_id', '=', 'wo.id')
            ->where('l.id', $id)
            ->select(
                'l.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_name'
            )
            ->first();
    }

    private function findAssetSerial(int $id): ?object
    {
        if (!Schema::hasTable('asset_inventory_serials')) {
            return null;
        }

        return DB::table('asset_inventory_serials as s')
            ->join('items as i', 's.item_id', '=', 'i.id')
            ->leftJoin('tbl_data_outlet as oo', 's.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->where('s.id', $id)
            ->select(
                's.id',
                's.serial_number',
                's.inventory_item_id',
                's.item_id',
                's.owner_outlet_id',
                's.warehouse_outlet_id',
                's.status',
                'i.name as item_name',
                'oo.nama_outlet as owner_outlet_name',
                'wo.name as warehouse_name'
            )
            ->first();
    }

    private function findAssetSerialByNumber(string $serialNumber): ?object
    {
        return DB::table('asset_inventory_serials as s')
            ->join('items as i', 's.item_id', '=', 'i.id')
            ->leftJoin('tbl_data_outlet as oo', 's.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->where('s.serial_number', $serialNumber)
            ->select(
                's.id',
                's.serial_number',
                's.inventory_item_id',
                's.item_id',
                's.owner_outlet_id',
                's.warehouse_outlet_id',
                's.status',
                'i.name as item_name',
                'oo.nama_outlet as owner_outlet_name',
                'wo.name as warehouse_name'
            )
            ->first();
    }

    private function normalizeScannedSerial(string $raw): string
    {
        $value = trim($raw);
        $prefix = 'YM:ASSET:';
        if (stripos($value, $prefix) === 0) {
            $value = substr($value, strlen($prefix));
        }

        return trim($value);
    }

    private function authorizeSerialOutlet(Request $request, object $serial): ?string
    {
        $userOutletId = (int) ($request->user()->id_outlet ?? 0);
        if ($userOutletId === 1) {
            return null;
        }

        if ($userOutletId <= 0) {
            return 'User tidak memiliki outlet.';
        }

        if ((int) $serial->owner_outlet_id !== $userOutletId) {
            return 'Nomor seri ini milik outlet lain. Anda hanya dapat input untuk outlet sendiri.';
        }

        return null;
    }

    private function authorizeLogAccess(Request $request, object $log): void
    {
        $userOutletId = (int) ($request->user()->id_outlet ?? 0);
        if ($userOutletId === 1) {
            return;
        }

        if ($userOutletId <= 0 || (int) $log->outlet_id !== $userOutletId) {
            abort(403, 'Anda tidak memiliki akses ke data outlet ini.');
        }
    }

    private function ensureTableReady(): void
    {
        if (!Schema::hasTable('chiller_temperature_logs')) {
            abort(503, 'Tabel chiller_temperature_logs belum tersedia. Jalankan database/sql/chiller_temperature_logs.sql');
        }
    }

    private function ensureHeadOfficeUser(Request $request): void
    {
        if ((int) ($request->user()->id_outlet ?? 0) !== 1) {
            abort(403, 'Hanya Head Office yang dapat membuat ticket dari Kontrol Suhu.');
        }
    }

    private function buildTemperatureSummary(array $temps): string
    {
        $lines = [];
        foreach (self::CHECK_TIMES as $time) {
            $slot = $temps[$time] ?? null;
            if (!$slot || (!isset($slot['temp_display']) && !isset($slot['temp_actual']))) {
                $lines[] = "{$time}: belum diisi";
                continue;
            }

            $display = isset($slot['temp_display']) ? number_format((float) $slot['temp_display'], 1, '.', '') : '-';
            $actual = isset($slot['temp_actual']) ? number_format((float) $slot['temp_actual'], 1, '.', '') : '-';
            $notes = trim((string) ($slot['notes'] ?? ''));
            $line = "{$time}: Display {$display}°C / Actual {$actual}°C";
            if ($notes !== '') {
                $line .= " (Notes: {$notes})";
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
