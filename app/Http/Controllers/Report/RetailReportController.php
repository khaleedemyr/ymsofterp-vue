<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Traits\ReportHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retail Report Controller
 * 
 * Handles retail sales reports and exports
 * Split from ReportController for better organization and performance
 * 
 * Functions:
 * - retailSalesDetail: Get retail sales detail API
 * - retailDetailPdf: Export retail detail to PDF
 * - retailDetailExcel: Export retail detail to Excel
 */
class RetailReportController extends Controller
{
    use ReportHelperTrait;
    
    /**
     * Get Retail Sales Detail
     * 
     * Returns detailed items for retail warehouse sales
     * Grouped by sub-category
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function retailSalesDetail(Request $request)
    {
        $request->validate([
            'customer' => 'required|string',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $items = DB::table('retail_warehouse_sales as rws')
            ->join('retail_warehouse_sale_items as rwsi', 'rws.id', '=', 'rwsi.retail_warehouse_sale_id')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->join('items as it', 'rwsi.item_id', '=', 'it.id')
            ->join('categories as cat', 'it.category_id', '=', 'cat.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->where('c.name', $request->customer)
            ->whereDate('rws.created_at', '>=', $request->from)
            ->whereDate('rws.created_at', '<=', $request->to)
            ->select(
                'cat.name as category',
                'sc.name as sub_category',
                'it.name as item_name',
                'rwsi.qty',
                'rwsi.unit',
                'rwsi.price',
                'rwsi.subtotal',
                'rws.number as sale_number',
                'rws.created_at as sale_date'
            )
            ->orderBy('cat.name')
            ->orderBy('sc.name')
            ->orderBy('it.name')
            ->get();

        // Group by sub_category
        $grouped = [];
        foreach ($items as $item) {
            $subCat = $item->sub_category;
            if (!isset($grouped[$subCat])) $grouped[$subCat] = [];
            $grouped[$subCat][] = $item;
        }
        
        return response()->json($grouped);
    }

    /**
     * Export Retail Detail to PDF
     * 
     * Generates PDF report for retail warehouse sales detail
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function retailDetailPdf(Request $request)
    {
        try {
            $request->validate([
                'customer' => 'required|string',
                'from' => 'required|date',
                'to' => 'required|date',
            ]);

            $customer = $request->customer;
            $from = $request->from;
            $to = $request->to;

            // Get retail sales detail data with error handling
            $retailData = DB::table('retail_warehouse_sales as rws')
                ->join('retail_warehouse_sale_items as rwsi', 'rws.id', '=', 'rwsi.retail_warehouse_sale_id')
                ->join('customers as c', 'rws.customer_id', '=', 'c.id')
                ->join('items as it', 'rwsi.item_id', '=', 'it.id')
                ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->leftJoin('warehouses as w', 'rws.warehouse_id', '=', 'w.id')
                ->where('c.name', $customer)
                ->whereDate('rws.created_at', '>=', $from)
                ->whereDate('rws.created_at', '<=', $to)
                ->select(
                    'it.name as item_name',
                    DB::raw('COALESCE(sc.name, "Uncategorized") as category'),
                    'rwsi.qty',
                    'rwsi.price',
                    'rwsi.subtotal',
                    'rws.number as sale_number',
                    'rws.created_at as sale_date'
                )
                ->orderBy('category')
                ->orderBy('it.name')
                ->get();

            // Group by category
            $groupedData = [];
            foreach ($retailData as $item) {
                $category = $item->category ?: 'Uncategorized';
                if (!isset($groupedData[$category])) {
                    $groupedData[$category] = [];
                }
                $groupedData[$category][] = $item;
            }

            // Calculate totals
            $totalAmount = $retailData->sum('subtotal');

            // Generate PDF
            $pdf = \PDF::loadView('reports.retail-detail-pdf', [
                'customer' => $customer,
                'from' => $from,
                'to' => $to,
                'detailData' => $groupedData,
                'totalAmount' => $totalAmount,
            ]);

            // Optimize PDF settings for compact layout
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('margin-top', 10);
            $pdf->setOption('margin-bottom', 10);
            $pdf->setOption('margin-left', 10);
            $pdf->setOption('margin-right', 10);
            $pdf->setOption('dpi', 96);

            // Clean filename from invalid characters and ensure it's safe
            $cleanCustomer = preg_replace('/[^a-zA-Z0-9\s\-_]/', '_', $customer);
            $cleanCustomer = trim($cleanCustomer); // Remove leading/trailing spaces
            $cleanCustomer = preg_replace('/\s+/', '_', $cleanCustomer); // Replace multiple spaces with single underscore
            $filename = "Retail_Detail_{$cleanCustomer}_{$from}_{$to}.pdf";
            
            return $pdf->download($filename);
            
        } catch (\Exception $e) {
            Log::error('Retail Detail PDF Error: ' . $e->getMessage(), [
                'customer' => $request->customer ?? 'unknown',
                'from' => $request->from ?? 'unknown',
                'to' => $request->to ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Gagal generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Retail Detail to Excel
     * 
     * Generates Excel report for retail warehouse sales detail
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function retailDetailExcel(Request $request)
    {
        try {
            $request->validate([
                'customer' => 'required|string',
                'from' => 'required|date',
                'to' => 'required|date',
            ]);

            $customer = $request->customer;
            $from = $request->from;
            $to = $request->to;

            // Get retail sales data (using same tables as retailSalesDetail and retailDetailPdf)
            $retailData = DB::table('retail_warehouse_sales as rws')
                ->join('retail_warehouse_sale_items as rwsi', 'rws.id', '=', 'rwsi.retail_warehouse_sale_id')
                ->join('customers as c', 'rws.customer_id', '=', 'c.id')
                ->join('items as it', 'rwsi.item_id', '=', 'it.id')
                ->leftJoin('categories as cat', 'it.category_id', '=', 'cat.id')
                ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->where('c.name', $customer)
                ->whereDate('rws.created_at', '>=', $from)
                ->whereDate('rws.created_at', '<=', $to)
                ->select(
                    DB::raw('COALESCE(sc.name, "Uncategorized") as sub_category'),
                    'it.name as item_name',
                    DB::raw('COALESCE(cat.name, "Uncategorized") as category_name'),
                    DB::raw('COALESCE(rwsi.unit, "Pcs") as unit'),
                    DB::raw('SUM(rwsi.qty) as qty'),
                    DB::raw('AVG(rwsi.price) as price'),
                    DB::raw('SUM(rwsi.subtotal) as subtotal')
                )
                ->groupBy(DB::raw('COALESCE(sc.name, "Uncategorized")'), 'it.name', DB::raw('COALESCE(cat.name, "Uncategorized")'), 'rwsi.unit')
                ->orderBy('sub_category')
                ->orderBy('it.name')
                ->get();

            // Prepare data for Excel
            $excelData = [];
            
            // Add header (matching FjDetailExport structure: Kategori, Item Name, Category, Unit, Qty Received, Price, Subtotal)
            $excelData[] = [
                'Kategori',
                'Item Name',
                'Category',
                'Unit',
                'Qty Received',
                'Price',
                'Subtotal'
            ];

            // Add retail data grouped by sub_category
            foreach ($retailData as $item) {
                $excelData[] = [
                    $item->sub_category ?: 'Uncategorized', // Kategori (sub_category)
                    $item->item_name, // Item Name
                    $item->category_name ?: 'Uncategorized', // Category (from categories table)
                    $item->unit ?: 'Pcs', // Unit
                    $item->qty, // Qty Received
                    $item->price, // Price
                    $item->subtotal // Subtotal
                ];
            }

            // Create Excel file
            $filename = 'Retail_Detail_' . str_replace([' ', '/'], '_', $customer) . '_' . $from . '_' . $to . '.xlsx';
            
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\FjDetailExport($excelData),
                $filename
            );

        } catch (\Exception $e) {
            Log::error('Retail Detail Excel error: ' . $e->getMessage());
            Log::error('Retail Detail Excel error trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Terjadi kesalahan saat generate Excel: ' . $e->getMessage()], 500);
        }
    }
}
