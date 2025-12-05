<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Outlet;
use App\Models\Item;
use App\Models\Unit;
use App\Models\ActivityLog;
use App\Exports\FoodStockBalanceImportTemplateExport;
use App\Imports\OutletStockBalanceImport;
use Maatwebsite\Excel\Facades\Excel;

class OutletStockBalanceController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
            ->join('items as i', 'fi.item_id', '=', 'i.id')
            ->join('tbl_data_outlet as o', 's.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->select(
                's.id',
                'i.id as product_id',
                'i.name as product_name',
                'i.sku as product_code',
                'o.id_outlet',
                'o.nama_outlet',
                's.qty_small',
                's.qty_medium',
                's.qty_large',
                'us.name as unit_name_small',
                'um.name as unit_name_medium',
                'ul.name as unit_name_large',
                's.created_at',
                's.updated_at'
            );
        if ($request->outlet_id) {
            $query->where('o.id_outlet', $request->outlet_id);
        }
        if ($request->product_id) {
            $query->where('i.id', $request->product_id);
        }
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('i.name', 'like', "%$search%")
                  ->orWhere('i.sku', 'like', "%$search%")
                  ->orWhere('o.nama_outlet', 'like', "%$search%")
                ;
            });
        }
        $stockBalances = $query->orderByDesc('s.created_at')->paginate(10)->withQueryString();
        $outlets = DB::table('tbl_data_outlet')->where('status', 'A')->select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        $products = DB::table('items')->select('id', 'name')->orderBy('name')->get();
        $units = DB::table('units')->select('id', 'name')->get();
        return Inertia::render('OutletStockBalances/Index', [
            'stockBalances' => $stockBalances,
            'outlets' => $outlets,
            'products' => $products,
            'units' => $units,
            'filters' => $request->only(['search', 'outlet_id', 'product_id']),
        ]);
    }

    public function downloadTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\OutletStockBalanceImportTemplateExport,
            'template_saldo_awal_stok_outlet.xlsx'
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);
        try {
            $import = new \App\Imports\OutletStockBalanceImport;
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));
            return response()->json([
                'success' => true,
                'message' => "Berhasil mengimport {$import->getSuccessCount()} data",
                'errors' => method_exists($import, 'getErrors') ? $import->getErrors() : [],
                'error_count' => method_exists($import, 'getErrorCount') ? $import->getErrorCount() : 0,
                'success_count' => method_exists($import, 'getSuccessCount') ? $import->getSuccessCount() : 0
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function previewImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);
        try {
            $sheets = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\OutletStockBalanceImport, $request->file('file'));
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

    // ... store, update, destroy, import, previewImport, downloadTemplate mirip, tapi ke tabel outlet
} 