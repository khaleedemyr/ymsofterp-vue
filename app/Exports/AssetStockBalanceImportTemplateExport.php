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

class AssetStockBalanceImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $items = Item::with(['category', 'smallUnit'])
            ->where('status', 'active')
            ->whereHas('category', function ($query) {
                $query->where('is_asset', '1');
            })
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                return [
                    $item->sku,
                    $item->name,
                    $item->smallUnit->name ?? '',
                ];
            })->toArray();

        $outlets = DB::table('tbl_data_outlet as o')
            ->where('o.status', 'A')
            ->leftJoin('warehouse_outlets as wo', 'wo.outlet_id', '=', 'o.id_outlet')
            ->select('o.id_outlet', 'o.nama_outlet', 'wo.id as warehouse_outlet_id', 'wo.name as warehouse_name')
            ->orderBy('o.nama_outlet')
            ->orderBy('wo.name')
            ->get()
            ->map(function ($row) {
                return [
                    $row->id_outlet,
                    $row->nama_outlet,
                    $row->warehouse_outlet_id ?? '',
                    $row->warehouse_name ?? '',
                ];
            })->toArray();

        $ownerOutlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get()
            ->map(fn ($row) => [$row->id_outlet, $row->nama_outlet])
            ->toArray();

        return [
            'Instructions' => new AssetBalanceInstructionsSheet(),
            'StockBalance' => new AssetStockBalanceSheet(),
            'Items' => new AssetBalanceMasterSheet($items, ['SKU', 'Name', 'Small Unit'], 'Items'),
            'OwnerOutlets' => new AssetBalanceMasterSheet($ownerOutlets, ['Owner Outlet ID', 'Owner Outlet Name'], 'OwnerOutlets'),
            'Outlets' => new AssetBalanceMasterSheet($outlets, ['Location Outlet ID', 'Location Outlet Name', 'Warehouse Outlet ID', 'Warehouse Name'], 'Outlets'),
        ];
    }
}

class AssetBalanceInstructionsSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    public function array(): array
    {
        return [
            ['SKU', 'Kode item asset. Wajib. Contoh: AST001'],
            ['Name', 'Nama item (harus cocok dengan SKU). Wajib.'],
            ['Small Unit', 'Unit terkecil item. Wajib. Contoh: Pcs'],
            ['Owner Outlet', 'Outlet PEMILIK aset (kepemilikan). Wajib. Nama harus sama persis seperti sheet OwnerOutlets. Jika pemilik = lokasi fisik, isi nama yang sama.'],
            ['Outlet', 'Outlet LOKASI fisik tempat barang disimpan (induk gudang). Wajib. Harus punya Warehouse Outlet ID di sheet Outlets.'],
            ['Warehouse Outlet ID', 'ID gudang (warehouse_outlets.id). Wajib. Lihat sheet Outlets — gudang harus milik outlet lokasi di kolom Outlet.'],
            ['Quantity', 'Qty dalam unit terkecil. Wajib. Contoh: 10'],
            ['Cost', 'Harga per unit terkecil. Wajib. Contoh: 15000000'],
            ['Notes', 'Catatan. Opsional.'],
            ['', ''],
            ['Catatan ownership', 'Stok di sistem = kombinasi (Item + Owner Outlet + Warehouse). Owner dan lokasi boleh berbeda outlet.'],
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
        $sheet->getStyle('A1:B12')->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(60);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A2');
            }
        ];
    }
}

class AssetStockBalanceSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    public function array(): array
    {
        return [
            ['AST001', 'Dinner Fork', 'Pcs', 'Justus Steak House Dago', 'Justus Steak House Dago', 1, 10, 50000, 'Saldo awal — pemilik & lokasi sama'],
            ['AST002', 'Office Chair', 'Pcs', 'Justus Steak House Dago', 'Kantor Pusat', 2, 5, 1200000, 'Contoh: pemilik outlet A, barang di gudang outlet B'],
        ];
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Name',
            'Small Unit',
            'Owner Outlet',
            'Outlet',
            'Warehouse Outlet ID',
            'Quantity',
            'Cost',
            'Notes',
        ];
    }

    public function title(): string
    {
        return 'StockBalance';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0']
            ],
        ]);
        $sheet->getStyle('A1:I3')->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A2');
            }
        ];
    }
}

class AssetBalanceMasterSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    protected $data;
    protected $headings;
    protected $title;

    public function __construct($data, $headings, $title)
    {
        if ($data && is_array($data) && isset($data[0]) && is_string($data[0])) {
            $data = array_map(function ($v) { return [$v]; }, $data);
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
        $sheet->getStyle('A1:' . $colLetter . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0']
            ],
        ]);
        $rowCount = count($this->data) + 1;
        $sheet->getStyle('A1:' . $colLetter . $rowCount)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A2');
            }
        ];
    }
}
