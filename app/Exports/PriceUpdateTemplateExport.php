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
    protected $regionIds;
    protected $outletIds;
    protected $categoryIds;
    protected $subCategoryIds;
    protected $priceType;

    public function __construct($regionIds = [], $outletIds = [], $categoryIds = [], $subCategoryIds = [], $priceType = 'all')
    {
        // Support backward compatibility: jika parameter adalah single value, convert ke array
        $this->regionIds = is_array($regionIds) ? $regionIds : (!empty($regionIds) ? [$regionIds] : []);
        $this->outletIds = is_array($outletIds) ? $outletIds : (!empty($outletIds) ? [$outletIds] : []);
        $this->categoryIds = is_array($categoryIds) ? $categoryIds : (!empty($categoryIds) ? [$categoryIds] : []);
        $this->subCategoryIds = is_array($subCategoryIds) ? $subCategoryIds : (!empty($subCategoryIds) ? [$subCategoryIds] : []);
        $this->priceType = $priceType;
    }

    public function collection()
    {
        $query = Item::with(['category', 'subCategory', 'prices.region', 'prices.outlet'])
            ->where('status', 'active');

        // Filter by category_ids
        if (!empty($this->categoryIds)) {
            $query->whereIn('category_id', $this->categoryIds);
        }

        // Filter by sub_category_ids
        if (!empty($this->subCategoryIds)) {
            $query->whereIn('sub_category_id', $this->subCategoryIds);
        }

        // Jika user pilih region, ambil juga outlet_ids yang ada di region tersebut
        $expandedRegionIds = $this->regionIds;
        $expandedOutletIds = $this->outletIds;
        
        if (!empty($this->regionIds)) {
            $outletsInRegions = \DB::table('tbl_data_outlet')
                ->whereIn('region_id', $this->regionIds)
                ->where('status', 'A')
                ->pluck('id_outlet')
                ->toArray();
            $expandedOutletIds = array_unique(array_merge($expandedOutletIds, $outletsInRegions));
        }

        // Jika user pilih outlet, ambil juga region_ids dari outlet tersebut
        if (!empty($this->outletIds)) {
            $regionsFromOutlets = \DB::table('tbl_data_outlet')
                ->whereIn('id_outlet', $this->outletIds)
                ->where('status', 'A')
                ->pluck('region_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
            $expandedRegionIds = array_unique(array_merge($expandedRegionIds, $regionsFromOutlets));
        }

        $items = $query->get();
        
        // Transform items menjadi multiple rows (satu row per price)
        $rows = [];
        
        foreach ($items as $item) {
            // Filter prices berdasarkan region_ids atau outlet_ids jika ada
            $filteredPrices = $item->prices;
            $priceType = $this->priceType;
            
            // Jika ada filter region/outlet, filter prices
            if (!empty($expandedRegionIds) || !empty($expandedOutletIds)) {
                $filteredPrices = $filteredPrices->filter(function($price) use ($expandedRegionIds, $expandedOutletIds, $priceType) {
                    // Always include 'all' type
                    if ($price->availability_price_type === 'all') {
                        return true;
                    }
                    
                    // Include region prices
                    if ($price->availability_price_type === 'region' && !empty($expandedRegionIds)) {
                        return in_array($price->region_id, $expandedRegionIds);
                    }
                    
                    // Include outlet prices
                    if ($price->availability_price_type === 'outlet' && !empty($expandedOutletIds)) {
                        return in_array($price->outlet_id, $expandedOutletIds);
                    }
                    
                    return false;
                });
            }

            // Jika item tidak memiliki prices yang sesuai filter, buat row untuk region/outlet yang dipilih
            if ($filteredPrices->isEmpty()) {
                // Jika ada filter region, buat row untuk setiap region yang dipilih
                if (!empty($expandedRegionIds)) {
                    $regions = \DB::table('regions')->whereIn('id', $expandedRegionIds)->get();
                    foreach ($regions as $region) {
                        $rows[] = [
                            'item' => $item,
                            'price' => null,
                            'price_type' => 'region',
                            'region_outlet' => $region->name,
                            'current_price' => 0
                        ];
                    }
                }
                // Jika ada filter outlet, buat row untuk setiap outlet yang dipilih
                if (!empty($expandedOutletIds)) {
                    $outlets = \DB::table('tbl_data_outlet')->whereIn('id_outlet', $expandedOutletIds)->get();
                    foreach ($outlets as $outlet) {
                        $rows[] = [
                            'item' => $item,
                            'price' => null,
                            'price_type' => 'outlet',
                            'region_outlet' => $outlet->nama_outlet,
                            'current_price' => 0
                        ];
                    }
                }
                // Jika tidak ada filter sama sekali, buat row dengan 'All'
                if (empty($expandedRegionIds) && empty($expandedOutletIds)) {
                    $rows[] = [
                        'item' => $item,
                        'price' => null,
                        'price_type' => 'all',
                        'region_outlet' => 'All',
                        'current_price' => 0
                    ];
                }
            } else {
                // Buat row untuk setiap price yang sesuai filter
                foreach ($filteredPrices as $price) {
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
            'Item ID', 
            'SKU', 
            'Item Name', 
            'Category', 
            'Region/Outlet Name', 
            'Current Price', 
            'New Price (Isi untuk update, kosongkan jika tidak diubah)'
        ];

        // Tambahkan kolom untuk validasi
        $headers[] = 'Validation Hash (JANGAN DIUBAH)';

        return $headers;
    }

    public function map($row): array
    {
        $item = $row['item'];
        $price = $row['price'];
        $priceType = $row['price_type'];
        $regionOutlet = $row['region_outlet'];
        $currentPrice = $row['current_price'];

        // Format yang lebih sederhana: tidak perlu kolom Price Type, langsung berdasarkan Region/Outlet Name
        return [
            $item->id,
            $item->sku,
            $item->name,
            $item->category->name ?? '',
            $regionOutlet, // Region/Outlet Name - akan digunakan untuk detect price type
            $currentPrice,
            '', // New Price - kosongkan, user akan mengisi
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
            'E' => 25, // Region/Outlet Name
            'F' => 15, // Current Price
            'G' => 40, // New Price
            'H' => 50, // Validation Hash
        ];
    }
} 