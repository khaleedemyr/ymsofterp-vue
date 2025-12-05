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
            'No', 
            'NIK', 
            'Nama Karyawan', 
            'Jabatan', 
            'Hari Kerja', 
            'Off', 
            'PH (Bonus)', 
            'Extra Off', 
            'Annual Leave', 
            'Bereavement (Duka Cita)', 
            'Extra Off', 
            'Hospitalize (Rawat Inap)', 
            'Maternity (Melahirkan)', 
            'Matrimony (Menikah)', 
            'Public Holiday', 
            'Sick Leave', 
            'Unpaid Leave', 
            'Wife\'s Maternity Leave', 
            'Alpa', 
            'OT Full', 
            'Telat', 
            'Total Days'
        ];
    }

    public function map($row): array
    {
        static $no = 1;
        
        return [
            $no++,
            $row->nik ?? '-',
            $row->nama_lengkap,
            $row->jabatan ?? '-',
            $row->hari_kerja ?? 0,
            $row->off_days ?? 0,
            $row->ph_days ?? 0 . ' hari (' . ($row->ph_bonus ?? 0) . ')',
            $row->extra_off_days ?? 0,
            $row->annual_leave_days ?? 0,
            $row->bereavement_leave_days ?? 0,
            $row->extra_off_days ?? 0, // Duplikat Extra Off
            $row->hospitalize_leave_days ?? 0,
            $row->maternity_leave_days ?? 0,
            $row->matrimony_leave_days ?? 0,
            $row->public_holiday_days ?? 0,
            $row->sick_leave_days ?? 0,
            $row->unpaid_leave_days ?? 0,
            $row->wife_maternity_leave_days ?? 0,
            $row->alpa_days ?? 0,
            $row->ot_full_days ?? 0,
            $row->total_telat ?? 0,
            $row->total_days ?? 0,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header style
        $sheet->getStyle('A1:V1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0']
            ],
        ]);
        
        // Auto-size columns
        foreach (range('A', 'V') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,   // No
            'B' => 15,  // NIK
            'C' => 35,  // Nama Karyawan
            'D' => 25,  // Jabatan
            'E' => 15,  // Hari Kerja
            'F' => 10,  // Off
            'G' => 20,  // PH (Bonus)
            'H' => 15,  // Extra Off
            'I' => 15,  // Annual Leave
            'J' => 20,  // Bereavement (Duka Cita)
            'K' => 15,  // Extra Off (duplikat)
            'L' => 20,  // Hospitalize (Rawat Inap)
            'M' => 20,  // Maternity (Melahirkan)
            'N' => 20,  // Matrimony (Menikah)
            'O' => 15,  // Public Holiday
            'P' => 15,  // Sick Leave
            'Q' => 15,  // Unpaid Leave
            'R' => 20,  // Wife's Maternity Leave
            'S' => 10,  // Alpa
            'T' => 15,  // OT Full
            'U' => 15,  // Telat
            'V' => 15,  // Total Days
        ];
    }

    public function toResponse($request)
    {
        return Excel::download($this, $this->fileName);
    }
}
