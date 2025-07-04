<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BomImport implements ToArray, WithMultipleSheets
{
    public function array(array $rows)
    {
        return $rows;
    }

    public function sheets(): array
    {
        return [
            'BOM' => $this,
        ];
    }
} 