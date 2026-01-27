<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Import class for reading Stock Opname Excel (Info + Items).
 * Used by Excel::toArray() for preview and by controller for import logic.
 * MAC is NOT read from file; it is taken from outlet_food_inventory_stocks.last_cost_small.
 */
class StockOpnameImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Info' => new StockOpnameInfoSheetImport(),
            'Items' => new StockOpnameItemsSheetImport(),
        ];
    }
}

class StockOpnameInfoSheetImport implements ToCollection, WithHeadingRow, WithTitle
{
    public function collection(Collection $rows)
    {
        // Used only when Excel::import(); for toArray() this is not called.
    }

    public function title(): string
    {
        return 'Info';
    }
}

class StockOpnameItemsSheetImport implements ToCollection, WithHeadingRow, WithTitle
{
    public function collection(Collection $rows)
    {
        // Used only when Excel::import(); for toArray() this is not called.
    }

    public function title(): string
    {
        return 'Items';
    }
}
