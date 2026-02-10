<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Models\Reservation;
use App\Models\Outlet;
use App\Models\User;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $query = Reservation::with(['outlet', 'creator', 'salesUser'])
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
                'outlet' => $reservation->outlet->nama_outlet,
                'reservation_date' => $reservation->reservation_date,
                'reservation_time' => $reservation->reservation_time,
                'number_of_guests' => $reservation->number_of_guests,
                'smoking_preference' => $reservation->smoking_preference,
                'dp' => $reservation->dp,
                'from_sales' => $reservation->from_sales,
                'sales_user_id' => $reservation->sales_user_id,
                'sales_user_name' => $reservation->salesUser ? $reservation->salesUser->nama_lengkap : null,
                'menu' => $reservation->menu,
                'status' => $reservation->status,
                'created_by' => $reservation->creator ? $reservation->creator->name : '-',
            ];
        });

        return Inertia::render('Reservations/Index', [
            'reservations' => $reservations,
            'search' => $request->search,
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
            ->get()
            ->map(function($o) {
                return [
                    'id' => $o->id_outlet,
                    'name' => $o->nama_outlet,
                ];
            })
            ->values();

        $salesUsers = User::where('division_id', 17)
            ->where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->nama_lengkap])
            ->values();

        return Inertia::render('Reservations/Form', [
            'outlets' => $outlets,
            'salesUsers' => $salesUsers,
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
                $validated['menu_file'] = $request->file('menu_file')->store('reservations/menu', 'public');
            } else {
                unset($validated['menu_file']);
            }

            $reservation = Reservation::create($validated);

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
        $reservation->load(['outlet', 'creator', 'salesUser']);
        return Inertia::render('Reservations/Show', [
            'reservation' => $reservation
        ]);
    }

    public function edit(Reservation $reservation)
    {
        $outlets = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get()
            ->map(function($o) {
                return [
                    'id' => $o->id_outlet,
                    'name' => $o->nama_outlet,
                ];
            })
            ->values();

        $salesUsers = User::where('division_id', 17)
            ->where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->nama_lengkap])
            ->values();

        return Inertia::render('Reservations/Form', [
            'reservation' => $reservation,
            'outlets' => $outlets,
            'salesUsers' => $salesUsers,
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
            $validated['menu_file'] = $request->file('menu_file')->store('reservations/menu', 'public');
        } else {
            unset($validated['menu_file']);
        }

        $reservation->update($validated);

        return redirect()->route('reservations.index')
            ->with('success', 'Reservasi berhasil diupdate!');
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
        $query = Reservation::with(['outlet', 'creator', 'salesUser'])
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

    public function apiShow($id)
    {
        $reservation = Reservation::with(['outlet', 'creator', 'salesUser'])->find($id);
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
                $validated['menu_file'] = $request->file('menu_file')->store('reservations/menu', 'public');
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
                $validated['menu_file'] = $request->file('menu_file')->store('reservations/menu', 'public');
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
} 