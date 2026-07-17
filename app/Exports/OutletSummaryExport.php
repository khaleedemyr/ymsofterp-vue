<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutletSummaryExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, Responsable
{
    private $rows;

    public $fileName = 'outlet_summary_attendance.xlsx';

    public function __construct($rows, ?string $fileName = null)
    {
        $this->rows = collect($rows);
        if ($fileName) {
            $this->fileName = $fileName;
        }
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
            'Karyawan',
            'Total Telat (menit)',
            'Avg Telat / Orang (menit)',
            'Total Lembur (jam)',
            'Avg Lembur / Orang (jam)',
        ];
    }

    public function map($row): array
    {
        static $no = 1;

        $row = (object) $row;

        return [
            $no++,
            $row->nama_outlet ?? '-',
            $row->employee_count ?? 0,
            $row->total_telat ?? 0,
            $row->average_telat_per_person ?? 0,
            $row->total_lembur ?? 0,
            $row->average_lembur_per_person ?? 0,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 30,
            'C' => 12,
            'D' => 20,
            'E' => 22,
            'F' => 18,
            'G' => 22,
        ];
    }

    public function toResponse($request)
    {
        return Excel::download($this, $this->fileName);
    }
}
