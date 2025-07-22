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

        $items = $query->get();
        
        // Transform items menjadi multiple rows (satu row per price)
        $rows = [];
        
        foreach ($items as $item) {
            // Jika item tidak memiliki prices, buat satu row dengan price type 'all'
            if ($item->prices->isEmpty()) {
                $rows[] = [
                    'item' => $item,
                    'price' => null,
                    'price_type' => 'all',
                    'region_outlet' => 'All',
                    'current_price' => 0
                ];
            } else {
                // Buat row untuk setiap price yang dimiliki item
                foreach ($item->prices as $price) {
                    $priceType = $price->availability_price_type;
                    $regionOutlet = 'All';
                    
                    if ($priceType === 'region' && $price->region) {
                        $regionOutlet = $price->region->name;
                    } elseif ($priceType === 'outlet' && $price->outlet) {
                        $regionOutlet = $price->outlet->nama_outlet;
                    }
                    
                    $rows[] = [
                        'item' => $item,
                        'price' => $price,
                        'price_type' => $priceType,
                        'region_outlet' => $regionOutlet,
                        'current_price' => $price->price
                    ];
                }
            }
        }
        
        return collect($rows);
    }

    public function headings(): array
    {
        $headers = [
            'Item ID', 'SKU', 'Item Name', 'Category', 'Current Price', 'New Price (Format: 14152.94 region)', 'Region/Outlet'
        ];

        // Tambahkan kolom untuk validasi
        $headers[] = 'Validation (JANGAN DIUBAH)';

        return $headers;
    }

    public function map($row): array
    {
        $item = $row['item'];
        $price = $row['price'];
        $priceType = $row['price_type'];
        $regionOutlet = $row['region_outlet'];
        $currentPrice = $row['current_price'];

        // Format new price dengan price type: "14152.94 region" atau "14152.94 all"
        // User bisa mengubah angka saja, misal: "15000 region" atau "15000 all"
        $newPriceFormat = $currentPrice > 0 ? "{$currentPrice} {$priceType}" : '';

        return [
            $item->id,
            $item->sku,
            $item->name,
            $item->category->name ?? '',
            $currentPrice,
            $newPriceFormat, // New Price dengan format yang benar
            $regionOutlet,
            $this->generateValidationHash($item) // Hash untuk validasi
        ];
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
            'F' => 25, // New Price (Format: 14152.94 region)
            'G' => 25, // Region/Outlet
            'H' => 50, // Validation
        ];
    }
} 