<?php

namespace App\Exports;

use App\Models\Item;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BomImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        // Get composed items (parent items)
        $parentItems = Item::where('composition_type', 'composed')
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(function($item) {
                return [
                    $item->name,
                    $item->sku,
                    $item->category->name ?? '',
                    $item->subCategory->name ?? '',
                ];
            })->toArray();

        // Get raw materials and WIP items (child items)
        $childItems = Item::whereIn('type', ['Raw Materials', 'WIP', 'Finish Goods'])
            ->where('status', 'active')
            ->with('smallUnit')
            ->orderBy('name')
            ->get()
            ->map(function($item) {
                return [
                    $item->name,
                    $item->sku,
                    $item->smallUnit->name ?? '',
                ];
            })->toArray();

        return [
            'Instructions' => new InstructionsSheet(),
            'BOM' => new BomSheet(),
            'ItemParent' => new MasterSheet($parentItems, ['Name', 'SKU', 'Category', 'Sub Category'], 'ItemParent'),
            'ItemChild' => new MasterSheet($childItems, ['Name', 'SKU', 'Small Unit'], 'ItemChild'),
        ];
    }
}

class InstructionsSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    public function array(): array
    {
        return [
            ['Parent Item', 'Nama item parent (composed). Wajib diisi. Contoh: Nasi Goreng'],
            ['Child Item', 'Nama item child (raw material/WIP/Finish Goods). Wajib diisi. Contoh: Beras'],
            ['Quantity', 'Jumlah item child yang dibutuhkan. Wajib diisi. Contoh: 2'],
            ['Unit', 'Unit dari item child. Wajib diisi. Contoh: Kg'],
        ];
    }

    public function headings(): array
    {
        return ['Kolom', 'Keterangan & Contoh'];
    }

    public function title(): string
    {
        return 'Instructions';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0']
            ],
        ]);
        $sheet->getStyle('A1:B5')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A2');
            }
        ];
    }
}

class BomSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    public function array(): array
    {
        return [
            ['Nasi Goreng', 'Beras', 2, 'Kg'],
        ];
    }

    public function headings(): array
    {
        return [
            'Parent Item',
            'Child Item',
            'Quantity',
            'Unit'
        ];
    }

    public function title(): string
    {
        return 'BOM';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0']
            ],
        ]);
        $sheet->getStyle('A1:D2')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A2');
            }
        ];
    }
}

class MasterSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    protected $data;
    protected $headings;
    protected $title;

    public function __construct($data, $headings, $title)
    {
        $this->data = $data;
        $this->headings = $headings;
        $this->title = $title;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet)
    {
        $colCount = count($this->headings);
        $colLetter = chr(64 + $colCount);
        $sheet->getStyle('A1:'.$colLetter.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0']
            ],
        ]);
        $rowCount = count($this->data) + 1;
        $sheet->getStyle('A1:'.$colLetter.$rowCount)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A2');
            }
        ];
    }
} 