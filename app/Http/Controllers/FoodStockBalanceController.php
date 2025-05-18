<?php

namespace App\Http\Controllers;

use App\Models\FoodStockBalance;
use App\Models\Item;
use App\Models\Warehouse;
use App\Models\Unit;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Exports\FoodStockBalanceImportTemplateExport;
use App\Imports\FoodStockBalanceImport;
use Maatwebsite\Excel\Facades\Excel;

class FoodStockBalanceController extends Controller
{
    public function index(Request $request)
    {
        $query = FoodStockBalance::with([
            'product',
            'warehouse',
            'unit',
            'creator'
        ])
        ->select('food_stock_balances.*');

        // Filter by warehouse
        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by product
        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        // Search
        if ($request->search) {
            $search = $request->search;
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('code', 'like', "%$search%");
            });
        }

        $stockBalances = $query->orderByDesc('created_at')->paginate(10)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get();
        $products = Item::orderBy('name')->get();
        $units = Unit::all();

        return Inertia::render('FoodStockBalances/Index', [
            'stockBalances' => $stockBalances,
            'warehouses' => $warehouses,
            'products' => $products,
            'units' => $units,
            'filters' => $request->only(['search', 'warehouse_id', 'product_id']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0',
            'unit_id' => 'required|exists:units,id',
            'batch_number' => 'nullable|string',
            'expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Cek apakah sudah ada stok balance untuk item dan warehouse ini
            $existingBalance = FoodStockBalance::where('product_id', $request->product_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->first();

            if ($existingBalance) {
                throw new \Exception('Stok balance untuk item ini di warehouse ini sudah ada');
            }

            $stockBalance = FoodStockBalance::create([
                'product_id' => $request->product_id,
                'warehouse_id' => $request->warehouse_id,
                'quantity' => $request->quantity,
                'unit_id' => $request->unit_id,
                'batch_number' => $request->batch_number,
                'expiry_date' => $request->expiry_date,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
            ]);

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'stock_balance',
                'description' => 'Create Stock Balance: ' . $stockBalance->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($request->all()),
                'created_at' => now(),
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Stock balance berhasil disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'product_id' => 'required|exists:items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0',
            'unit_id' => 'required|exists:units,id',
            'batch_number' => 'nullable|string',
            'expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $stockBalance = FoodStockBalance::findOrFail($id);

            // Cek apakah sudah ada stok balance lain untuk item dan warehouse ini
            $existingBalance = FoodStockBalance::where('product_id', $request->product_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->where('id', '!=', $id)
                ->first();

            if ($existingBalance) {
                throw new \Exception('Stok balance untuk item ini di warehouse ini sudah ada');
            }

            $oldData = $stockBalance->toArray();
            $stockBalance->update([
                'product_id' => $request->product_id,
                'warehouse_id' => $request->warehouse_id,
                'quantity' => $request->quantity,
                'unit_id' => $request->unit_id,
                'batch_number' => $request->batch_number,
                'expiry_date' => $request->expiry_date,
                'notes' => $request->notes,
            ]);

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'stock_balance',
                'description' => 'Update Stock Balance: ' . $id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => json_encode($oldData),
                'new_data' => json_encode($request->all()),
                'created_at' => now(),
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Stock balance berhasil diupdate']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $stockBalance = FoodStockBalance::findOrFail($id);
            $oldData = $stockBalance->toArray();
            $stockBalance->delete();

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'stock_balance',
                'description' => 'Delete Stock Balance: ' . $id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($oldData),
                'new_data' => null,
                'created_at' => now(),
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Stock balance berhasil dihapus']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function downloadTemplate()
    {
        return Excel::download(new FoodStockBalanceImportTemplateExport, 'stock_balance_template.xlsx');
    }

    public function previewImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        try {
            // HANYA AMBIL DATA, JANGAN INSERT KE DB
            $sheets = Excel::toArray(new FoodStockBalanceImport, $request->file('file'));
            if (!isset($sheets['StockBalance']) || empty($sheets['StockBalance'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sheet StockBalance tidak ditemukan atau kosong'
                ], 422);
            }
            $preview = array_slice($sheets['StockBalance'], 0, 5);

            return response()->json([
                'success' => true,
                'preview' => $preview,
                'total_rows' => count($preview),
                'message' => 'File berhasil dibaca'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        try {
            $import = new FoodStockBalanceImport;
            Excel::import($import, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => "Berhasil mengimport {$import->getSuccessCount()} data",
                'errors' => $import->getErrors(),
                'error_count' => $import->getErrorCount(),
                'success_count' => $import->getSuccessCount()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
} 