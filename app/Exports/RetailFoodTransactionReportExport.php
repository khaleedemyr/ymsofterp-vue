<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RetailFoodTransactionReportExport
{
    public function __construct(private $transactions, private array $filters)
    {
    }

    public function download()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report Retail Food');
        $sheet->setCellValue('A1', 'Report Retail Food');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $filterText = sprintf('Tanggal: %s - %s', $this->filters['date_from'] ?: 'Semua', $this->filters['date_to'] ?: 'Semua');
        $sheet->setCellValue('A2', $filterText);
        $sheet->mergeCells('A2:J2');

        $headers = ['No', 'Tanggal', 'Supplier', 'Outlet', 'Metode Pembayaran', 'No. Transaksi', 'Item', 'Qty', 'Harga', 'Subtotal', 'Total'];
        $headerRow = 4;
        foreach ($headers as $column => $header) {
            $sheet->setCellValueByColumnAndRow($column + 1, $headerRow, $header);
        }
        $sheet->getStyle('A4:K4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = $headerRow + 1;
        $number = 1;
        foreach ($this->transactions as $transaction) {
            $items = $transaction->items ?? [];
            foreach (count($items) ? $items : [null] as $item) {
                $sheet->fromArray([
                    $number,
                    $transaction->transaction_date,
                    $transaction->supplier_name,
                    $transaction->outlet_name,
                    $this->paymentMethodLabel($transaction->payment_method),
                    $transaction->retail_number,
                    $item->item_name ?? '-',
                    $item ? $item->qty . ' ' . $item->unit : '-',
                    $item ? $item->price : 0,
                    $item ? $item->subtotal : 0,
                    $transaction->total_amount,
                ], null, 'A' . $row);
                $row++;
            }
            $number++;
        }

        if ($row > $headerRow + 1) {
            $sheet->getStyle('A5:K' . ($row - 1))->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
            $sheet->getStyle('I5:K' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'report-retail-food-' . now()->format('YmdHis') . '.xlsx';
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'cash' => 'Cash',
            'contra_bon' => 'Contra Bon',
            default => $method ?: '-',
        };
    }
}