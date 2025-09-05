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

    public function index(Request $request)
    {
        $user = auth()->user()->load('outlet');
        $userOutletId = $user->id_outlet;
        $outletExists = \DB::table('tbl_data_outlet')->where('id_outlet', $userOutletId)->exists();
        if (!$outletExists && $userOutletId != 1) {
            abort(403, 'Outlet tidak terdaftar');
        }

        // Get filter parameters
        $search = $request->get('search', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        
        // Query dengan join warehouse outlet
        $query = RetailNonFood::query()
            ->with(['outlet', 'creator', 'items'])
            ->orderByDesc('created_at');
            
        // Apply outlet filter
        if ($userOutletId != 1) {
            $query->where('outlet_id', $userOutletId);
        }

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('retail_number', 'like', "%{$search}%")
                  ->orWhereHas('outlet', function($outletQuery) use ($search) {
                      $outletQuery->where('nama_outlet', 'like', "%{$search}%");
                  });
            });
        }

        // Apply date filter
        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }
        
        $retailNonFoods = $query->paginate(10)->withQueryString();
        
        return Inertia::render('RetailNonFood/Index', [
            'user' => $user,
            'retailNonFoods' => $retailNonFoods,
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]
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

            // Setelah RetailNonFood berhasil dibuat
            if ($request->hasFile('invoices')) {
                foreach ($request->file('invoices') as $file) {
                    if (in_array($file->extension(), ['jpg', 'jpeg', 'png'])) {
                        $path = $file->store('retail_non_food_invoices', 'public');
                        $retailNonFood->invoices()->create([
                            'file_path' => $path
                        ]);
                    }
                }
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
        $retailNonFood = RetailNonFood::with(['outlet', 'creator', 'items', 'warehouseOutlet', 'invoices'])
            ->findOrFail($id);

        return Inertia::render('RetailNonFood/Detail', [
            'retailNonFood' => $retailNonFood
        ]);
    }

    public function destroy($id)
    {
        try {
            $user = auth()->user();
            
            // Check if user has permission to delete (only admin with id_outlet = 1)
            if ($user->id_outlet !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk menghapus transaksi retail non food'
                ], 403);
            }

            $retailNonFood = RetailNonFood::findOrFail($id);

            \Log::info('RETAIL_NON_FOOD_DELETE: Starting deletion process', [
                'user_id' => $user->id,
                'retail_non_food_id' => $id,
                'retail_number' => $retailNonFood->retail_number
            ]);

            DB::beginTransaction();
            
            // Hapus retail non food dan items (tanpa inventory rollback)
            RetailNonFoodItem::where('retail_non_food_id', $retailNonFood->id)->delete();
            $retailNonFood->delete();
            
            DB::commit();

            \Log::info('RETAIL_NON_FOOD_DELETE: Deletion completed successfully', [
                'retail_non_food_id' => $id,
                'retail_number' => $retailNonFood->retail_number
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi retail non food berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('RETAIL_NON_FOOD_DELETE: Deletion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'retail_non_food_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus transaksi: ' . $e->getMessage()
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