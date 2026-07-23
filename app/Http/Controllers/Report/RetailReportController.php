<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Traits\ReportHelperTrait;
use Illuminate\Http\Request;
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
     * Grouped by sub-category (includes serial RWS items)
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

        $items = $this->rekapFjFetchRetailWarehouseDetailItems(
            $request->customer,
            $request->from,
            $request->to
        );

        // Group by sub_category
        $grouped = [];
        foreach ($items as $item) {
            $subCat = $item->sub_category ?: 'Uncategorized';
            if (!isset($grouped[$subCat])) {
                $grouped[$subCat] = [];
            }
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

            $retailData = $this->rekapFjFetchRetailWarehouseDetailItems($customer, $from, $to);

            // Group by category
            $groupedData = [];
            foreach ($retailData as $item) {
                $category = $item->category ?: 'Uncategorized';
                if (!isset($groupedData[$category])) {
                    $groupedData[$category] = [];
                }
                $groupedData[$category][] = $item;
            }

            $totalAmount = $retailData->sum('subtotal');

            $pdf = \PDF::loadView('reports.retail-detail-pdf', [
                'customer' => $customer,
                'from' => $from,
                'to' => $to,
                'detailData' => $groupedData,
                'totalAmount' => $totalAmount,
            ]);

            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('margin-top', 10);
            $pdf->setOption('margin-bottom', 10);
            $pdf->setOption('margin-left', 10);
            $pdf->setOption('margin-right', 10);
            $pdf->setOption('dpi', 96);

            $cleanCustomer = preg_replace('/[^a-zA-Z0-9\s\-_]/', '_', $customer);
            $cleanCustomer = trim($cleanCustomer);
            $cleanCustomer = preg_replace('/\s+/', '_', $cleanCustomer);
            $filename = "Retail_Detail_{$cleanCustomer}_{$from}_{$to}.pdf";

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Retail Detail PDF Error: ' . $e->getMessage(), [
                'customer' => $request->customer ?? 'unknown',
                'from' => $request->from ?? 'unknown',
                'to' => $request->to ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Gagal generate PDF: ' . $e->getMessage(),
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

            $retailData = $this->rekapFjFetchRetailWarehouseDetailItems($customer, $from, $to)
                ->groupBy(function ($item) {
                    return implode('|', [
                        $item->sub_category ?: 'Uncategorized',
                        $item->item_name,
                        $item->category ?: 'Uncategorized',
                        $item->unit ?: 'Pcs',
                    ]);
                })
                ->map(function ($group) {
                    $first = $group->first();
                    $qty = (float) $group->sum('qty');
                    $subtotal = (float) $group->sum('subtotal');

                    return (object) [
                        'sub_category' => $first->sub_category ?: 'Uncategorized',
                        'item_name' => $first->item_name,
                        'category_name' => $first->category ?: 'Uncategorized',
                        'unit' => $first->unit ?: 'Pcs',
                        'qty' => $qty,
                        'price' => $qty > 0 ? ($subtotal / $qty) : (float) $first->price,
                        'subtotal' => $subtotal,
                    ];
                })
                ->sortBy([
                    ['sub_category', 'asc'],
                    ['item_name', 'asc'],
                ])
                ->values();

            $excelData = [];
            $excelData[] = [
                'Kategori',
                'Item Name',
                'Category',
                'Unit',
                'Qty Received',
                'Price',
                'Subtotal',
            ];

            foreach ($retailData as $item) {
                $excelData[] = [
                    $item->sub_category,
                    $item->item_name,
                    $item->category_name,
                    $item->unit,
                    $item->qty,
                    $item->price,
                    $item->subtotal,
                ];
            }

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
