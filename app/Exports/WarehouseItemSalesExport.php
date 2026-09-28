<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class WarehouseItemSalesExport implements WithMultipleSheets
{
    protected array $items;
    protected array $monthly;
    protected array $summary;
    protected array $filters;

    public function __construct(array $items, array $monthly, array $summary, array $filters = [])
    {
        $this->items = $items;
        $this->monthly = $monthly;
        $this->summary = $summary;
        $this->filters = $filters;
    }

    public function sheets(): array
    {
        return [
            new WarehouseItemSalesItemsSheet($this->items, $this->summary, $this->filters),
            new WarehouseItemSalesMonthlySheet($this->monthly),
        ];
    }
}

class WarehouseItemSalesItemsSheet implements FromArray, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    protected array $items;
    protected array $summary;
    protected array $filters;

    public function __construct(array $items, array $summary, array $filters = [])
    {
        $this->items = $items;
        $this->summary = $summary;
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'Per Item';
    }

    public function headings(): array
    {
        return [
            'Item',
            'Unit',
            'Qty Total',
            'Nilai Total',
            'Qty Antar Gudang',
            'Nilai Antar Gudang',
            'Qty Retail',
            'Nilai Retail',
            'Qty Outlet GR',
            'Nilai Outlet GR',
        ];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->items as $item) {
            $rows[] = [
                $item['item_name'] ?? '',
                $item['unit_name'] ?? '-',
                $item['qty'] ?? 0,
                $item['value'] ?? 0,
                $item['antar_gudang_qty'] ?? 0,
                $item['antar_gudang_value'] ?? 0,
                $item['retail_qty'] ?? 0,
                $item['retail_value'] ?? 0,
                $item['outlet_gr_qty'] ?? 0,
                $item['outlet_gr_value'] ?? 0,
            ];
        }

        $rows[] = [];
        $rows[] = [
            'TOTAL',
            '',
            $this->summary['total_qty'] ?? 0,
            $this->summary['total_value'] ?? 0,
            $this->summary['antar_gudang_qty'] ?? 0,
            $this->summary['antar_gudang_value'] ?? 0,
            $this->summary['retail_qty'] ?? 0,
            $this->summary['retail_value'] ?? 0,
            $this->summary['outlet_gr_qty'] ?? 0,
            $this->summary['outlet_gr_value'] ?? 0,
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1D4ED8'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}

class WarehouseItemSalesMonthlySheet implements FromArray, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    protected array $monthly;

    public function __construct(array $monthly)
    {
        $this->monthly = $monthly;
    }

    public function title(): string
    {
        return 'Per Bulan';
    }

    public function headings(): array
    {
        return [
            'Bulan',
            'Qty Total',
            'Nilai Total',
            'Qty Antar Gudang',
            'Nilai Antar Gudang',
            'Qty Retail',
            'Nilai Retail',
            'Qty Outlet GR',
            'Nilai Outlet GR',
        ];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->monthly as $m) {
            $rows[] = [
                $m['month'] ?? '',
                $m['qty'] ?? 0,
                $m['value'] ?? 0,
                $m['antar_gudang_qty'] ?? 0,
                $m['antar_gudang_value'] ?? 0,
                $m['retail_qty'] ?? 0,
                $m['retail_value'] ?? 0,
                $m['outlet_gr_qty'] ?? 0,
                $m['outlet_gr_value'] ?? 0,
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '047857'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}
