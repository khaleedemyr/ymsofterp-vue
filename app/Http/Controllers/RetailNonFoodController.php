<?php

namespace App\Http\Controllers;

use App\Models\RetailNonFood;
use App\Models\RetailNonFoodItem;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RetailNonFoodController extends Controller
{
    private function generateRetailNumber()
    {
        $prefix = 'RNF';
        $date = date('Ymd');
        
        // Cari nomor terakhir hari ini
        $lastNumber = RetailNonFood::where('retail_number', 'like', $prefix . $date . '%')
            ->orderBy('retail_number', 'desc')
            ->first();
            
        if ($lastNumber) {
            $sequence = (int) substr($lastNumber->retail_number, -4) + 1;
        } else {
            $sequence = 1;
        }
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function index()
    {
        $user = auth()->user()->load('outlet');
        $userOutletId = $user->id_outlet;
        $outletExists = \DB::table('tbl_data_outlet')->where('id_outlet', $userOutletId)->exists();
        if (!$outletExists && $userOutletId != 1) {
            abort(403, 'Outlet tidak terdaftar');
        }
        
        // Query dengan join warehouse outlet
        $query = RetailNonFood::query()
            ->with(['outlet', 'creator', 'items'])
            ->orderByDesc('created_at');
            
        if ($userOutletId != 1) {
            $query->where('outlet_id', $userOutletId);
        }
        
        $retailNonFoods = $query->paginate(10);
        
        return Inertia::render('RetailNonFood/Index', [
            'user' => $user,
            'retailNonFoods' => $retailNonFoods,
        ]);
    }

    public function create()
    {
        $user = auth()->user()->load('outlet');
        $userOutletId = $user->id_outlet;
        $outletExists = \DB::table('tbl_data_outlet')->where('id_outlet', $userOutletId)->exists();
        if (!$outletExists && $userOutletId != 1) {
            abort(403, 'Outlet tidak terdaftar');
        }
        if ($userOutletId == 1) {
            $outlets = Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']);
        } else {
            $outlets = Outlet::where('id_outlet', $userOutletId)->where('status', 'A')->get(['id_outlet', 'nama_outlet']);
        }
        return Inertia::render('RetailNonFood/Form', [
            'user' => $user,
            'outlets' => $outlets
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'transaction_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string',
            'items.*.qty' => 'required|numeric|min:0',
            'items.*.unit' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Generate nomor retail non food
            $retailNumber = $this->generateRetailNumber();

            // Hitung total amount
            $totalAmount = collect($request->items)->sum(function ($item) {
                return $item['qty'] * $item['price'];
            });

            // Cek total transaksi hari ini
            $dailyTotal = RetailNonFood::whereDate('transaction_date', $request->transaction_date)
                ->where('status', 'approved')
                ->sum('total_amount');

            // Buat retail non food
            $retailNonFood = RetailNonFood::create([
                'retail_number' => $retailNumber,
                'outlet_id' => $request->outlet_id,
                'created_by' => auth()->id(),
                'transaction_date' => $request->transaction_date,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
                'status' => 'approved'
            ]);

            // Simpan items (tanpa inventory processing)
            foreach ($request->items as $item) {
                RetailNonFoodItem::create([
                    'retail_non_food_id' => $retailNonFood->id,
                    'item_name' => $item['item_name'],
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'subtotal' => $item['qty'] * $item['price']
                ]);
            }

            DB::commit();

            // Cek apakah total hari ini sudah melebihi 500rb
            if ($dailyTotal + $totalAmount >= 500000) {
                return response()->json([
                    'message' => 'Transaksi berhasil disimpan, namun total pembelian hari ini sudah melebihi Rp 500.000',
                    'data' => $retailNonFood->load('items')
                ], 201);
            }

            return response()->json([
                'message' => 'Transaksi berhasil disimpan',
                'data' => $retailNonFood->load('items')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan transaksi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $retailNonFood = RetailNonFood::with(['outlet', 'creator', 'items', 'warehouseOutlet'])
            ->findOrFail($id);

        return Inertia::render('RetailNonFood/Detail', [
            'retailNonFood' => $retailNonFood
        ]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $retailNonFood = RetailNonFood::with('items')->findOrFail($id);
            if ($retailNonFood->status === 'approved') {
                return response()->json([
                    'message' => 'Tidak dapat menghapus transaksi yang sudah diapprove'
                ], 422);
            }
            
            // Hapus retail non food dan items (tanpa inventory rollback)
            RetailNonFoodItem::where('retail_non_food_id', $retailNonFood->id)->delete();
            $retailNonFood->delete();
            
            DB::commit();
            return response()->json([
                'message' => 'Transaksi berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus transaksi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function dailyTotal(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'transaction_date' => 'required|date',
        ]);
        
        $total = RetailNonFood::where('outlet_id', $request->outlet_id)
            ->whereDate('transaction_date', $request->transaction_date)
            ->sum('total_amount');
            
        return response()->json(['total' => $total]);
    }
} 