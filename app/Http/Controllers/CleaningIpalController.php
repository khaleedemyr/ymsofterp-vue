<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CleaningIpalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userOutletId = (int) ($user->id_outlet ?? 0);
        $canChooseOutlet = $userOutletId === 1;

        if (!Schema::hasTable('cleaning_ipal_logs')) {
            return Inertia::render('CleaningIpal/Index', [
                'records' => ['data' => [], 'links' => []],
                'filters' => [
                    'date' => now()->toDateString(),
                    'outlet_id' => '',
                    'search' => '',
                ],
                'outlets' => [],
                'canChooseOutlet' => $canChooseOutlet,
                'tableReady' => false,
            ]);
        }

        $date = $request->input('date') ?: now()->toDateString();
        $search = trim((string) $request->input('search', ''));
        $outletFilter = $canChooseOutlet ? (int) $request->input('outlet_id', 0) : $userOutletId;

        $query = DB::table('cleaning_ipal_logs as l')
            ->leftJoin('tbl_data_outlet as o', 'l.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'l.created_by', '=', 'u.id')
            ->where('l.cleaning_date', $date)
            ->select(
                'l.*',
                DB::raw('COALESCE(o.nama_outlet, l.outlet_name) as outlet_name'),
                'u.nama_lengkap as created_by_name'
            )
            ->orderByDesc('l.id');

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
                $q->where('l.outlet_name', 'like', "%{$search}%")
                    ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                    ->orWhere('l.outlet_qr_code', 'like', "%{$search}%")
                    ->orWhere('l.keterangan', 'like', "%{$search}%");
            });
        }

        $outlets = [];
        if ($canChooseOutlet) {
            $outlets = DB::table('tbl_data_outlet')
                ->select('id_outlet', 'nama_outlet')
                ->orderBy('nama_outlet')
                ->get();
        }

        return Inertia::render('CleaningIpal/Index', [
            'records' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'date' => $date,
                'outlet_id' => $canChooseOutlet && $outletFilter > 0 ? $outletFilter : '',
                'search' => $search,
            ],
            'outlets' => $outlets,
            'canChooseOutlet' => $canChooseOutlet,
            'tableReady' => true,
        ]);
    }

    public function create(Request $request)
    {
        $userOutletId = (int) ($request->user()->id_outlet ?? 0);

        return Inertia::render('CleaningIpal/Create', [
            'defaultDate' => now()->toDateString(),
            'canChooseOutlet' => $userOutletId === 1,
            'userOutletId' => $userOutletId,
            'tableReady' => Schema::hasTable('cleaning_ipal_logs'),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureTableReady();

        $data = $request->validate([
            'outlet_id' => 'required|integer',
            'cleaning_date' => 'required|date',
            'keterangan' => 'nullable|string|max:2000',
            'photo_before' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'photo_after' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        $outlet = $this->findOutlet((int) $data['outlet_id']);
        if (!$outlet) {
            return back()->withErrors(['outlet_id' => 'Outlet tidak ditemukan.']);
        }

        $authError = $this->authorizeOutletAccess($request, (int) $outlet->id_outlet);
        if ($authError) {
            return back()->withErrors(['outlet_id' => $authError]);
        }

        $beforePath = $request->file('photo_before')->store('cleaning-ipal/before', 'public');
        $afterPath = $request->hasFile('photo_after')
            ? $request->file('photo_after')->store('cleaning-ipal/after', 'public')
            : null;

        $now = now();
        DB::table('cleaning_ipal_logs')->insert([
            'outlet_id' => (int) $outlet->id_outlet,
            'outlet_qr_code' => $outlet->qr_code,
            'outlet_name' => $outlet->nama_outlet,
            'cleaning_date' => $data['cleaning_date'],
            'keterangan' => $data['keterangan'] ?? null,
            'photo_before' => $beforePath,
            'photo_after' => $afterPath,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return redirect()
            ->route('cleaning-ipal.index', ['date' => $data['cleaning_date']])
            ->with('success', 'Data Cleaning IPAL berhasil disimpan.');
    }

    public function edit(int $id)
    {
        $this->ensureTableReady();

        $log = $this->findLog($id);
        if (!$log) {
            abort(404);
        }

        $this->authorizeLogAccess(request(), $log);

        return Inertia::render('CleaningIpal/Edit', [
            'log' => $log,
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
            'cleaning_date' => 'required|date',
            'keterangan' => 'nullable|string|max:2000',
            'photo_before' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'photo_after' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        $update = [
            'cleaning_date' => $data['cleaning_date'],
            'keterangan' => $data['keterangan'] ?? null,
            'updated_by' => $request->user()->id,
            'updated_at' => now(),
        ];

        if ($request->hasFile('photo_before')) {
            $newBefore = $request->file('photo_before')->store('cleaning-ipal/before', 'public');
            if ($log->photo_before && Storage::disk('public')->exists($log->photo_before)) {
                Storage::disk('public')->delete($log->photo_before);
            }
            $update['photo_before'] = $newBefore;
        }

        if ($request->hasFile('photo_after')) {
            $newAfter = $request->file('photo_after')->store('cleaning-ipal/after', 'public');
            if ($log->photo_after && Storage::disk('public')->exists($log->photo_after)) {
                Storage::disk('public')->delete($log->photo_after);
            }
            $update['photo_after'] = $newAfter;
        }

        DB::table('cleaning_ipal_logs')->where('id', $id)->update($update);

        return redirect()
            ->route('cleaning-ipal.index', ['date' => $data['cleaning_date']])
            ->with('success', 'Data Cleaning IPAL berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureTableReady();

        $log = $this->findLog($id);
        if (!$log) {
            abort(404);
        }

        $this->authorizeLogAccess($request, $log);

        if ($log->photo_before && Storage::disk('public')->exists($log->photo_before)) {
            Storage::disk('public')->delete($log->photo_before);
        }
        if ($log->photo_after && Storage::disk('public')->exists($log->photo_after)) {
            Storage::disk('public')->delete($log->photo_after);
        }

        $date = $log->cleaning_date;
        DB::table('cleaning_ipal_logs')->where('id', $id)->delete();

        return redirect()
            ->route('cleaning-ipal.index', ['date' => $date])
            ->with('success', 'Data Cleaning IPAL berhasil dihapus.');
    }

    public function lookupOutlet(Request $request)
    {
        $raw = trim((string) $request->input('qr_code', ''));
        if ($raw === '') {
            return response()->json(['success' => false, 'message' => 'QR Code outlet wajib diisi.'], 422);
        }

        $outlet = DB::table('tbl_data_outlet')
            ->select('id_outlet', 'nama_outlet', 'qr_code', 'lokasi', 'status')
            ->where('qr_code', $raw)
            ->first();

        if (!$outlet) {
            return response()->json(['success' => false, 'message' => 'QR Code outlet tidak ditemukan.'], 404);
        }

        $authError = $this->authorizeOutletAccess($request, (int) $outlet->id_outlet);
        if ($authError) {
            return response()->json(['success' => false, 'message' => $authError], 403);
        }

        return response()->json([
            'success' => true,
            'outlet' => [
                'id_outlet' => $outlet->id_outlet,
                'nama_outlet' => $outlet->nama_outlet,
                'qr_code' => $outlet->qr_code,
                'lokasi' => $outlet->lokasi,
                'status' => $outlet->status,
            ],
        ]);
    }

    private function findLog(int $id): ?object
    {
        return DB::table('cleaning_ipal_logs as l')
            ->leftJoin('tbl_data_outlet as o', 'l.outlet_id', '=', 'o.id_outlet')
            ->where('l.id', $id)
            ->select(
                'l.*',
                DB::raw('COALESCE(o.nama_outlet, l.outlet_name) as outlet_name')
            )
            ->first();
    }

    private function findOutlet(int $id): ?object
    {
        return DB::table('tbl_data_outlet')
            ->select('id_outlet', 'nama_outlet', 'qr_code', 'lokasi', 'status')
            ->where('id_outlet', $id)
            ->first();
    }

    private function authorizeOutletAccess(Request $request, int $outletId): ?string
    {
        $userOutletId = (int) ($request->user()->id_outlet ?? 0);
        if ($userOutletId === 1) {
            return null;
        }

        if ($userOutletId <= 0) {
            return 'User tidak memiliki outlet.';
        }

        if ($outletId !== $userOutletId) {
            return 'QR outlet ini milik outlet lain. Anda hanya dapat input untuk outlet sendiri.';
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
        if (!Schema::hasTable('cleaning_ipal_logs')) {
            abort(503, 'Tabel cleaning_ipal_logs belum tersedia. Jalankan database/sql/cleaning_ipal_logs.sql');
        }
    }
}
