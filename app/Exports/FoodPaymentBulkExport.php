<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FoodPaymentBulkExport implements FromArray, WithStyles, WithColumnWidths
{
    /** @var array<int, array{date_label: string, items: array<int, array<string, mixed>>}> */
    protected array $groups;

    /** @var array<int, int> */
    protected array $groupHeaderRows = [];

    public function __construct(array $groups)
    {
        $this->groups = $groups;
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = [
            'Supplier',
            'Nominal',
            'Admin',
            'Deskripsi',
            'Validasi',
            'No Rekening Supplier',
            'Bank Supplier',
            'Atas Nama Rekening Supplier',
        ];

        $rowIndex = 2;
        foreach ($this->groups as $group) {
            $this->groupHeaderRows[] = $rowIndex;
            $rows[] = [
                'Tanggal: '.$group['date_label'],
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ];
            $rowIndex++;

            foreach ($group['items'] as $item) {
                $rows[] = [
                    $item['supplier_name'],
                    $item['nominal'],
                    '',
                    $item['description'],
                    '',
                    $item['bank_account_number'],
                    $item['bank_name'],
                    $item['bank_account_name'],
                ];
                $rowIndex++;
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach ($this->groupHeaderRows as $row) {
            $sheet->mergeCells("A{$row}:H{$row}");
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DBEAFE'],
                ],
            ]);
        }

        $sheet->getStyle('B:B')->getNumberFormat()->setFormatCode('#,##0');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 16,
            'C' => 12,
            'D' => 28,
            'E' => 12,
            'F' => 22,
            'G' => 20,
            'H' => 28,
        ];
    }
}
