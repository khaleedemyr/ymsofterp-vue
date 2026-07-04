<?php

namespace App\Exports;

use App\Services\FbProductCalibrationService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FbProductCalibrationReportExport implements FromCollection, WithCustomStartCell, WithEvents, WithStyles, ShouldAutoSize
{
    private const DATA_START_ROW = 5;

    private const FIXED_HEADERS = [
        'NO',
        'PRODUCT NAME',
        'CATEGORY',
        'CALIBRATION DATE',
        'EMPLOYEE NAME',
        'OUTLET',
        'CONDUCTED BY',
    ];

    /** @var array<string, mixed> */
    private array $payload;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function startCell(): string
    {
        return 'A'.self::DATA_START_ROW;
    }

    public function collection(): Collection
    {
        return collect($this->payload['rows'] ?? [])->map(function (array $row): array {
            $line = [
                $row['no'] ?? '',
                $row['product_name'] ?? '',
                $row['category'] ?? '',
                $row['calibration_date'] ?? '',
                $row['employee_name'] ?? '',
                $row['outlet'] ?? '',
                $row['conducted_by'] ?? '',
            ];

            foreach ($this->parameterCodesFromPayload() as $code) {
                $value = $row['parameters'][$code] ?? null;
                $line[] = $value === 'C' ? '✓' : '';
                $line[] = $value === 'NC' ? '✓' : '';
            }

            return $line;
        });
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = $this->lastColumnLetter();
        $headerRange = 'A2:'.$lastColumn.'4';
        $dataRowCount = count($this->payload['rows'] ?? []);
        $lastDataRow = self::DATA_START_ROW + max($dataRowCount, 1) - 1;

        return [
            'A1:'.$lastColumn.'1' => [
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            $headerRange => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F2937'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
            'A'.self::DATA_START_ROW.':'.$lastColumn.$lastDataRow => [
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D1D5DB'],
                    ],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $this->lastColumnLetter();
                $paramCount = count($this->parameterCodesFromPayload());

                foreach (self::FIXED_HEADERS as $index => $label) {
                    $column = Coordinate::stringFromColumnIndex($index + 1);
                    $sheet->setCellValue($column.'2', $label);
                    $sheet->mergeCells($column.'2:'.$column.'4');
                }

                $paramStartColumn = count(self::FIXED_HEADERS) + 1;
                $paramStartLetter = Coordinate::stringFromColumnIndex($paramStartColumn);
                $paramEndLetter = Coordinate::stringFromColumnIndex($paramStartColumn + ($paramCount * 2) - 1);
                $sheet->setCellValue($paramStartLetter.'2', 'CALIBRATION PARAMETER');
                $sheet->mergeCells($paramStartLetter.'2:'.$paramEndLetter.'2');

                $labels = collect($this->payload['parameter_options'] ?? [])
                    ->pluck('label')
                    ->values()
                    ->all();

                if (count($labels) < $paramCount) {
                    $labels = [
                        'Presentation',
                        'Taste Profile',
                        'Portion Size',
                        'Recipe Compliance',
                        'Cooking Method',
                        'Texture',
                        'Temperature',
                    ];
                }

                $columnIndex = $paramStartColumn;
                foreach ($labels as $label) {
                    $start = Coordinate::stringFromColumnIndex($columnIndex);
                    $end = Coordinate::stringFromColumnIndex($columnIndex + 1);
                    $sheet->setCellValue($start.'3', strtoupper($label));
                    $sheet->mergeCells($start.'3:'.$end.'3');
                    $sheet->setCellValue($start.'4', 'C');
                    $sheet->setCellValue($end.'4', 'NC');
                    $columnIndex += 2;
                }

                $filters = $this->payload['filters'] ?? [];
                $title = sprintf(
                    'F&B Product Calibration Report (%s s/d %s)',
                    $filters['date_from'] ?? '-',
                    $filters['date_to'] ?? '-'
                );
                $sheet->mergeCells('A1:'.$lastColumn.'1');
                $sheet->setCellValue('A1', $title);

                $sheet->getRowDimension(1)->setRowHeight(24);
                for ($row = 2; $row <= 4; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(22);
                }
            },
        ];
    }

    private function parameterCodesFromPayload(): array
    {
        $codes = $this->payload['parameter_codes'] ?? null;
        if (is_array($codes) && $codes !== []) {
            return $codes;
        }

        return FbProductCalibrationService::ALL_PARAMETER_CODES;
    }

    private function lastColumnLetter(): string
    {
        $totalColumns = count(self::FIXED_HEADERS) + (count($this->parameterCodesFromPayload()) * 2);

        return Coordinate::stringFromColumnIndex($totalColumns);
    }
}
