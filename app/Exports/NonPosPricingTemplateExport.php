<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Template Excel untuk update mode & harga item non-POS (scope all, satuan large).
 */
class NonPosPricingTemplateExport implements FromCollection, WithHeadings, WithTitle
{
    /**
     * @param  Collection<int, array{item_id:int,category_id?:int,category_name?:?string,sku:string,name:string,large_unit:string,pricing_mode:string,price:mixed}>  $rows
     */
    public function __construct(
        protected Collection $rows
    ) {}

    public function title(): string
    {
        return 'non_pos_pricing';
    }

    public function headings(): array
    {
        return [
            'item_id',
            'category_id',
            'category_name',
            'sku',
            'name',
            'large_unit',
            'pricing_mode',
            'price',
        ];
    }

    public function collection()
    {
        return $this->rows->map(function (array $r) {
            $mode = ($r['pricing_mode'] ?? 'manual') === 'auto' ? 'auto' : 'manual';
            $price = $r['price'] ?? null;
            if ($mode === 'auto') {
                $priceCell = '';
            } else {
                $priceCell = $price !== null && $price !== '' ? round((float) $price, 2) : '';
            }

            return [
                (int) $r['item_id'],
                (int) ($r['category_id'] ?? 0),
                (string) ($r['category_name'] ?? ''),
                (string) ($r['sku'] ?? ''),
                (string) ($r['name'] ?? ''),
                (string) ($r['large_unit'] ?? ''),
                $mode,
                $priceCell,
            ];
        });
    }
}
