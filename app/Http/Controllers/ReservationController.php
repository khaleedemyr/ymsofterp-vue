<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\Reservation;
use App\Models\Outlet;
use App\Models\User;
use App\Models\PaymentType;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet ? (int) $user->id_outlet : null;
        $isAdminOutlet = ($userOutletId === 1 || $userOutletId === null);

        $query = Reservation::with(['outlet', 'creator', 'salesUser', 'paymentType'])
            ->when(!$isAdminOutlet && $userOutletId, function ($query) use ($userOutletId) {
                $query->where('outlet_id', $userOutletId);
            })
            ->when($isAdminOutlet && $request->outlet_id, function ($query, $outletId) {
                $query->where('outlet_id', $outletId);
            })
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->dateFrom, function ($query, $dateFrom) {
                $query->whereDate('reservation_date', '>=', $dateFrom);
            })
            ->when($request->dateTo, function ($query, $dateTo) {
                $query->whereDate('reservation_date', '<=', $dateTo);
            })
            ->latest();

        $reservations = $query->get()->map(function ($reservation) {
            return [
                'id' => $reservation->id,
                'name' => $reservation->name,
                'outlet' => $reservation->outlet?->nama_outlet ?? '–',
                'reservation_date' => $reservation->reservation_date,
                'reservation_time' => $reservation->reservation_time,
                'number_of_guests' => $reservation->number_of_guests,
                'smoking_preference' => $reservation->smoking_preference,
                'dp' => $reservation->dp,
                'payment_type_id' => $reservation->payment_type_id,
                'payment_type_name' => $reservation->paymentType?->name,
                'dp_code' => $reservation->dp_code,
                'from_sales' => $reservation->from_sales,
                'sales_user_id' => $reservation->sales_user_id,
                'sales_user_name' => $reservation->salesUser ? $reservation->salesUser->nama_lengkap : null,
                'menu' => $reservation->menu,
                'status' => $reservation->status,
                'created_by' => $reservation->creator ? ($reservation->creator->nama_lengkap ?? $reservation->creator->name) : '–',
                'created_at' => $reservation->created_at?->toIso8601String(),
            ];
        });

        $outletsQuery = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '');
        if (!$isAdminOutlet && $userOutletId) {
            $outletsQuery->where('id_outlet', $userOutletId);
        } else {
            $outletsQuery->orderBy('nama_outlet');
        }
        $outlets = $outletsQuery->get(['id_outlet', 'nama_outlet'])
            ->map(fn($o) => ['id' => $o->id_outlet, 'name' => $o->nama_outlet])
            ->values();

        $effectiveOutletId = $isAdminOutlet ? $request->outlet_id : $userOutletId;

        return Inertia::render('Reservations/Index', [
            'reservations' => $reservations,
            'outlets' => $outlets,
            'can_choose_outlet' => $isAdminOutlet,
            'search' => $request->search,
            'outlet_id' => $effectiveOutletId,
            'status' => $request->status,
            'dateFrom' => $request->dateFrom,
            'dateTo' => $request->dateTo,
        ]);
    }

    public function create()
    {
        $outlets = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get(['id_outlet', 'nama_outlet', 'region_id'])
            ->map(function($o) {
                return [
                    'id' => $o->id_outlet,
                    'name' => $o->nama_outlet,
                    'region_id' => $o->region_id,
                ];
            })
            ->values();

        $salesUsers = User::where('division_id', 17)
            ->where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->nama_lengkap])
            ->values();

        $paymentTypes = $this->getPaymentTypesForReservationForm();

        return Inertia::render('Reservations/Form', [
            'outlets' => $outlets,
            'salesUsers' => $salesUsers,
            'paymentTypes' => $paymentTypes,
            'isEdit' => false
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:100',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'reservation_date' => 'required|date',
                'reservation_time' => 'required',
                'number_of_guests' => 'required|integer|min:1',
                'special_requests' => 'nullable|string',
                'dp' => 'nullable|numeric|min:0',
                'payment_type_id' => 'nullable|exists:payment_types,id',
                'from_sales' => 'nullable|boolean',
                'sales_user_id' => 'nullable|exists:users,id',
                'menu' => 'nullable|string',
                'menu_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,xls,xlsx|max:10240',
                'status' => 'required|in:pending,confirmed,cancelled',
            ]);

            // Add created_by with authenticated user ID
            $validated['created_by'] = auth()->id();
            $validated['from_sales'] = filter_var($request->input('from_sales'), FILTER_VALIDATE_BOOLEAN);
            if (empty($validated['from_sales'])) {
                $validated['sales_user_id'] = null;
            }
            if ($request->hasFile('menu_file')) {
                $validated['menu_file'] = $request->file('menu_file')->storeAs(
                    'reservations/menu',
                    Str::uuid() . '.' . $request->file('menu_file')->getClientOriginalExtension(),
                    'public'
                );
            } else {
                unset($validated['menu_file']);
            }

            $reservation = Reservation::create($validated);
            $this->syncDpCode($reservation, (float) ($request->input('dp') ?? 0));

            return redirect()->route('reservations.index')
                ->with('success', 'Reservasi berhasil ditambahkan!');
        } catch (\Throwable $e) {
            \Log::error('Gagal menyimpan reservasi: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return redirect()->back()->with('error', 'Terjadi kesalahan saat menyimpan reservasi.');
        }
    }

    public function show(Reservation $reservation)
    {
        $reservation->load(['outlet', 'creator', 'salesUser', 'paymentType']);

        // Transaksi POS yang ter-link ke reservasi ini (order sync dari POS ke pusat)
        $linkedOrders = DB::table('orders')
            ->where('reservation_id', $reservation->id)
            ->orderByDesc('created_at')
            ->get(['id', 'paid_number', 'grand_total', 'status', 'table', 'created_at', 'kode_outlet'])
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'paid_number' => $row->paid_number,
                    'grand_total' => $row->grand_total,
                    'status' => $row->status,
                    'table' => $row->table,
                    'created_at' => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->toIso8601String() : null,
                    'kode_outlet' => $row->kode_outlet,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Reservations/Show', [
            'reservation' => $reservation,
            'linked_orders' => $linkedOrders,
        ]);
    }

    /**
     * Download file menu reservasi (agar bisa dibuka/unduh meski tanpa symlink storage).
     * Nama file pakai ekstensi asli (xlsx, pdf, jpg, dll) supaya tidak tersimpan sebagai .htm.
     */
    public function downloadMenuFile(Reservation $reservation)
    {
        if (empty($reservation->menu_file)) {
            abort(404, 'File menu tidak ada.');
        }
        $path = Storage::disk('public')->path($reservation->menu_file);
        if (!file_exists($path)) {
            abort(404, 'File menu tidak ditemukan.');
        }
        $mime = \Illuminate\Support\Facades\File::mimeType($path);
        $basename = basename($reservation->menu_file);
        $ext = pathinfo($basename, PATHINFO_EXTENSION);
        if ($ext === '') {
            $ext = $this->mimeToExtension($mime);
            $basename = 'menu.' . $ext;
        }
        return response()->download($path, $basename, [
            'Content-Type' => $mime,
        ]);
    }

    /** Map MIME ke ekstensi untuk file menu (image, pdf, excel). */
    private function mimeToExtension(string $mime): string
    {
        $map = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-excel' => 'xls',
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        return $map[$mime] ?? 'bin';
    }

    public function edit(Reservation $reservation)
    {
        $outlets = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get(['id_outlet', 'nama_outlet', 'region_id'])
            ->map(function($o) {
                return [
                    'id' => $o->id_outlet,
                    'name' => $o->nama_outlet,
                    'region_id' => $o->region_id,
                ];
            })
            ->values();

        $salesUsers = User::where('division_id', 17)
            ->where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->nama_lengkap])
            ->values();

        $paymentTypes = $this->getPaymentTypesForReservationForm();

        return Inertia::render('Reservations/Form', [
            'reservation' => $reservation,
            'outlets' => $outlets,
            'salesUsers' => $salesUsers,
            'paymentTypes' => $paymentTypes,
            'isEdit' => true
        ]);
    }

    public function update(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:100',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'reservation_date' => 'required|date',
            'reservation_time' => 'required',
            'number_of_guests' => 'required|integer|min:1',
            'special_requests' => 'nullable|string',
            'dp' => 'nullable|numeric|min:0',
            'payment_type_id' => 'nullable|exists:payment_types,id',
            'from_sales' => 'nullable|boolean',
            'sales_user_id' => 'nullable|exists:users,id',
            'menu' => 'nullable|string',
            'menu_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,xls,xlsx|max:10240',
            'status' => 'required|in:pending,confirmed,cancelled',
        ]);
        $validated['from_sales'] = filter_var($request->input('from_sales'), FILTER_VALIDATE_BOOLEAN);
        if (empty($validated['from_sales'])) {
            $validated['sales_user_id'] = null;
        }
        if ($request->hasFile('menu_file')) {
            if ($reservation->menu_file && Storage::disk('public')->exists($reservation->menu_file)) {
                Storage::disk('public')->delete($reservation->menu_file);
            }
            $validated['menu_file'] = $request->file('menu_file')->storeAs(
                'reservations/menu',
                Str::uuid() . '.' . $request->file('menu_file')->getClientOriginalExtension(),
                'public'
            );
        } else {
            unset($validated['menu_file']);
        }

        $reservation->update($validated);
        $this->syncDpCode($reservation, (float) ($request->input('dp') ?? 0));

        return redirect()->route('reservations.index')
            ->with('success', 'Reservasi berhasil diupdate!');
    }

    /**
     * Set atau clear dp_code & dp_used_at berdasarkan nilai DP.
     * Jika dp > 0: generate kode unik 8 karakter (angka+huruf). Jika dp 0/null: clear kode.
     */
    private function syncDpCode(Reservation $reservation, float $dp): void
    {
        if ($dp > 0) {
            if (empty($reservation->dp_code)) {
                $reservation->dp_code = $this->generateUniqueDpCode();
                $reservation->dp_used_at = null;
                $reservation->saveQuietly();
            }
        } else {
            if ($reservation->dp_code !== null || $reservation->dp_used_at !== null) {
                $reservation->dp_code = null;
                $reservation->dp_used_at = null;
                $reservation->saveQuietly();
            }
        }
    }

    private function generateUniqueDpCode(): string
    {
        $chars = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // tanpa I,O agar tidak bingung
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (Reservation::where('dp_code', $code)->exists());
        return $code;
    }

    public function destroy(Reservation $reservation)
    {
        $reservation->delete();

        return redirect()->route('reservations.index')
            ->with('success', 'Reservasi berhasil dihapus!');
    }

    // ---------- API for Approval App (mobile) ----------

    public function apiIndex(Request $request)
    {
        $query = Reservation::with(['outlet', 'creator', 'salesUser', 'paymentType'])
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->when($request->status, function ($q, $status) {
                $q->where('status', $status);
            })
            ->when($request->date_from, function ($q, $dateFrom) {
                $q->whereDate('reservation_date', '>=', $dateFrom);
            })
            ->when($request->date_to, function ($q, $dateTo) {
                $q->whereDate('reservation_date', '<=', $dateTo);
            })
            ->latest();

        $reservations = $query->get()->map(function ($reservation) {
            return [
                'id' => $reservation->id,
                'name' => $reservation->name,
                'phone' => $reservation->phone,
                'email' => $reservation->email,
                'outlet_id' => $reservation->outlet_id,
                'outlet' => $reservation->outlet ? $reservation->outlet->nama_outlet : null,
                'reservation_date' => $reservation->reservation_date?->format('Y-m-d'),
                'reservation_time' => $reservation->reservation_time ? \Carbon\Carbon::parse($reservation->reservation_time)->format('H:i') : null,
                'number_of_guests' => $reservation->number_of_guests,
                'smoking_preference' => $reservation->smoking_preference,
                'special_requests' => $reservation->special_requests,
                'dp' => $reservation->dp ? (float) $reservation->dp : null,
                'payment_type_id' => $reservation->payment_type_id,
                'payment_type_name' => $reservation->paymentType?->name,
                'from_sales' => (bool) $reservation->from_sales,
                'sales_user_id' => $reservation->sales_user_id,
                'sales_user_name' => $reservation->salesUser ? $reservation->salesUser->nama_lengkap : null,
                'menu' => $reservation->menu,
                'menu_file' => $reservation->menu_file,
                'menu_file_url' => $reservation->menu_file ? Storage::disk('public')->url($reservation->menu_file) : null,
                'status' => $reservation->status,
                'created_by' => $reservation->creator ? ($reservation->creator->nama_lengkap ?? $reservation->creator->name ?? null) : null,
                'created_at' => $reservation->created_at?->toIso8601String(),
            ];
        });

        return response()->json(['data' => $reservations]);
    }

    public function apiCreateData()
    {
        $outlets = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get()
            ->map(function ($o) {
                return ['id' => $o->id_outlet, 'name' => $o->nama_outlet];
            })
            ->values();

        $salesUsers = User::where('division_id', 17)
            ->where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->nama_lengkap])
            ->values();

        return response()->json([
            'outlets' => $outlets,
            'sales_users' => $salesUsers,
        ]);
    }

    /**
     * Ringkasan DP reservasi per tanggal & outlet (untuk Revenue Report).
     * GET /api/reservations/dp-summary?date=YYYY-MM-DD&outlet_id=123
     *
     * Return:
     * - total_dp, breakdown: DP untuk reservasi yang jadwalnya di tanggal report.
     * - dp_future_total, dp_future_breakdown: DP diterima hari ini untuk reservasi tanggal mendatang (created_at = date, reservation_date > date).
     * - orders_using_dp: transaksi hari tersebut yang menggunakan DP (order paid + reservation dengan DP), plus tanggal DP.
     */
    public function apiDpSummary(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'outlet_id' => 'required',
        ]);
        $date = $request->date;
        $outletId = $request->outlet_id;

        // 1) DP untuk reservasi yang jadwalnya di tanggal report (existing)
        $reservations = Reservation::with('paymentType')
            ->whereDate('reservation_date', $date)
            ->where('outlet_id', $outletId)
            ->whereNotNull('dp')
            ->where('dp', '>', 0)
            ->get();

        $totalDp = $reservations->sum(fn ($r) => (float) $r->dp);
        $breakdown = [];
        foreach ($reservations as $r) {
            $name = $r->paymentType ? $r->paymentType->name : 'Lainnya';
            if (!isset($breakdown[$name])) {
                $breakdown[$name] = 0;
            }
            $breakdown[$name] += (float) $r->dp;
        }

        // 2) DP diterima hari ini untuk reservasi tanggal mendatang (created_at = date, reservation_date > date)
        $reservationsFuture = Reservation::with('paymentType')
            ->whereDate('created_at', $date)
            ->where('reservation_date', '>', $date)
            ->where('outlet_id', $outletId)
            ->whereNotNull('dp')
            ->where('dp', '>', 0)
            ->get();

        $dpFutureTotal = $reservationsFuture->sum(fn ($r) => (float) $r->dp);
        $dpFutureBreakdown = [];
        foreach ($reservationsFuture as $r) {
            $name = $r->paymentType ? $r->paymentType->name : 'Lainnya';
            if (!isset($dpFutureBreakdown[$name])) {
                $dpFutureBreakdown[$name] = 0;
            }
            $dpFutureBreakdown[$name] += (float) $r->dp;
        }

        // 3) Transaksi hari tersebut yang menggunakan DP (order paid on date, punya reservation_id dengan DP)
        $kodeOutlet = Outlet::where('id_outlet', $outletId)->value('qr_code');
        $ordersUsingDp = [];
        if ($kodeOutlet && Schema::hasTable('orders') && Schema::hasColumn('orders', 'reservation_id')) {
            $orderRows = DB::table('orders')
                ->whereDate('updated_at', $date)
                ->where('kode_outlet', $kodeOutlet)
                ->where('status', 'paid')
                ->whereNotNull('reservation_id')
                ->get(['id', 'paid_number', 'grand_total', 'reservation_id']);

            $reservationIds = $orderRows->pluck('reservation_id')->unique()->filter()->values()->all();
            if (!empty($reservationIds)) {
                $reservationsWithDp = Reservation::with('paymentType')
                    ->whereIn('id', $reservationIds)
                    ->whereNotNull('dp')
                    ->where('dp', '>', 0)
                    ->get()
                    ->keyBy('id');

                foreach ($orderRows as $row) {
                    $res = $reservationsWithDp->get($row->reservation_id);
                    if (!$res) {
                        continue;
                    }
                    $ordersUsingDp[] = [
                        'paid_number' => $row->paid_number,
                        'grand_total' => (float) $row->grand_total,
                        'reservation_name' => $res->name,
                        'dp_amount' => (float) $res->dp,
                        'dp_paid_at' => $res->created_at?->format('Y-m-d'),
                    ];
                }
            }
        }

        return response()->json([
            'total_dp' => $totalDp,
            'breakdown' => array_values(array_map(fn ($name) => ['payment_type_name' => $name, 'total' => $breakdown[$name]], array_keys($breakdown))),
            'dp_future_total' => $dpFutureTotal,
            'dp_future_breakdown' => array_values(array_map(fn ($name) => ['payment_type_name' => $name, 'total' => $dpFutureBreakdown[$name]], array_keys($dpFutureBreakdown))),
            'orders_using_dp' => $ordersUsingDp,
        ]);
    }

    /**
     * Validasi kode DP untuk transaksi POS.
     * GET ?code=XXX&outlet_id=Y  atau  ?code=XXX&kode_outlet=ZZZ (kode outlet dari setup.json POS).
     * Return { valid, amount, reservation_id, message }.
     */
    public function apiValidateDpCode(Request $request)
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        $outletId = $request->input('outlet_id');
        $kodeOutlet = $request->input('kode_outlet');
        if ($code === '') {
            return response()->json(['valid' => false, 'message' => 'Kode wajib'], 400);
        }
        if (!$outletId && $kodeOutlet !== null && $kodeOutlet !== '') {
            $outlet = Outlet::where('qr_code', $kodeOutlet)->first();
            $outletId = $outlet ? $outlet->id_outlet : null;
        }
        if (!$outletId) {
            return response()->json(['valid' => false, 'message' => 'Outlet wajib (outlet_id atau kode_outlet)'], 400);
        }
        $reservation = Reservation::where('dp_code', $code)
            ->where('outlet_id', $outletId)
            ->whereNull('dp_used_at')
            ->whereNotNull('dp')
            ->where('dp', '>', 0)
            ->first();
        if (!$reservation) {
            return response()->json([
                'valid' => false,
                'message' => 'Kode DP tidak valid atau sudah dipakai',
            ]);
        }
        return response()->json([
            'valid' => true,
            'amount' => (float) $reservation->dp,
            'reservation_id' => $reservation->id,
        ]);
    }

    /**
     * Tandai kode DP sudah dipakai di transaksi (dipanggil POS setelah bayar).
     * POST { "dp_code": "XXXXXXXX" }
     */
    public function apiMarkDpUsed(Request $request)
    {
        $request->validate(['dp_code' => 'required|string|size:8']);
        $code = strtoupper(trim($request->dp_code));
        $updated = Reservation::where('dp_code', $code)
            ->whereNull('dp_used_at')
            ->where('dp', '>', 0)
            ->update(['dp_used_at' => now()]);
        if ($updated === 0) {
            return response()->json(['success' => false, 'message' => 'Kode tidak ditemukan atau sudah dipakai'], 404);
        }
        return response()->json(['success' => true]);
    }

    /**
     * Update status reservasi dari POS (mis. Set Datang -> arrived, Cancel -> cancelled).
     * PATCH /api/reservations/{id}/status  body: { "status": "arrived" }
     */
    public function apiUpdateStatus(Request $request, $id)
    {
        $status = $request->input('status');
        $allowed = ['pending', 'confirmed', 'arrived', 'cancelled', 'no_show'];
        if (!$status || !in_array($status, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Status tidak valid. Gunakan: ' . implode(', ', $allowed)], 400);
        }
        $reservation = Reservation::find($id);
        if (!$reservation) {
            return response()->json(['success' => false, 'message' => 'Reservasi tidak ditemukan'], 404);
        }
        $reservation->status = $status;
        $reservation->save();
        return response()->json(['success' => true, 'status' => $reservation->status]);
    }

    public function apiShow($id)
    {
        $reservation = Reservation::with(['outlet', 'creator', 'salesUser', 'paymentType'])->find($id);
        if (!$reservation) {
            return response()->json(['message' => 'Reservasi tidak ditemukan'], 404);
        }
        return response()->json([
            'id' => $reservation->id,
            'name' => $reservation->name,
            'phone' => $reservation->phone,
            'email' => $reservation->email,
            'outlet_id' => $reservation->outlet_id,
            'outlet' => $reservation->outlet ? $reservation->outlet->nama_outlet : null,
            'reservation_date' => $reservation->reservation_date?->format('Y-m-d'),
            'reservation_time' => $reservation->reservation_time ? \Carbon\Carbon::parse($reservation->reservation_time)->format('H:i') : null,
            'number_of_guests' => $reservation->number_of_guests,
            'smoking_preference' => $reservation->smoking_preference,
            'special_requests' => $reservation->special_requests,
            'dp' => $reservation->dp ? (float) $reservation->dp : null,
            'payment_type_id' => $reservation->payment_type_id,
            'payment_type_name' => $reservation->paymentType?->name,
            'dp_code' => $reservation->dp_code,
            'dp_used_at' => $reservation->dp_used_at?->toIso8601String(),
            'from_sales' => (bool) $reservation->from_sales,
            'sales_user_id' => $reservation->sales_user_id,
            'sales_user_name' => $reservation->salesUser ? $reservation->salesUser->nama_lengkap : null,
            'menu' => $reservation->menu,
            'menu_file' => $reservation->menu_file,
            'menu_file_url' => $reservation->menu_file ? Storage::disk('public')->url($reservation->menu_file) : null,
            'status' => $reservation->status,
            'created_by' => $reservation->creator ? ($reservation->creator->nama_lengkap ?? $reservation->creator->name ?? null) : null,
            'created_at' => $reservation->created_at?->toIso8601String(),
        ]);
    }

    public function apiStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:100',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'reservation_date' => 'required|date',
                'reservation_time' => 'required',
                'number_of_guests' => 'required|integer|min:1',
                'smoking_preference' => 'nullable|in:smoking,non_smoking',
                'special_requests' => 'nullable|string',
                'dp' => 'nullable|numeric|min:0',
                'payment_type_id' => 'nullable|exists:payment_types,id',
                'from_sales' => 'nullable|boolean',
                'sales_user_id' => 'nullable|exists:users,id',
                'menu' => 'nullable|string',
                'menu_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,xls,xlsx|max:10240',
                'status' => 'required|in:pending,confirmed,cancelled',
            ]);
            $validated['created_by'] = auth()->id();
            $validated['email'] = $request->filled('email') ? trim((string) $request->input('email')) : null;
            $validated['from_sales'] = filter_var($request->input('from_sales'), FILTER_VALIDATE_BOOLEAN);
            if (empty($validated['from_sales'])) {
                $validated['sales_user_id'] = null;
            }
            if ($request->hasFile('menu_file')) {
                $validated['menu_file'] = $request->file('menu_file')->storeAs(
                    'reservations/menu',
                    Str::uuid() . '.' . $request->file('menu_file')->getClientOriginalExtension(),
                    'public'
                );
            } else {
                unset($validated['menu_file']);
            }
            $reservation = Reservation::create($validated);
            return response()->json([
                'message' => 'Reservasi berhasil ditambahkan',
                'id' => $reservation->id,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            \Log::error('Reservation apiStore: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal menyimpan reservasi'], 500);
        }
    }

    public function apiUpdate(Request $request, $id)
    {
        $reservation = Reservation::find($id);
        if (!$reservation) {
            return response()->json(['message' => 'Reservasi tidak ditemukan'], 404);
        }
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:100',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'reservation_date' => 'required|date',
                'reservation_time' => 'required',
                'number_of_guests' => 'required|integer|min:1',
                'smoking_preference' => 'nullable|in:smoking,non_smoking',
                'special_requests' => 'nullable|string',
                'dp' => 'nullable|numeric|min:0',
                'payment_type_id' => 'nullable|exists:payment_types,id',
                'from_sales' => 'nullable|boolean',
                'sales_user_id' => 'nullable|exists:users,id',
                'menu' => 'nullable|string',
                'menu_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,xls,xlsx|max:10240',
                'status' => 'required|in:pending,confirmed,cancelled',
            ]);
            $validated['email'] = $request->filled('email') ? trim((string) $request->input('email')) : null;
            $validated['from_sales'] = filter_var($request->input('from_sales'), FILTER_VALIDATE_BOOLEAN);
            if (empty($validated['from_sales'])) {
                $validated['sales_user_id'] = null;
            }
            if ($request->hasFile('menu_file')) {
                if ($reservation->menu_file && Storage::disk('public')->exists($reservation->menu_file)) {
                    Storage::disk('public')->delete($reservation->menu_file);
                }
                $validated['menu_file'] = $request->file('menu_file')->storeAs(
                    'reservations/menu',
                    Str::uuid() . '.' . $request->file('menu_file')->getClientOriginalExtension(),
                    'public'
                );
            } else {
                unset($validated['menu_file']);
            }
            $reservation->update($validated);
            return response()->json(['message' => 'Reservasi berhasil diupdate']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            \Log::error('Reservation apiUpdate: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal mengupdate reservasi'], 500);
        }
    }

    /**
     * Payment types untuk form reservasi: dipakai per outlet/region.
     * Return array dengan id, name, code, outlet_ids, region_ids agar frontend bisa filter by outlet.
     */
    private function getPaymentTypesForReservationForm(): array
    {
        $paymentTypes = PaymentType::where('status', 'active')
            ->with(['outlets:id_outlet', 'regions:id'])
            ->orderBy('name')
            ->get();

        return $paymentTypes->map(function ($pt) {
            return [
                'id' => $pt->id,
                'name' => $pt->name,
                'code' => $pt->code,
                'outlet_ids' => $pt->outlets->pluck('id_outlet')->values()->all(),
                'region_ids' => $pt->regions->pluck('id')->values()->all(),
            ];
        })->values()->all();
    }
} 