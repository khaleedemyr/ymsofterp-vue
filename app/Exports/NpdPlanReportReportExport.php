<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NpdPlanReportReportExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    /** @var array<string, mixed> */
    private array $payload;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function headings(): array
    {
        return [
            'NO',
            'REPORT NUMBER',
            'REPORT MONTH',
            'OUTLET',
            'STATUS',
            'CREATED BY',
            'PRODUCT NAME',
            'CATEGORY',
            'PIC',
            'DEV. DATE',
            'PURPOSE',
            'LAUNCH DATE',
            'AREA / OUTLET',
            'F&B COST',
            'SELLING PRICE',
        ];
    }

    public function collection(): Collection
    {
        return collect($this->payload['rows'] ?? [])->map(function (array $row): array {
            return [
                $row['no'] ?? '',
                $row['report_number'] ?? '',
                $row['report_month'] ?? '',
                $row['outlet'] ?? '',
                $row['status_label'] ?? $row['status'] ?? '',
                $row['created_by'] ?? '',
                $row['product_name'] ?? '',
                $row['category'] ?? '',
                $row['pics'] ?? '',
                $row['development_date'] ?? '',
                $row['purpose_label'] ?? $row['purpose'] ?? '',
                $row['proposed_launch_date'] ?? '',
                $row['launch_outlets'] ?? '',
                $row['fb_cost'] ?? 0,
                $row['selling_price'] ?? 0,
            ];
        });
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D97706'],
                ],
            ],
        ];
    }
}
