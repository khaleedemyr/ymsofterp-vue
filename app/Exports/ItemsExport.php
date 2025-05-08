<?php

namespace App\Exports;

use App\Models\Item;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ItemsExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithMapping
{
    public function collection()
    {
        return Item::with([
            'category', 'subCategory', 'smallUnit', 'mediumUnit', 'largeUnit', 'images',
            'prices.region', 'prices.outlet',
            'availabilities.region', 'availabilities.outlet',
            'boms.materialItem', 'boms.unit', 'modifierOptions'
        ])->get();
    }

    public function headings(): array
    {
        return [
            'Name', 'SKU', 'Category', 'Sub Category', 'Warehouse Division', 'Type', 'Description', 'Specification',
            'Small Unit', 'Medium Unit', 'Large Unit', 'Medium Conversion Qty', 'Small Conversion Qty', 'Min Stock', 'Status',
            'Composition Type', 'Modifier Enabled', 'Modifier Options', 'BOM', 'Prices', 'Availabilities', 'Images'
        ];
    }

    public function map($item): array
    {
        return [
            $item->name,
            $item->sku,
            $item->category->name ?? '',
            $item->subCategory->name ?? '',
            $item->warehouseDivision->name ?? '',
            $item->type,
            $item->description,
            $item->specification,
            $item->smallUnit->name ?? '',
            $item->mediumUnit->name ?? '',
            $item->largeUnit->name ?? '',
            $item->medium_conversion_qty,
            $item->small_conversion_qty,
            $item->min_stock,
            $item->status,
            $item->composition_type,
            $item->modifier_enabled ? 'Yes' : 'No',
            $item->modifierOptions->pluck('name')->implode(', '),
            // BOM
            $item->boms->map(function($b) {
                $materialName = $b->materialItem->name ?? $b->material_item_id;
                $unitName = $b->unit->name ?? $b->unit_id;
                $qty = rtrim(rtrim(number_format($b->qty, 2, '.', ''), '0'), '.');
                return "{$materialName} x {$qty} ({$unitName})";
            })->implode('; '),
            // Prices
            $item->prices->map(function($p) {
                $region = $p->region->name ?? '';
                $outlet = $p->outlet->nama_outlet ?? '';
                $label = $region ?: $outlet ?: '-';
                return "{$label} = {$p->price}";
            })->implode('; '),
            // Availabilities
            $item->availabilities->map(function($a) {
                $region = $a->region->name ?? '';
                $outlet = $a->outlet->nama_outlet ?? '';
                $label = $region ?: $outlet ?: '-';
                return "{$label} = " . ($a->availability_type ?? '');
            })->implode('; '),
            // Images
            $item->images->map(function($img) {
                return url('/storage/' . $img->path);
            })->implode(', ')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header style + border
        $sheet->getStyle('A1:U1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);
        // All cells border
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $highestCol . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        return [];
    }

    public function columnWidths(): array
    {
        // Set auto width for all columns
        $widths = [];
        foreach (range('A', 'U') as $col) {
            $widths[$col] = 25;
        }
        return $widths;
    }
} 