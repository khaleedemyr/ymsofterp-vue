<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompetitorBenchmarkReportReportExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
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
            'PIC',
            'CREATED BY',
            'BRAND / RESTAURANT',
            'LOCATION',
            'VISIT DATE',
            'PRODUCT BENCHMARK',
            'SERVICE BENCHMARK',
            'PRICING BENCHMARK',
            'OPERATIONAL BENCHMARK',
            'MARKET & POSITIONING',
            'SUMMARY REPORT',
            'DEVELOPMENT & ACTION PLAN',
        ];
    }

    public function collection(): Collection
    {
        return collect($this->payload['rows'] ?? [])->map(function (array $row): array {
            return [
                $row['no'] ?? '',
                $row['report_number'] ?? '',
                $row['report_month'] ?? '',
                $row['pics'] ?? '',
                $row['created_by'] ?? '',
                $row['brand_restaurant_visited'] ?? '',
                $row['location'] ?? '',
                $row['visit_date'] ?? '',
                $row['product_benchmark'] ?? '',
                $row['service_benchmark'] ?? '',
                $row['pricing_benchmark'] ?? '',
                $row['operational_benchmark'] ?? '',
                $row['market_positioning_benchmark'] ?? '',
                $row['summary_report'] ?? '',
                $row['development_action_plan'] ?? '',
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
                    'startColor' => ['rgb' => '0D9488'],
                ],
            ],
        ];
    }
}
