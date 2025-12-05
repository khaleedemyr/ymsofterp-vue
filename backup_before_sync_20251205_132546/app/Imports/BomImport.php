<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class BomImport implements ToArray, WithMultipleSheets, WithHeadingRow, WithCalculatedFormulas
{
    public function array(array $rows)
    {
        // Filter out empty rows
        return array_filter($rows, function($row) {
            return !empty(array_filter($row, function($cell) {
                return $cell !== null && $cell !== '';
            }));
        });
    }

    public function sheets(): array
    {
        return [
            'BOM' => $this,
        ];
    }

    public function headingRow(): int
    {
        return 1; // First row contains headers
    }
} 