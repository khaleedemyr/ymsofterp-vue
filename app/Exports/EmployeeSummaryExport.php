<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeSummaryExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, Responsable
{
    private $rows;
    public $fileName = 'employee_summary_report.xlsx';

    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return [
            'No', 'Outlet', 'NIK', 'Nama Karyawan', 'Jabatan', 'Hari Kerja', 'Off', 'PH (Bonus)', 'Cuti', 'Extra Off', 'Sakit', 'Alpa', 'OT Full', 'Telat', 'Total Days'
        ];
    }

    public function map($row): array
    {
        static $no = 1;
        
        return [
            $no++,
            $row->nama_outlet ?? '-',
            $row->nik ?? '-',
            $row->nama_lengkap,
            $row->jabatan ?? '-',
            $row->hari_kerja ?? 0,
            $row->off_days ?? 0,
            $row->ph_days ?? 0 . ' hari (' . ($row->ph_bonus ?? 0) . ')',
            $row->cuti_days ?? 0,
            $row->extra_off_days ?? 0,
            $row->sakit_days ?? 0,
            $row->alpa_days ?? 0,
            $row->ot_full_days ?? 0,
            $row->total_telat ?? 0,
            $row->total_days ?? 0,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header style
        $sheet->getStyle('A1:O1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0']
            ],
        ]);
        
        // Auto-size columns
        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,   // No
            'B' => 25,  // Outlet
            'C' => 15,  // NIK
            'D' => 35,  // Nama Karyawan
            'E' => 25,  // Jabatan
            'F' => 15,  // Hari Kerja
            'G' => 10,  // Off
            'H' => 20,  // PH (Bonus)
            'I' => 10,  // Cuti
            'J' => 15,  // Extra Off
            'K' => 10,  // Sakit
            'L' => 10,  // Alpa
            'M' => 15,  // OT Full
            'N' => 15,  // Telat
            'O' => 15,  // Total Days
        ];
    }

    public function toResponse($request)
    {
        return Excel::download($this, $this->fileName);
    }
}
