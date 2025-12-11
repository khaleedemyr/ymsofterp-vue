<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RetailFoodSupplierReportExport
{
    private $suppliers;
    private $filters;

    public function __construct($suppliers, $filters = [])
    {
        $this->suppliers = $suppliers;
        $this->filters = $filters;
    }

    public function export()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $sheet->setCellValue('A1', 'Laporan Retail Food per Supplier');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Set filter info
        $row = 2;
        if (!empty($this->filters)) {
            $filterText = 'Filter: ';
            $filters = [];
            if (isset($this->filters['date_from']) && isset($this->filters['date_to'])) {
                $filters[] = 'Tanggal: ' . $this->formatDate($this->filters['date_from']) . ' - ' . $this->formatDate($this->filters['date_to']);
            }
            if (isset($this->filters['search']) && !empty($this->filters['search'])) {
                $filters[] = 'Pencarian: ' . $this->filters['search'];
            }
            if (!empty($filters)) {
                $filterText .= implode(' | ', $filters);
                $sheet->setCellValue('A' . $row, $filterText);
                $sheet->mergeCells('A' . $row . ':J' . $row);
                $row++;
            }
        }
        
        $row++; // Empty row
        
        // Header row
        $headers = [
            'No',
            'Supplier',
            'Kode Supplier',
            'Outlet',
            'No. Transaksi',
            'Tanggal Transaksi',
            'Item',
            'Qty',
            'Unit',
            'Harga',
            'Subtotal',
            'Total Transaksi',
            'Catatan'
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
                'size' => 11,
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
                    'color' => ['rgb' => 'FFFFFF'],
                ],
            ],
        ];
        $sheet->getStyle('A' . $headerRow . ':M' . $headerRow)->applyFromArray($headerStyle);
        $sheet->getRowDimension($headerRow)->setRowHeight(25);
        
        // Data rows
        $currentRow = $headerRow + 1;
        $no = 1;
        
        foreach ($this->suppliers as $supplier) {
            foreach ($supplier['outlets'] as $outlet) {
                foreach ($outlet['transactions'] as $transaction) {
                    $firstItemRow = $currentRow;
                    $items = $transaction['items'] ?? [];
                    $itemCount = count($items) > 0 ? count($items) : 1;
                    
                    // If no items, still show one row with transaction info
                    if (empty($items)) {
                        $sheet->setCellValueByColumnAndRow(1, $currentRow, $no);
                        $sheet->setCellValueByColumnAndRow(2, $currentRow, $supplier['name']);
                        $sheet->setCellValueByColumnAndRow(3, $currentRow, $supplier['code'] ?? '-');
                        $sheet->setCellValueByColumnAndRow(4, $currentRow, $outlet['name']);
                        $sheet->setCellValueByColumnAndRow(5, $currentRow, $transaction['retail_number']);
                        $sheet->setCellValueByColumnAndRow(6, $currentRow, $this->formatDate($transaction['transaction_date']));
                        $sheet->setCellValueByColumnAndRow(7, $currentRow, '-');
                        $sheet->setCellValueByColumnAndRow(8, $currentRow, '-');
                        $sheet->setCellValueByColumnAndRow(9, $currentRow, '-');
                        $sheet->setCellValueByColumnAndRow(10, $currentRow, '-');
                        $sheet->setCellValueByColumnAndRow(11, $currentRow, '-');
                        $sheet->setCellValueByColumnAndRow(12, $currentRow, $this->formatRupiah($transaction['total_amount']));
                        $sheet->setCellValueByColumnAndRow(13, $currentRow, $transaction['notes'] ?? '-');
                        $currentRow++;
                        $no++;
                    } else {
                        // Show each item as a separate row
                        foreach ($items as $item) {
                            if ($currentRow > $firstItemRow) {
                                // Repeat transaction data for subsequent items
                                $sheet->setCellValueByColumnAndRow(1, $currentRow, '');
                                $sheet->setCellValueByColumnAndRow(2, $currentRow, '');
                                $sheet->setCellValueByColumnAndRow(3, $currentRow, '');
                                $sheet->setCellValueByColumnAndRow(4, $currentRow, '');
                                $sheet->setCellValueByColumnAndRow(5, $currentRow, '');
                                $sheet->setCellValueByColumnAndRow(6, $currentRow, '');
                                $sheet->setCellValueByColumnAndRow(12, $currentRow, '');
                                $sheet->setCellValueByColumnAndRow(13, $currentRow, '');
                            } else {
                                // First row of transaction
                                $sheet->setCellValueByColumnAndRow(1, $currentRow, $no);
                                $sheet->setCellValueByColumnAndRow(2, $currentRow, $supplier['name']);
                                $sheet->setCellValueByColumnAndRow(3, $currentRow, $supplier['code'] ?? '-');
                                $sheet->setCellValueByColumnAndRow(4, $currentRow, $outlet['name']);
                                $sheet->setCellValueByColumnAndRow(5, $currentRow, $transaction['retail_number']);
                                $sheet->setCellValueByColumnAndRow(6, $currentRow, $this->formatDate($transaction['transaction_date']));
                                $sheet->setCellValueByColumnAndRow(12, $currentRow, $this->formatRupiah($transaction['total_amount']));
                                $sheet->setCellValueByColumnAndRow(13, $currentRow, $transaction['notes'] ?? '-');
                            }
                            
                            // Item details
                            $sheet->setCellValueByColumnAndRow(7, $currentRow, $item['item_name'] ?? '-');
                            $sheet->setCellValueByColumnAndRow(8, $currentRow, $this->formatNumber($item['qty'] ?? 0));
                            $sheet->setCellValueByColumnAndRow(9, $currentRow, $item['unit'] ?? '-');
                            $sheet->setCellValueByColumnAndRow(10, $currentRow, $this->formatRupiah($item['price'] ?? 0));
                            $sheet->setCellValueByColumnAndRow(11, $currentRow, $this->formatRupiah($item['subtotal'] ?? 0));
                            
                            $currentRow++;
                        }
                        $no++;
                    }
                }
            }
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
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                ],
            ];
            $sheet->getStyle('A' . ($headerRow + 1) . ':M' . $lastRow)->applyFromArray($styleArray);
        }
        
        // Auto size columns
        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Set column widths for better readability
        $sheet->getColumnDimension('A')->setWidth(6);  // No
        $sheet->getColumnDimension('B')->setWidth(25); // Supplier
        $sheet->getColumnDimension('C')->setWidth(15); // Kode Supplier
        $sheet->getColumnDimension('D')->setWidth(20); // Outlet
        $sheet->getColumnDimension('E')->setWidth(20); // No. Transaksi
        $sheet->getColumnDimension('F')->setWidth(18); // Tanggal
        $sheet->getColumnDimension('G')->setWidth(30); // Item
        $sheet->getColumnDimension('H')->setWidth(12); // Qty
        $sheet->getColumnDimension('I')->setWidth(10); // Unit
        $sheet->getColumnDimension('J')->setWidth(15); // Harga
        $sheet->getColumnDimension('K')->setWidth(15); // Subtotal
        $sheet->getColumnDimension('L')->setWidth(18); // Total Transaksi
        $sheet->getColumnDimension('M')->setWidth(30); // Catatan
        
        // Set alignment for numeric columns
        $sheet->getStyle('H' . ($headerRow + 1) . ':H' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('J' . ($headerRow + 1) . ':L' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        // Wrap text for long content
        $sheet->getStyle('B' . ($headerRow + 1) . ':M' . $lastRow)->getAlignment()->setWrapText(true);
        
        // Freeze first row (header)
        $sheet->freezePane('A' . ($headerRow + 1));
        
        // Create writer
        $writer = new Xlsx($spreadsheet);
        
        // Set headers for download
        $dateRange = '';
        if (isset($this->filters['date_from']) && isset($this->filters['date_to'])) {
            $dateRange = '-' . str_replace('-', '', $this->filters['date_from']) . '-' . str_replace('-', '', $this->filters['date_to']);
        }
        $filename = 'report-retail-food-supplier' . $dateRange . '-' . date('YmdHis') . '.xlsx';
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
    
    private function formatNumber($value)
    {
        if ($value === null || $value === '') return '0';
        return number_format((float) $value, 2, ',', '.');
    }
}

