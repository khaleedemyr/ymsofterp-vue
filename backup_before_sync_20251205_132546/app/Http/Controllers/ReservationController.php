<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Reservation;
use App\Models\Outlet;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $query = Reservation::with(['outlet', 'creator'])
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

        return Inertia::render('Reservations/Form', [
            'outlets' => $outlets,
            'isEdit' => false
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'phone' => 'required|string|max:20',
                'email' => 'required|email|max:100',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'reservation_date' => 'required|date',
                'reservation_time' => 'required',
                'number_of_guests' => 'required|integer|min:1',
                'special_requests' => 'nullable|string',
                'status' => 'required|in:pending,confirmed,cancelled',
            ]);

            // Add created_by with authenticated user ID
            $validated['created_by'] = auth()->id();

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
        $reservation->load(['outlet', 'creator']);
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

        return Inertia::render('Reservations/Form', [
            'reservation' => $reservation,
            'outlets' => $outlets,
            'isEdit' => true
        ]);
    }

    public function update(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:100',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'reservation_date' => 'required|date',
            'reservation_time' => 'required',
            'number_of_guests' => 'required|integer|min:1',
            'special_requests' => 'nullable|string',
            'status' => 'required|in:pending,confirmed,cancelled',
        ]);

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
} 