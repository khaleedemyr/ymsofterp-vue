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
            'No',
            'Tanggal',
            'Created At',
            'Member ID',
            'Nama Member',
            'Phone',
            'Email',
            'Tipe',
            'Transaction Type',
            'Channel',
            'Point',
            'Nilai Transaksi',
            'Paid Number',
            'Order / Reference ID',
            'Outlet',
            'Earning Rate',
            'Expires At',
            'Keterangan',
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
            $row['transaction_date'] ?? '',
            $row['created_at'] ?? '',
            $row['member_id'] ?? '',
            $row['member_name'] ?? '',
            $row['member_phone'] ?? '',
            $row['member_email'] ?? '',
            $row['type_text'] ?? '',
            $row['transaction_type'] ?? '',
            $row['channel'] ?? '',
            $row['point_amount'] ?? 0,
            $row['transaction_amount'] ?? 0,
            $row['paid_number'] ?? '',
            $row['order_id'] ?? ($row['reference_id'] ?? ''),
            $row['outlet_name'] ?? '',
            $row['earning_rate'] ?? '',
            $row['expires_at'] ?? '',
            $row['description'] ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:R1')->getFont()->setBold(true);
        $sheet->getStyle('A1:R1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EDE9FE');
        $sheet->getStyle('A1:R1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }
}
