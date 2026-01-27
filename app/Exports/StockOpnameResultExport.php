<?php

namespace App\Exports;

use App\Models\StockOpname;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockOpnameResultExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected StockOpname $stockOpname;

    protected int $rowNo = 0;

    public function __construct(StockOpname $stockOpname)
    {
        $this->stockOpname = $stockOpname;
    }

    public function collection()
    {
        return $this->stockOpname->items;
    }

    public function headings(): array
    {
        return [
            'No',
            'Kategori',
            'Nama Item',
            'Qty System (S)',
            'Qty System (M)',
            'Qty System (L)',
            'Qty Physical (S)',
            'Qty Physical (M)',
            'Qty Physical (L)',
            'Selisih (S)',
            'Selisih (M)',
            'Selisih (L)',
            'MAC',
            'Value Adjustment',
            'Alasan',
        ];
    }

    public function map($row): array
    {
        $item = $row->inventoryItem?->item ?? null;
        $cat = $item?->category?->name ?? '';
        $name = $item?->name ?? '-';
        $uS = $item?->smallUnit?->name ?? '';
        $uM = $item?->mediumUnit?->name ?? '';
        $uL = $item?->largeUnit?->name ?? '';

        $qtySysS = $row->qty_system_small !== null ? (float) $row->qty_system_small : 0;
        $qtySysM = $row->qty_system_medium !== null ? (float) $row->qty_system_medium : 0;
        $qtySysL = $row->qty_system_large !== null ? (float) $row->qty_system_large : 0;
        $qtyPhyS = $row->qty_physical_small !== null ? (float) $row->qty_physical_small : 0;
        $qtyPhyM = $row->qty_physical_medium !== null ? (float) $row->qty_physical_medium : 0;
        $qtyPhyL = $row->qty_physical_large !== null ? (float) $row->qty_physical_large : 0;
        $diffS = $row->qty_diff_small !== null ? (float) $row->qty_diff_small : 0;
        $diffM = $row->qty_diff_medium !== null ? (float) $row->qty_diff_medium : 0;
        $diffL = $row->qty_diff_large !== null ? (float) $row->qty_diff_large : 0;

        $mac = $row->mac_before !== null ? (float) $row->mac_before : 0;
        $valAdj = $row->value_adjustment !== null ? (float) $row->value_adjustment : 0;

        $this->rowNo++;

        return [
            $this->rowNo,
            $cat,
            $name,
            $qtySysS . ($uS ? " {$uS}" : ''),
            $qtySysM . ($uM ? " {$uM}" : ''),
            $qtySysL . ($uL ? " {$uL}" : ''),
            $qtyPhyS . ($uS ? " {$uS}" : ''),
            $qtyPhyM . ($uM ? " {$uM}" : ''),
            $qtyPhyL . ($uL ? " {$uL}" : ''),
            $diffS,
            $diffM,
            $diffL,
            $mac,
            $valAdj,
            $row->reason ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:O1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0'],
            ],
        ]);
        $highest = $sheet->getHighestRow();
        $lastCol = 'O';
        if ($highest >= 1) {
            $sheet->getStyle('A1:' . $lastCol . $highest)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }
        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,  'B' => 18, 'C' => 30, 'D' => 14, 'E' => 14, 'F' => 14,
            'G' => 14, 'H' => 14, 'I' => 14, 'J' => 12, 'K' => 12, 'L' => 12,
            'M' => 12, 'N' => 16, 'O' => 25,
        ];
    }
}
