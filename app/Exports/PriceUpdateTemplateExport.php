<?php

namespace App\Exports;

use App\Models\Item;
use App\Models\Region;
use App\Models\Outlet;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PriceUpdateTemplateExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithMapping
{
    protected $regionId;
    protected $outletId;
    protected $priceType;

    public function __construct($regionId = null, $outletId = null, $priceType = 'all')
    {
        $this->regionId = $regionId;
        $this->outletId = $outletId;
        $this->priceType = $priceType;
    }

    public function collection()
    {
        $query = Item::with(['category', 'prices.region', 'prices.outlet'])
            ->where('status', 'active');

        // Filter berdasarkan kategori yang show_pos = 0 (untuk POS)
        $query->whereHas('category', function($q) {
            $q->where('show_pos', '0');
        });

        return $query->get();
    }

    public function headings(): array
    {
        $headers = [
            'Item ID', 'SKU', 'Item Name', 'Category', 'Current Price', 'New Price (Kosongkan jika tidak diupdate)', 'Price Type', 'Region/Outlet'
        ];

        // Tambahkan kolom untuk validasi
        $headers[] = 'Validation (JANGAN DIUBAH)';

        return $headers;
    }

    public function map($item): array
    {
        // Ambil harga saat ini berdasarkan filter
        $currentPrice = $this->getCurrentPrice($item);
        $priceType = $this->getPriceType($item);
        $regionOutlet = $this->getRegionOutlet($item);

        return [
            $item->id,
            $item->sku,
            $item->name,
            $item->category->name ?? '',
            $currentPrice,
            '', // New Price - kosong untuk diisi user
            $priceType,
            $regionOutlet,
            $this->generateValidationHash($item) // Hash untuk validasi
        ];
    }

    private function getCurrentPrice($item)
    {
        $price = $item->prices->where('item_id', $item->id)->first();
        return $price ? $price->price : 0;
    }

    private function getPriceType($item)
    {
        $price = $item->prices->where('item_id', $item->id)->first();
        if (!$price) return 'all';
        
        if ($price->outlet_id) return 'outlet';
        if ($price->region_id) return 'region';
        return 'all';
    }

    private function getRegionOutlet($item)
    {
        $price = $item->prices->where('item_id', $item->id)->first();
        if (!$price) return 'All';
        
        if ($price->outlet_id && $price->outlet) {
            return $price->outlet->nama_outlet;
        }
        if ($price->region_id && $price->region) {
            return $price->region->name;
        }
        return 'All';
    }

    private function generateValidationHash($item)
    {
        return md5($item->id . $item->sku . $item->name);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E3F2FD']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ]
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, // Item ID
            'B' => 15, // SKU
            'C' => 30, // Item Name
            'D' => 20, // Category
            'E' => 15, // Current Price
            'F' => 15, // New Price
            'G' => 15, // Price Type
            'H' => 25, // Region/Outlet
            'I' => 50, // Validation
        ];
    }
} 