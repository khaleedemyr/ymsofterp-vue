<?php

namespace App\Exports;

use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Unit;
use App\Models\WarehouseDivision;
use App\Models\Region;
use App\Models\Outlet;
use App\Models\Modifier;
use App\Models\MenuType;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ItemsImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        // Ambil data modifier options join modifier
        $modifierOptions = DB::table('modifier_options')
            ->join('modifiers', 'modifier_options.modifier_id', '=', 'modifiers.id')
            ->select('modifiers.name as modifier_name', 'modifier_options.name as option_name')
            ->orderBy('modifiers.name')->orderBy('modifier_options.name')
            ->get()->map(function($row) {
                return [$row->modifier_name, $row->option_name];
            })->toArray();
        // Ambil data menu types
        $menuTypes = DB::table('menu_type')->select('type', 'status')->orderBy('type')->get()->map(function($row) {
            return [$row->type, $row->status];
        })->toArray();
        // Ambil data referensi items
        $itemsRef = \App\Models\Item::with(['category', 'subCategory', 'smallUnit'])
            ->orderBy('name', 'asc')
            ->get()
            ->map(function($item) {
                return [
                    $item->category->name ?? '',
                    $item->subCategory->name ?? '',
                    $item->sku,
                    $item->name,
                    $item->smallUnit->name ?? '',
                ];
            })->toArray();
        return [
            'Instructions' => new InstructionsSheet(),
            'Items' => new ItemsSheet(),
            'ItemsRef' => new MasterSheet($itemsRef, ['Category', 'Sub Category', 'SKU', 'Name', 'Small Unit'], 'ItemsRef'),
            'Categories' => new MasterSheet(Category::pluck('name')->toArray(), ['Category Name'], 'Categories'),
            'SubCategories' => new MasterSheet(SubCategory::pluck('name')->toArray(), ['Sub Category Name'], 'SubCategories'),
            'Units' => new MasterSheet(Unit::pluck('name')->toArray(), ['Unit Name'], 'Units'),
            'WarehouseDivisions' => new MasterSheet(WarehouseDivision::pluck('name')->toArray(), ['Warehouse Division Name'], 'WarehouseDivisions'),
            'MenuTypes' => new MasterSheet($menuTypes, ['Type', 'Status'], 'MenuTypes'),
            'Regions' => new MasterSheet(Region::pluck('name')->toArray(), ['Region Name'], 'Regions'),
            'Outlets' => new MasterSheet(Outlet::pluck('nama_outlet')->toArray(), ['Outlet Name'], 'Outlets'),
            'Modifiers' => new MasterSheet(Modifier::pluck('name')->toArray(), ['Modifier Name'], 'Modifiers'),
            'ModifierOptions' => new MasterSheet($modifierOptions, ['Modifier Name', 'Option Name'], 'ModifierOptions'),
        ];
    }
}

class InstructionsSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    public function array(): array
    {
        return [
            ['Name', 'Nama item. Wajib diisi. Contoh: Nasi Goreng'],
            ['Category', 'Pilih dari sheet Categories. Wajib diisi. Contoh: Food'],
            ['Sub Category', 'Pilih dari sheet SubCategories. Boleh dikosongkan. Contoh: Rice'],
            ['Warehouse Division', 'Pilih dari sheet WarehouseDivisions. Wajib diisi. Contoh: Gudang Utama'],
            ['Type', 'Pilih dari sheet MenuTypes kolom Type. Boleh dikosongkan. Contoh: Food'],
            ['Description', 'Deskripsi item. Boleh dikosongkan. Contoh: Nasi goreng dengan topping ayam.'],
            ['Specification', 'Spesifikasi item. Boleh dikosongkan. Contoh: Berat 250gr'],
            ['Small Unit', 'Pilih dari sheet Units. Wajib diisi. Contoh: Porsi'],
            ['Medium Unit', 'Pilih dari sheet Units. Wajib diisi. Contoh: Box'],
            ['Large Unit', 'Pilih dari sheet Units. Wajib diisi. Contoh: Dus'],
            ['Medium Conversion Qty', 'Angka. Wajib diisi. Contoh: 10'],
            ['Small Conversion Qty', 'Angka. Wajib diisi. Contoh: 2'],
            ['Min Stock', 'Angka. Wajib diisi. Contoh: 5'],
            ['Status', 'Pilih: active/inactive. Wajib diisi. Contoh: active'],
            ['Composition Type', 'Pilih: single/composed. Wajib diisi. Contoh: single'],
            ['Modifier Enabled', 'Pilih: Yes/No. Contoh: Yes'],
            ['Modifier Options', 'Nama option dari sheet ModifierOptions (kolom Option Name), dipisahkan koma jika lebih dari satu. Contoh: Keju,Coklat'],
            ['BOM', 'Format: Nama Item x Qty (Unit); jika lebih dari satu, pisahkan dengan titik koma (;). Contoh: Gula x 2 (Kg); Tepung x 1 (Sak)'],
            ['Prices', 'Format: Nama Region/Outlet=Harga; jika lebih dari satu, pisahkan dengan titik koma (;). Contoh: Jakarta=10000; Outlet A=20000'],
            ['Availabilities', 'Isi dengan nama Region/Outlet dari sheet Regions/Outlets, dipisahkan titik koma jika lebih dari satu. Untuk semua region/outlet, isi: all. Contoh: Jakarta; Outlet A; all'],
            ['Images', 'Link gambar, dipisahkan koma jika lebih dari satu. Contoh: https://img.com/a.jpg,https://img.com/b.jpg'],
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
        $sheet->getStyle('A1:B22')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
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

class ItemsSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    public function array(): array
    {
        return [
            [
                'Item A', 'Food', 'Sub1', 'Gudang 1', 'Food', 'Deskripsi', 'Spesifikasi', 'Kg', 'Sak', 'Box', 10, 2, 5, 'active', 'single', 'Yes', 'Keju,Coklat', 'Gula x 2 (Kg); Tepung x 1 (Sak)', 'Jakarta=10000; Outlet A=20000', 'Jakarta; Outlet A; all', 'https://img.com/a.jpg,https://img.com/b.jpg'
            ]
        ];
    }
    public function headings(): array
    {
        return [
            'Name', 'Category', 'Sub Category', 'Warehouse Division', 'Type', 'Description', 'Specification',
            'Small Unit', 'Medium Unit', 'Large Unit', 'Medium Conversion Qty', 'Small Conversion Qty', 'Min Stock', 'Status',
            'Composition Type', 'Modifier Enabled', 'Modifier Options', 'BOM', 'Prices', 'Availabilities', 'Images'
        ];
    }
    public function title(): string
    {
        return 'Items';
    }
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:T1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0']
            ],
        ]);
        $sheet->getStyle('A1:T2')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
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
        // Pastikan array of array
        if ($data && is_array($data) && is_string(reset($data))) {
            $data = array_map(function($v) { return [$v]; }, $data);
        }
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