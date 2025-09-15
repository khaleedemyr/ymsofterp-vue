<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Events\AfterSheet;

class BankPromoDiscountExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithCustomStartCell, WithEvents, Responsable
{
    private $transactions;
    private $grandTotalResult;
    private $dateFrom;
    private $dateTo;
    private $search;
    public $fileName;

    public function __construct($transactions, $grandTotalResult, $dateFrom, $dateTo, $search = '')
    {
        $this->transactions = $transactions;
        $this->grandTotalResult = $grandTotalResult;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->search = $search;
        $this->fileName = 'Bank_Promo_Discount_Transactions_' . $dateFrom . '_to_' . $dateTo . '.xlsx';
    }

    public function collection()
    {
        return collect($this->transactions);
    }

    public function headings(): array
    {
        return [
            'ID Order',
            'Paid Number',
            'Outlet',
            'Region',
            'Kasir',
            'Payment Method',
            'Grand Total',
            'Discount Amount',
            'Discount Reason',
            'Created Date'
        ];
    }

    public function map($transaction): array
    {
        return [
            $transaction->id,
            $transaction->paid_number ?? '',
            $transaction->outlet_name ?? '',
            $transaction->region_name ?? 'N/A',
            $transaction->kasir ?? '',
            $transaction->payment_method ?? '',
            $transaction->grand_total,
            $transaction->manual_discount_amount,
            $transaction->manual_discount_reason ?? '',
            $transaction->created_at
        ];
    }

    public function startCell(): string
    {
        return 'A11'; // Start data from row 11
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, // ID Order
            'B' => 15, // Paid Number
            'C' => 25, // Outlet
            'D' => 15, // Region
            'E' => 20, // Kasir
            'F' => 30, // Payment Method
            'G' => 15, // Grand Total
            'H' => 15, // Discount Amount
            'I' => 30, // Discount Reason
            'J' => 20, // Created Date
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header information (rows 1-9)
        $sheet->setCellValue('A1', 'Bank Promo Discount Transactions Report');
        $sheet->setCellValue('A2', 'Date Range: ' . $this->dateFrom . ' to ' . $this->dateTo);
        if (!empty($this->search)) {
            $sheet->setCellValue('A3', 'Search Filter: ' . $this->search);
        }
        $sheet->setCellValue('A4', 'Generated: ' . now()->format('Y-m-d H:i:s'));
        
        // Summary
        $sheet->setCellValue('A6', 'Summary:');
        $sheet->setCellValue('A7', 'Total Transactions: ' . $this->grandTotalResult->total_transactions);
        $sheet->setCellValue('A8', 'Total Grand Total: Rp ' . number_format($this->grandTotalResult->total_grand_total, 0, ',', '.'));
        $sheet->setCellValue('A9', 'Total Discount Amount: Rp ' . number_format($this->grandTotalResult->total_discount_amount, 0, ',', '.'));

        // Style header information
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A6')->getFont()->setBold(true);
        $sheet->getStyle('A7:A9')->getFont()->setBold(true);

        return [
            // Style the header row (row 11)
            11 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE0E0E0'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Get the last row with data
                $lastRow = $sheet->getHighestRow();
                
                // Add borders to data range
                $dataRange = 'A11:J' . $lastRow;
                $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                
                // Format currency columns (G and H)
                $sheet->getStyle('G12:G' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('H12:H' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
                
                // Auto-size columns
                foreach (range('A', 'J') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            },
        ];
    }

    public function toResponse($request)
    {
        try {
            return Excel::download($this, $this->fileName);
        } catch (\Exception $e) {
            \Log::error('Export Bank Promo Discount toResponse error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat generate file Excel'], 500);
        }
    }
}
