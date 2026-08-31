<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Traits\ReportHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductSalesPivotReportController extends Controller
{
    use ReportHelperTrait;

    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdminOutlet = (int) ($user->id_outlet ?? 0) === 1;

        return Inertia::render('Report/ProductSalesPivot', [
            'canSelectOutlet' => $isAdminOutlet,
            'defaultDateFrom' => now()->startOfMonth()->toDateString(),
            'defaultDateTo' => now()->toDateString(),
        ]);
    }

    public function productOptions(Request $request)
    {
        $search = trim((string) $request->input('q', ''));

        $query = DB::table('items as i')
            ->join('categories as c', 'c.id', '=', 'i.category_id')
            ->where('c.show_pos', '1')
            ->where('c.is_asset', '0');

        if ($search !== '') {
            $query->where('i.name', 'like', '%' . $search . '%');
        }

        $products = $query
            ->select([
                'i.name as name',
                'i.id as item_id',
            ])
            ->orderBy('i.name')
            ->limit($search === '' ? 500 : 100)
            ->get();

        return response()->json(['products' => $products]);
    }

    public function report(Request $request)
    {
        $payload = $this->buildReportPayload($request);

        return response()->json($payload);
    }

    public function export(Request $request): StreamedResponse
    {
        $payload = $this->buildReportPayload($request);
        $outlets = $payload['outlets'];
        $rows = $payload['rows'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Product Sales Pivot');

        $sheet->setCellValue('A1', 'Product');
        $sheet->setCellValue('B1', 'Price');
        $colIndex = 3;
        foreach ($outlets as $outlet) {
            $colLetterStart = $this->columnLetter($colIndex);
            $colLetterEnd = $this->columnLetter($colIndex + 1);
            $sheet->mergeCells("{$colLetterStart}1:{$colLetterEnd}1");
            $sheet->setCellValue("{$colLetterStart}1", $outlet['name']);
            $sheet->setCellValue("{$colLetterStart}2", 'Qty Sld');
            $sheet->setCellValue("{$colLetterEnd}2", 'Revenue');
            $colIndex += 2;
        }
        $totalQtyCol = $this->columnLetter($colIndex);
        $totalRevCol = $this->columnLetter($colIndex + 1);
        $sheet->setCellValue("{$totalQtyCol}1", 'Total');
        $sheet->setCellValue("{$totalQtyCol}2", 'Qty Sld');
        $sheet->setCellValue("{$totalRevCol}2", 'Revenue');

        $rowNum = 3;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$rowNum}", $row['product_name']);
            $sheet->setCellValue("B{$rowNum}", $row['price']);
            $colIndex = 3;
            foreach ($outlets as $outlet) {
                $cell = $row['outlets'][$outlet['id']] ?? ['qty' => 0, 'revenue' => 0];
                $sheet->setCellValue($this->columnLetter($colIndex) . $rowNum, $cell['qty']);
                $sheet->setCellValue($this->columnLetter($colIndex + 1) . $rowNum, $cell['revenue']);
                $colIndex += 2;
            }
            $sheet->setCellValue("{$totalQtyCol}{$rowNum}", $row['total_qty']);
            $sheet->setCellValue("{$totalRevCol}{$rowNum}", $row['total_revenue']);
            $rowNum++;
        }

        $sheet->getStyle('A1:' . $totalRevCol . '2')->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $totalRevCol . '2')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1E293B');
        $sheet->getStyle('A1:' . $totalRevCol . '2')->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:' . $totalRevCol . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $fileName = 'product_sales_pivot_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName);
    }

    private function buildReportPayload(Request $request): array
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $outletIds = $this->normalizeIdArray($request->input('outlet_ids', []));
        $productNames = $this->normalizeStringArray($request->input('product_names', []));

        $availableOutlets = $this->getScopedOutlets($outletIds);
        $selectedOutletIds = $availableOutlets->pluck('id_outlet')->map(fn ($id) => (int) $id)->all();

        $query = DB::table('order_items as oi')
            ->join('orders as ord', 'oi.order_id', '=', 'ord.id')
            ->join('items as it', 'oi.item_id', '=', 'it.id')
            ->join('categories as cat', 'it.category_id', '=', 'cat.id')
            ->join('tbl_data_outlet as o', 'ord.kode_outlet', '=', 'o.qr_code')
            ->where('cat.show_pos', '1')
            ->where('cat.is_asset', '0')
            ->where('o.status', 'A')
            ->where('ord.status', '!=', 'cancelled')
            ->where('ord.grand_total', '>', 0)
            ->whereNotNull('oi.item_name')
            ->where('oi.item_name', '!=', '');

        if (!empty($selectedOutletIds)) {
            $query->whereIn('o.id_outlet', $selectedOutletIds);
        }

        if (!empty($productNames)) {
            $query->whereIn('oi.item_name', $productNames);
        }

        $this->applyDateScope($query, $dateFrom, $dateTo, 'ord.created_at');

        $rawRows = $query
            ->select([
                'oi.item_id',
                'oi.item_name',
                'o.id_outlet',
                'o.nama_outlet',
                DB::raw('SUM(oi.qty) as total_qty'),
                DB::raw('SUM(oi.qty * oi.price) as total_revenue'),
                DB::raw('MAX(oi.price) as unit_price'),
            ])
            ->groupBy('oi.item_id', 'oi.item_name', 'o.id_outlet', 'o.nama_outlet')
            ->orderBy('oi.item_name')
            ->get();

        $outlets = $availableOutlets
            ->map(fn ($outlet) => [
                'id' => (int) $outlet->id_outlet,
                'name' => (string) $outlet->nama_outlet,
            ])
            ->values()
            ->all();

        $rowsMap = [];
        $outletTotals = [];
        foreach ($outlets as $outlet) {
            $outletTotals[$outlet['id']] = ['qty' => 0, 'revenue' => 0];
        }

        foreach ($rawRows as $raw) {
            $productKey = (string) $raw->item_name;
            $outletId = (int) $raw->id_outlet;

            if (!isset($rowsMap[$productKey])) {
                $rowsMap[$productKey] = [
                    'item_id' => $raw->item_id,
                    'product_name' => $productKey,
                    'price' => (float) $raw->unit_price,
                    'outlets' => [],
                    'total_qty' => 0,
                    'total_revenue' => 0,
                ];
            }

            $qty = (float) $raw->total_qty;
            $revenue = (float) $raw->total_revenue;

            $rowsMap[$productKey]['outlets'][$outletId] = [
                'qty' => $qty,
                'revenue' => $revenue,
            ];
            $rowsMap[$productKey]['total_qty'] += $qty;
            $rowsMap[$productKey]['total_revenue'] += $revenue;

            if (isset($outletTotals[$outletId])) {
                $outletTotals[$outletId]['qty'] += $qty;
                $outletTotals[$outletId]['revenue'] += $revenue;
            }
        }

        $rows = array_values($rowsMap);
        $grandTotalQty = array_sum(array_column($rows, 'total_qty'));
        $grandTotalRevenue = array_sum(array_column($rows, 'total_revenue'));

        return [
            'outlets' => $outlets,
            'rows' => $rows,
            'outlet_totals' => $outletTotals,
            'grand_total_qty' => $grandTotalQty,
            'grand_total_revenue' => $grandTotalRevenue,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'outlet_ids' => $selectedOutletIds,
                'product_names' => $productNames,
            ],
        ];
    }

    private function getScopedOutlets(array $outletIds)
    {
        $user = auth()->user();
        $query = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->where('is_outlet', 1)
            ->orderBy('nama_outlet');

        if ((int) ($user->id_outlet ?? 0) !== 1) {
            $query->where('id_outlet', (int) $user->id_outlet);
        } elseif (!empty($outletIds)) {
            $query->whereIn('id_outlet', $outletIds);
        }

        return $query->get(['id_outlet', 'nama_outlet', 'qr_code']);
    }

    private function applyDateScope($query, ?string $dateFrom, ?string $dateTo, string $column): void
    {
        if ($dateFrom) {
            $query->whereDate($column, '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate($column, '<=', $dateTo);
        }
    }

    private function normalizeIdArray(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = array_filter(array_map('trim', explode(',', $value)));
            }
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $value))));
    }

    private function normalizeStringArray(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $value)));
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $value
        ))));
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
