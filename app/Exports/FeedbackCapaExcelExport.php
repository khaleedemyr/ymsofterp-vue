<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class FeedbackCapaExcelExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = (int) $sheet->getHighestRow();
                if ($highestRow < 2) {
                    return;
                }

                $row = 2;
                while ($row <= $highestRow) {
                    $cellVal = (string) $sheet->getCell("A{$row}")->getCalculatedValue();
                    $startMerge = $row;
                    while (
                        $row < $highestRow
                        && (string) $sheet->getCell('A'.($row + 1))->getCalculatedValue() === $cellVal
                    ) {
                        $row++;
                    }
                    $endMerge = $row;
                    if ($endMerge > $startMerge) {
                        $sheet->mergeCells("A{$startMerge}:A{$endMerge}");
                        $sheet->getStyle("A{$startMerge}:A{$endMerge}")
                            ->getAlignment()
                            ->setVertical(Alignment::VERTICAL_CENTER)
                            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                            ->setWrapText(true);
                    }
                    $row++;
                }
            },
        ];
    }
}
