<?php

namespace App\Exports;

use App\Models\Item;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutletStockBalanceImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        // Get active items that are not shown in POS
        $items = Item::with(['category', 'smallUnit'])
            ->where('status', 'active')
            ->whereHas('category', function($query) {
                $query->where('show_pos', '0');
            })
            ->orderBy('name')
            ->get()
            ->map(function($item) {
                return [
                    $item->sku,
                    $item->name,
                    $item->smallUnit->name ?? '',
                ];
            })->toArray();

        // Get active outlets
        $outlets = Outlet::where('status', 'A')
            ->pluck('nama_outlet')
            ->toArray();

        return [
            'Instructions' => new OutletInstructionsSheet(),
            'StockBalance' => new OutletStockBalanceSheet(),
            'Items' => new OutletMasterSheet($items, ['SKU', 'Name', 'Small Unit'], 'Items'),
            'Outlets' => new OutletMasterSheet($outlets, ['Outlet Name'], 'Outlets'),
        ];
    }
}

class OutletInstructionsSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    public function array(): array
    {
        return [
            ['SKU', 'Kode item. Wajib diisi. Contoh: ITM001'],
            ['Name', 'Nama item. Wajib diisi. Contoh: Nasi Goreng'],
            ['Small Unit', 'Unit terkecil item. Wajib diisi. Contoh: Porsi'],
            ['Outlet', 'Outlet. Wajib diisi. Contoh: Justus Steak House'],
            ['Quantity', 'Jumlah dalam unit terkecil. Wajib diisi. Contoh: 100'],
            ['Cost', 'Harga per unit terkecil. Wajib diisi. Contoh: 5000'],
            ['Notes', 'Catatan tambahan. Boleh dikosongkan. Contoh: Stok awal outlet Januari 2024'],
            ['Warehouse Outlet ID', 'ID warehouse outlet. Wajib diisi. Contoh: 1'],
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
        $sheet->getStyle('A1:B7')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
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

class OutletStockBalanceSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    public function array(): array
    {
        return [
            ['ITM001', 'Nasi Goreng', 'Porsi', 'Justus Steak House', 100, 5000, 'Stok awal outlet Januari 2024', 1]
        ];
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Name',
            'Small Unit',
            'Outlet',
            'Quantity',
            'Cost',
            'Notes',
            'Warehouse Outlet ID'
        ];
    }

    public function title(): string
    {
        return 'StockBalance';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0']
            ],
        ]);
        $sheet->getStyle('A1:G2')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
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

class OutletMasterSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
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