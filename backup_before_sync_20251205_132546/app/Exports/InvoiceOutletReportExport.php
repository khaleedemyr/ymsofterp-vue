<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\DB;

class InvoiceOutletReportExport
{
    private $data;
    private $details;
    private $filters;

    public function __construct($data, $details, $filters = [])
    {
        $this->data = $data;
        $this->details = $details;
        $this->filters = $filters;
    }

    public function export()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $sheet->setCellValue('A1', 'Laporan Invoice Outlet');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Set filter info
        $row = 2;
        if (!empty($this->filters)) {
            $filterText = 'Filter: ';
            $filters = [];
            if (isset($this->filters['from']) && isset($this->filters['to'])) {
                $filters[] = 'Tanggal: ' . $this->filters['from'] . ' - ' . $this->filters['to'];
            }
            if (isset($this->filters['outlet_id'])) {
                $filters[] = 'Outlet ID: ' . $this->filters['outlet_id'];
            }
            if (isset($this->filters['transaction_type'])) {
                $filters[] = 'Tipe: ' . $this->filters['transaction_type'];
            }
            if (isset($this->filters['fo_mode'])) {
                $filters[] = 'RO Mode: ' . $this->filters['fo_mode'];
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
            'Tgl Invoice',
            'Outlet',
            'Warehouse',
            'Tipe',
            'RO Mode',
            'No RO',
            'No GR/RWS',
            'Tgl GR/RWS',
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
        $sheet->getStyle('A' . $headerRow . ':O' . $headerRow)->applyFromArray($headerStyle);
        
        // Data rows
        $currentRow = $headerRow + 1;
        $no = 1;
        
        foreach ($this->data as $index => $rowData) {
            $firstItemRow = $currentRow;
            $hasItems = isset($this->details[$rowData->gr_id]) && count($this->details[$rowData->gr_id]) > 0;
            $itemCount = $hasItems ? count($this->details[$rowData->gr_id]) : 1;
            
            // Main data row
            $sheet->setCellValueByColumnAndRow(1, $currentRow, $no);
            $sheet->setCellValueByColumnAndRow(2, $currentRow, $this->formatDate($rowData->invoice_date));
            $sheet->setCellValueByColumnAndRow(3, $currentRow, $rowData->outlet_name ?? '-');
            $sheet->setCellValueByColumnAndRow(4, $currentRow, $this->formatWarehouse($rowData));
            $sheet->setCellValueByColumnAndRow(5, $currentRow, $rowData->transaction_type ?? '-');
            $sheet->setCellValueByColumnAndRow(6, $currentRow, $rowData->fo_mode ?? '-');
            $sheet->setCellValueByColumnAndRow(7, $currentRow, $rowData->ro_number ?? '-');
            $sheet->setCellValueByColumnAndRow(8, $currentRow, $rowData->gr_number ?? '-');
            $sheet->setCellValueByColumnAndRow(9, $currentRow, $this->formatDate($rowData->gr_receive_date));
            $sheet->setCellValueByColumnAndRow(10, $currentRow, $this->formatRupiah($rowData->payment_total ?? 0));
            
            // Item details
            if ($hasItems) {
                foreach ($this->details[$rowData->gr_id] as $item) {
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
                        $sheet->setCellValueByColumnAndRow(10, $currentRow, '');
                    }
                    
                    $sheet->setCellValueByColumnAndRow(11, $currentRow, $item->item_name ?? '-');
                    $sheet->setCellValueByColumnAndRow(12, $currentRow, $item->qty ?? 0);
                    $sheet->setCellValueByColumnAndRow(13, $currentRow, $item->unit_name ?? '-');
                    $sheet->setCellValueByColumnAndRow(14, $currentRow, $this->formatRupiah($item->price ?? 0));
                    $sheet->setCellValueByColumnAndRow(15, $currentRow, $this->formatRupiah($item->subtotal ?? 0));
                    
                    $currentRow++;
                }
            } else {
                // No items, just show empty item columns
                $sheet->setCellValueByColumnAndRow(11, $currentRow, '-');
                $sheet->setCellValueByColumnAndRow(12, $currentRow, '-');
                $sheet->setCellValueByColumnAndRow(13, $currentRow, '-');
                $sheet->setCellValueByColumnAndRow(14, $currentRow, '-');
                $sheet->setCellValueByColumnAndRow(15, $currentRow, '-');
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
            $sheet->getStyle('A' . ($headerRow + 1) . ':O' . $lastRow)->applyFromArray($styleArray);
        }
        
        // Auto size columns
        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Set alignment for numeric columns
        $sheet->getStyle('J' . ($headerRow + 1) . ':J' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('L' . ($headerRow + 1) . ':L' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('N' . ($headerRow + 1) . ':O' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        // Create writer
        $writer = new Xlsx($spreadsheet);
        
        // Set headers for download
        $filename = 'laporan-invoice-outlet-' . date('Y-m-d') . '.xlsx';
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
        return date('d/m/Y', strtotime($date));
    }
    
    private function formatRupiah($value)
    {
        if ($value === null || $value === '') return '0';
        return number_format((float) $value, 0, ',', '.');
    }
    
    private function formatWarehouse($row)
    {
        if ($row->warehouse_name && $row->warehouse_division_name) {
            return $row->warehouse_name . ' - ' . $row->warehouse_division_name;
        }
        if ($row->warehouse_name) return $row->warehouse_name;
        if ($row->warehouse_division_name) return $row->warehouse_division_name;
        return '-';
    }
}

