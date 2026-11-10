<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MemberVisitFrequencyReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    private int $rowNumber = 0;

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(protected Collection $rows)
    {
    }

    public function title(): string
    {
        return 'Visit Frequency';
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'No',
            'Outlet',
            'Kode Outlet',
            'Unique Member',
            'Total Visits',
            'Total Pax',
            'Member / Pax',
            'Visit Frequency',
            'Repeat Members',
            'Repeat Ratio %',
            'Revenue Member',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $row['outlet_name'] ?? '',
            $row['kode_outlet'] ?? '',
            $row['unique_members'] ?? 0,
            $row['total_visits'] ?? 0,
            $row['total_pax'] ?? 0,
            $row['member_per_pax'] ?? 0,
            $row['visit_frequency'] ?? 0,
            $row['repeat_members'] ?? 0,
            $row['repeat_ratio'] ?? 0,
            $row['revenue'] ?? 0,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DBEAFE');
        $sheet->getStyle('A1:K1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }
}
