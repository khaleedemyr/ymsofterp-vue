<?php
namespace App\Http\Controllers;

use App\Models\UserPin;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserPinController extends Controller
{
    // List all pins for a user
    public function index($userId)
    {
        $pins = UserPin::with('outlet')
            ->where('user_id', $userId)
            ->get();
        $outlets = Outlet::select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        return response()->json([
            'pins' => $pins,
            'outlets' => $outlets,
        ]);
    }

    // Store new pin for user
    public function store(Request $request, $userId)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'pin' => 'required|string|max:20',
            'is_active' => 'boolean',
        ]);
        // Optional: pastikan kombinasi user_id + outlet_id unik
        $exists = UserPin::where('user_id', $userId)
            ->where('outlet_id', $validated['outlet_id'])
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'PIN untuk outlet ini sudah ada.'], 422);
        }
        $validated['user_id'] = $userId;
        $pin = UserPin::create($validated);
        return response()->json(['success' => true, 'pin' => $pin]);
    }

    // Update pin
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'pin' => 'required|string|max:20',
            'is_active' => 'boolean',
        ]);
        $pin = UserPin::findOrFail($id);
        // Optional: pastikan kombinasi user_id + outlet_id unik (kecuali record ini)
        $exists = UserPin::where('user_id', $pin->user_id)
            ->where('outlet_id', $validated['outlet_id'])
            ->where('id', '!=', $id)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'PIN untuk outlet ini sudah ada.'], 422);
        }
        $pin->update($validated);
        return response()->json(['success' => true, 'pin' => $pin]);
    }

    // Delete pin
    public function destroy($id)
    {
        $pin = UserPin::findOrFail($id);
        $pin->delete();
        return response()->json(['success' => true]);
    }
} 