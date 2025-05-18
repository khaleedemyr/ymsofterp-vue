<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class BomImport implements ToArray
{
    public function array(array $rows)
    {
        return $rows;
    }
} 