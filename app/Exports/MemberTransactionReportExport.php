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

class MemberTransactionReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
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
        return 'Transaksi Member';
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'NO',
            'Tanggal Kunjungan',
            'Member ID',
            'Nama Member',
            'Tier',
            'Outlet',
            'No. Struk / Check ID',
            'Total Cover',
            'Nilai Transaksi',
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
            $row['visit_at'] ?? ($row['visit_date'] ?? ''),
            $row['member_id'] ?? '',
            $row['member_name'] ?? '',
            $row['tier_label'] ?? ($row['tier'] ?? ''),
            $row['outlet_name'] ?? '',
            $row['check_id'] ?? '',
            $row['total_cover'] ?? 0,
            $row['transaction_amount'] ?? 0,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E3A5F');
        $sheet->getStyle('A1:I1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }
}
