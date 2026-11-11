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

class MemberVisitFrequencyDetailExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    private int $rowNumber = 0;

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(
        protected Collection $rows,
        protected string $sheetTitle = 'Member Detail',
    ) {
    }

    public function title(): string
    {
        $title = trim($this->sheetTitle);
        if ($title === '') {
            return 'Member Detail';
        }

        return mb_substr($title, 0, 31);
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'No',
            'Member ID',
            'Nama Member',
            'Phone',
            'Email',
            'Visit Count',
            'Total Pax Member',
            'Revenue Member',
            'First Visit',
            'Last Visit',
            'Visit Time',
            'Outlet',
            'Kode Outlet',
            'Paid Number',
            'Nomor',
            'Pax Visit',
            'Grand Total Visit',
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
            $row['member_id'] ?? '',
            $row['member_name'] ?? '',
            $row['phone'] ?? '',
            $row['email'] ?? '',
            $row['visit_count'] ?? 0,
            $row['total_pax'] ?? 0,
            $row['revenue'] ?? 0,
            $row['first_visit_at'] ?? '',
            $row['last_visit_at'] ?? '',
            $row['visit_time'] ?? '',
            $row['outlet_name'] ?? '',
            $row['kode_outlet'] ?? '',
            $row['paid_number'] ?? '',
            $row['nomor'] ?? '',
            $row['visit_pax'] ?? 0,
            $row['visit_grand_total'] ?? 0,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:Q1')->getFont()->setBold(true);
        $sheet->getStyle('A1:Q1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4F46E5');
        $sheet->getStyle('A1:Q1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:Q1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }
}
