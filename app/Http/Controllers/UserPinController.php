<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class UserPinController extends Controller
{
    /**
     * Get user pins for the authenticated user
     */
    public function index()
    {
        $user = Auth::user();
        
        $userPins = DB::table('user_pins')
            ->join('tbl_data_outlet', 'user_pins.outlet_id', '=', 'tbl_data_outlet.id_outlet')
            ->where('user_pins.user_id', $user->id)
            ->select(
                'user_pins.*',
                'tbl_data_outlet.nama_outlet'
            )
            ->orderBy('user_pins.created_at', 'desc')
            ->get();

        return response()->json($userPins);
    }

    /**
     * Store a new user pin
     */
    public function store(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'pin' => 'required|string|min:1|max:10',
        ]);

        $user = Auth::user();

        // Check if user already has a pin for this outlet
        $existingPin = DB::table('user_pins')
            ->where('user_id', $user->id)
            ->where('outlet_id', $request->outlet_id)
            ->where('is_active', 1)
            ->first();

        if ($existingPin) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah memiliki PIN untuk outlet ini. Silakan update PIN yang sudah ada.'
            ], 400);
        }

        try {
            $pinId = DB::table('user_pins')->insertGetId([
                'user_id' => $user->id,
                'outlet_id' => $request->outlet_id,
                'pin' => $request->pin,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Get outlet name for response
            $outlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', $request->outlet_id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => "PIN berhasil dibuat untuk outlet {$outlet->nama_outlet}",
                'data' => [
                    'id' => $pinId,
                    'outlet_name' => $outlet->nama_outlet,
                    'pin' => $request->pin
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat PIN: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing user pin
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'pin' => 'required|string|min:1|max:10',
        ]);

        $user = Auth::user();

        // Check if pin belongs to user
        $userPin = DB::table('user_pins')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$userPin) {
            return response()->json([
                'success' => false,
                'message' => 'PIN tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        try {
            DB::table('user_pins')
                ->where('id', $id)
                ->update([
                    'pin' => $request->pin,
                    'updated_at' => now(),
                ]);

            // Get outlet name for response
            $outlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', $userPin->outlet_id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => "PIN berhasil diupdate untuk outlet {$outlet->nama_outlet}",
                'data' => [
                    'id' => $id,
                    'outlet_name' => $outlet->nama_outlet,
                    'pin' => $request->pin
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate PIN: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a user pin
     */
    public function destroy($id)
    {
        $user = Auth::user();

        // Check if pin belongs to user
        $userPin = DB::table('user_pins')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$userPin) {
            return response()->json([
                'success' => false,
                'message' => 'PIN tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        try {
            DB::table('user_pins')->where('id', $id)->delete();

            // Get outlet name for response
            $outlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', $userPin->outlet_id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => "PIN berhasil dihapus untuk outlet {$outlet->nama_outlet}"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus PIN: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get outlets for dropdown
     */
    public function getOutlets()
    {
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return response()->json($outlets);
    }

    /**
     * Get user pins for admin management (for specific user)
     */
    public function getUserPins($userId)
    {
        $userPins = DB::table('user_pins')
            ->leftJoin('tbl_data_outlet', 'user_pins.outlet_id', '=', 'tbl_data_outlet.id_outlet')
            ->where('user_pins.user_id', $userId)
            ->select(
                'user_pins.*',
                'tbl_data_outlet.nama_outlet'
            )
            ->orderBy('user_pins.created_at', 'desc')
            ->get();

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return response()->json([
            'pins' => $userPins,
            'outlets' => $outlets
        ]);
    }

    /**
     * Store a new user pin for admin management
     */
    public function storeUserPin(Request $request, $userId)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'pin' => 'required|string|min:1|max:20',
            'is_active' => 'required|boolean',
        ]);

        // Check if user already has a pin for this outlet
        $existingPin = DB::table('user_pins')
            ->where('user_id', $userId)
            ->where('outlet_id', $request->outlet_id)
            ->where('is_active', 1)
            ->first();

        if ($existingPin) {
            return response()->json([
                'success' => false,
                'message' => 'User sudah memiliki PIN aktif untuk outlet ini.'
            ], 400);
        }

        try {
            $pinId = DB::table('user_pins')->insertGetId([
                'user_id' => $userId,
                'outlet_id' => $request->outlet_id,
                'pin' => $request->pin,
                'is_active' => $request->is_active ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'PIN berhasil ditambahkan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat PIN: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing user pin for admin management
     */
    public function updateUserPin(Request $request, $id)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'pin' => 'required|string|min:1|max:20',
            'is_active' => 'required|boolean',
        ]);

        try {
            DB::table('user_pins')
                ->where('id', $id)
                ->update([
                    'outlet_id' => $request->outlet_id,
                    'pin' => $request->pin,
                    'is_active' => $request->is_active ? 1 : 0,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'PIN berhasil diupdate'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate PIN: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a user pin for admin management
     */
    public function destroyUserPin($id)
    {
        try {
            DB::table('user_pins')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'PIN berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus PIN: ' . $e->getMessage()
            ], 500);
        }
    }
}