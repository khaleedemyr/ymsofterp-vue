<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FeedbackCapaExcelExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
     * @param  Collection<int, array{bagian: string, field: string, nilai: string}>  $rows
     */
    public function __construct(
        protected Collection $rows
    ) {}

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Bagian', 'Field', 'Nilai'];
    }

    /**
     * @param  array{bagian: string, field: string, nilai: string}  $row
     */
    public function map($row): array
    {
        return [
            $row['bagian'] ?? '',
            $row['field'] ?? '',
            $row['nilai'] ?? '',
        ];
    }
}
