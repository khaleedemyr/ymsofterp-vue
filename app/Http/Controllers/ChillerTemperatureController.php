<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

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

        $date = $request->input('date') ?: now()->toDateString();
        $search = trim((string) $request->input('search', ''));
        $outletFilter = $canChooseOutlet ? (int) $request->input('outlet_id', 0) : $userOutletId;

        $query = DB::table('chiller_temperature_logs as l')
            ->leftJoin('tbl_data_outlet as o', 'l.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'l.warehouse_outlet_id', '=', 'wo.id')
            ->where('l.check_date', $date)
            ->select(
                'l.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_name'
            )
            ->orderBy('o.nama_outlet')
            ->orderBy('wo.name')
            ->orderBy('l.item_name')
            ->orderBy('l.serial_number');

        if ($canChooseOutlet) {
            if ($outletFilter > 0) {
                $query->where('l.outlet_id', $outletFilter);
            }
        } elseif ($userOutletId > 0) {
            $query->where('l.outlet_id', $userOutletId);
        } else {
            $query->whereRaw('0 = 1');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('l.item_name', 'like', "%{$search}%")
                    ->orWhere('l.serial_number', 'like', "%{$search}%");
            });
        }

        $logs = $query->get();
        $rows = $this->pivotRows($logs);

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
                'date' => $date,
                'outlet_id' => $canChooseOutlet && $outletFilter > 0 ? $outletFilter : '',
                'search' => $search,
            ],
            'outlets' => $outlets,
            'canChooseOutlet' => $canChooseOutlet,
            'user' => $user,
            'tableReady' => true,
            'checkTimes' => self::CHECK_TIMES,
        ]);
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
}
