<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EmployeeSummaryAttendanceExport implements WithMultipleSheets, Responsable
{
    private $summaryRows;
    private $groupBy;
    public $fileName = 'employee_summary_attendance.xlsx';

    public function __construct($summaryRows, $groupBy = 'outlet')
    {
        $this->summaryRows = $summaryRows;
        $this->groupBy = $groupBy;
    }

    public function sheets(): array
    {
        $sheets = [];
        
        // Sheet 1: Summary
        $sheets[] = new SummarySheet($this->summaryRows, $this->groupBy);
        
        // Sheet 2: Employee Details
        $sheets[] = new EmployeeDetailsSheet($this->summaryRows, $this->groupBy);
        
        // Sheet 3: Daily Details
        $sheets[] = new DailyDetailsSheet($this->summaryRows, $this->groupBy);
        
        return $sheets;
    }
}

class SummarySheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    private $summaryRows;
    private $groupBy;

    public function __construct($summaryRows, $groupBy)
    {
        $this->summaryRows = $summaryRows;
        $this->groupBy = $groupBy;
    }

    public function collection()
    {
        return collect($this->summaryRows);
    }

    public function headings(): array
    {
        return [
            'No',
            $this->groupBy === 'outlet' ? 'Outlet' : 'Division',
            'Total Employees',
            'Total Telat (menit)',
            'Total Lembur (jam)',
            'Total Off',
            'Average Telat per Employee',
            'Average Lembur per Employee'
        ];
    }

    public function map($row): array
    {
        static $no = 1;
        
        $avgTelat = $row['total_employees'] > 0 ? round($row['total_telat'] / $row['total_employees'], 2) : 0;
        $avgLembur = $row['total_employees'] > 0 ? round($row['total_lembur'] / $row['total_employees'], 2) : 0;
        
        return [
            $no++,
            $row['group_name'],
            $row['total_employees'],
            $row['total_telat'],
            $row['total_lembur'],
            $row['total_off'],
            $avgTelat,
            $avgLembur
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E3F2FD']
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 25,
            'C' => 15,
            'D' => 15,
            'E' => 15,
            'F' => 10,
            'G' => 20,
            'H' => 20,
        ];
    }
}

class EmployeeDetailsSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    private $summaryRows;
    private $groupBy;

    public function __construct($summaryRows, $groupBy)
    {
        $this->summaryRows = $summaryRows;
        $this->groupBy = $groupBy;
    }

    public function collection()
    {
        $data = collect();
        
        foreach ($this->summaryRows as $group) {
            foreach ($group['employees'] as $employee) {
                $data->push([
                    'group_name' => $group['group_name'],
                    'group_type' => $group['group_type'],
                    'user_id' => $employee['user_id'],
                    'nama_lengkap' => $employee['nama_lengkap'],
                    'total_telat' => $employee['total_telat'],
                    'total_lembur' => $employee['total_lembur'],
                    'total_off' => $employee['total_off'],
                    'total_working_days' => $employee['total_working_days']
                ]);
            }
        }
        
        return $data;
    }

    public function headings(): array
    {
        return [
            'No',
            $this->groupBy === 'outlet' ? 'Outlet' : 'Division',
            'Employee ID',
            'Nama Lengkap',
            'Total Telat (menit)',
            'Total Lembur (jam)',
            'Total Off',
            'Working Days',
            'Average Telat per Day',
            'Average Lembur per Day'
        ];
    }

    public function map($row): array
    {
        static $no = 1;
        
        $avgTelatPerDay = $row['total_working_days'] > 0 ? round($row['total_telat'] / $row['total_working_days'], 2) : 0;
        $avgLemburPerDay = $row['total_working_days'] > 0 ? round($row['total_lembur'] / $row['total_working_days'], 2) : 0;
        
        return [
            $no++,
            $row['group_name'],
            $row['user_id'],
            $row['nama_lengkap'],
            $row['total_telat'],
            $row['total_lembur'],
            $row['total_off'],
            $row['total_working_days'],
            $avgTelatPerDay,
            $avgLemburPerDay
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F5E8']
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 25,
            'C' => 12,
            'D' => 25,
            'E' => 15,
            'F' => 15,
            'G' => 10,
            'H' => 12,
            'I' => 18,
            'J' => 18,
        ];
    }
}

class DailyDetailsSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    private $summaryRows;
    private $groupBy;

    public function __construct($summaryRows, $groupBy)
    {
        $this->summaryRows = $summaryRows;
        $this->groupBy = $groupBy;
    }

    public function collection()
    {
        $data = collect();
        
        foreach ($this->summaryRows as $group) {
            foreach ($group['employees'] as $employee) {
                foreach ($employee['details'] as $detail) {
                    $data->push([
                        'group_name' => $group['group_name'],
                        'group_type' => $group['group_type'],
                        'user_id' => $employee['user_id'],
                        'nama_lengkap' => $employee['nama_lengkap'],
                        'tanggal' => $detail['tanggal'],
                        'jam_masuk' => $detail['jam_masuk'],
                        'jam_keluar' => $detail['jam_keluar'],
                        'telat' => $detail['telat'],
                        'lembur' => $detail['lembur'],
                        'is_off' => $detail['is_off'],
                        'is_cross_day' => $detail['is_cross_day']
                    ]);
                }
            }
        }
        
        return $data;
    }

    public function headings(): array
    {
        return [
            'No',
            $this->groupBy === 'outlet' ? 'Outlet' : 'Division',
            'Employee ID',
            'Nama Lengkap',
            'Tanggal',
            'Jam Masuk',
            'Jam Keluar',
            'Telat (menit)',
            'Lembur (jam)',
            'Status',
            'Cross Day'
        ];
    }

    public function map($row): array
    {
        static $no = 1;
        
        $status = $row['is_off'] ? 'Off' : 'Normal';
        $jamMasuk = $row['jam_masuk'] ? date('H:i:s', strtotime($row['jam_masuk'])) : '-';
        $jamKeluar = $row['jam_keluar'] ? date('H:i:s', strtotime($row['jam_keluar'])) : '-';
        
        return [
            $no++,
            $row['group_name'],
            $row['user_id'],
            $row['nama_lengkap'],
            date('d/m/Y', strtotime($row['tanggal'])),
            $jamMasuk,
            $jamKeluar,
            $row['telat'],
            $row['lembur'],
            $status,
            $row['is_cross_day'] ? 'Yes' : 'No'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFF3E0']
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 25,
            'C' => 12,
            'D' => 25,
            'E' => 12,
            'F' => 12,
            'G' => 12,
            'H' => 12,
            'I' => 12,
            'J' => 10,
            'K' => 10,
        ];
    }
}
