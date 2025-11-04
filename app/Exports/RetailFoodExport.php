<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RetailFoodExport
{
    private $data;
    private $filters;

    public function __construct($data, $filters = [])
    {
        $this->data = $data;
        $this->filters = $filters;
    }

    public function export()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $sheet->setCellValue('A1', 'Laporan Outlet Retail Food');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Set filter info
        $row = 2;
        if (!empty($this->filters)) {
            $filterText = 'Filter: ';
            $filters = [];
            if (isset($this->filters['date_from']) && isset($this->filters['date_to'])) {
                $filters[] = 'Tanggal: ' . $this->filters['date_from'] . ' - ' . $this->filters['date_to'];
            }
            if (isset($this->filters['search'])) {
                $filters[] = 'Pencarian: ' . $this->filters['search'];
            }
            if (isset($this->filters['payment_method'])) {
                $filters[] = 'Metode Pembayaran: ' . ($this->filters['payment_method'] === 'cash' ? 'Cash' : 'Contra Bon');
            }
            $filterText .= implode(', ', $filters);
            $sheet->setCellValue('A' . $row, $filterText);
            $sheet->mergeCells('A' . $row . ':J' . $row);
            $row++;
        }
        
        $row++; // Empty row
        
        // Header row
        $headers = [
            'No',
            'Tanggal',
            'No. Transaksi',
            'Outlet',
            'Warehouse Outlet',
            'Supplier',
            'Metode Pembayaran',
            'Status',
            'Total',
            'Item Name',
            'Qty',
            'Unit',
            'Harga',
            'Subtotal'
        ];
        
        $headerRow = $row;
        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, $headerRow, $header);
        }
        
        // Style headers
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '3B82F6'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('A' . $headerRow . ':N' . $headerRow)->applyFromArray($headerStyle);
        
        // Data rows
        $currentRow = $headerRow + 1;
        $no = 1;
        
        foreach ($this->data as $retailFood) {
            $firstItemRow = $currentRow;
            $items = $retailFood->items ?? collect();
            $itemCount = $items->count() > 0 ? $items->count() : 1;
            
            // Main data row
            $sheet->setCellValueByColumnAndRow(1, $currentRow, $no);
            $sheet->setCellValueByColumnAndRow(2, $currentRow, $this->formatDate($retailFood->transaction_date));
            $sheet->setCellValueByColumnAndRow(3, $currentRow, $retailFood->retail_number ?? '-');
            $sheet->setCellValueByColumnAndRow(4, $currentRow, $retailFood->outlet->nama_outlet ?? '-');
            $sheet->setCellValueByColumnAndRow(5, $currentRow, $retailFood->warehouse_outlet_name ?? '-');
            $sheet->setCellValueByColumnAndRow(6, $currentRow, $retailFood->supplier_name ?? '-');
            $sheet->setCellValueByColumnAndRow(7, $currentRow, $retailFood->payment_method === 'cash' ? 'Cash' : 'Contra Bon');
            $sheet->setCellValueByColumnAndRow(8, $currentRow, $retailFood->status === 'approved' ? 'Approved' : 'Draft');
            $sheet->setCellValueByColumnAndRow(9, $currentRow, $this->formatRupiah($retailFood->total_amount ?? 0));
            
            // Item details
            if ($items->count() > 0) {
                foreach ($items as $item) {
                    if ($currentRow > $firstItemRow) {
                        // Repeat header data for merged rows
                        $sheet->setCellValueByColumnAndRow(1, $currentRow, '');
                        $sheet->setCellValueByColumnAndRow(2, $currentRow, '');
                        $sheet->setCellValueByColumnAndRow(3, $currentRow, '');
                        $sheet->setCellValueByColumnAndRow(4, $currentRow, '');
                        $sheet->setCellValueByColumnAndRow(5, $currentRow, '');
                        $sheet->setCellValueByColumnAndRow(6, $currentRow, '');
                        $sheet->setCellValueByColumnAndRow(7, $currentRow, '');
                        $sheet->setCellValueByColumnAndRow(8, $currentRow, '');
                        $sheet->setCellValueByColumnAndRow(9, $currentRow, '');
                    }
                    
                    $sheet->setCellValueByColumnAndRow(10, $currentRow, $item->item_name ?? '-');
                    $sheet->setCellValueByColumnAndRow(11, $currentRow, $item->qty ?? 0);
                    $sheet->setCellValueByColumnAndRow(12, $currentRow, $item->unit ?? '-');
                    $sheet->setCellValueByColumnAndRow(13, $currentRow, $this->formatRupiah($item->price ?? 0));
                    $sheet->setCellValueByColumnAndRow(14, $currentRow, $this->formatRupiah($item->subtotal ?? 0));
                    
                    $currentRow++;
                }
            } else {
                // No items, just show empty item columns
                $sheet->setCellValueByColumnAndRow(10, $currentRow, '-');
                $sheet->setCellValueByColumnAndRow(11, $currentRow, '-');
                $sheet->setCellValueByColumnAndRow(12, $currentRow, '-');
                $sheet->setCellValueByColumnAndRow(13, $currentRow, '-');
                $sheet->setCellValueByColumnAndRow(14, $currentRow, '-');
                $currentRow++;
            }
            
            $no++;
        }
        
        // Apply borders to data
        $lastRow = $currentRow - 1;
        if ($lastRow >= $headerRow + 1) {
            $styleArray = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ];
            $sheet->getStyle('A' . ($headerRow + 1) . ':N' . $lastRow)->applyFromArray($styleArray);
        }
        
        // Auto size columns
        foreach (range('A', 'N') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Set alignment for numeric columns
        $sheet->getStyle('I' . ($headerRow + 1) . ':I' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('K' . ($headerRow + 1) . ':K' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('M' . ($headerRow + 1) . ':N' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        // Create writer
        $writer = new Xlsx($spreadsheet);
        
        // Set headers for download
        $filename = 'outlet-retail-food-' . date('Y-m-d') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Output file
        $writer->save('php://output');
        exit;
    }
    
    private function formatDate($date)
    {
        if (!$date) return '-';
        if (is_string($date)) {
            return date('d/m/Y', strtotime($date));
        }
        return $date->format('d/m/Y');
    }
    
    private function formatRupiah($value)
    {
        if ($value === null || $value === '') return '0';
        return number_format((float) $value, 0, ',', '.');
    }
}

