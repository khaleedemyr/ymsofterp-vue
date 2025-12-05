<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HolidayAttendanceExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    private $rows;
    public $fileName = 'holiday_attendance_compensations.xlsx';

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
            'Date',
            'Employee Name',
            'NIK',
            'Job Position',
            'Level',
            'Outlet',
            'Division',
            'Compensation Type',
            'Amount',
            'Status',
            'Used Date',
            'Notes'
        ];
    }

    public function map($row): array
    {
        // Format compensation type
        $compensationTypeText = $row->compensation_type === 'extra_off' ? 'Extra Off Day' : 'Holiday Bonus';
        
        // Format status
        $statusText = ucfirst($row->status);
        
        // Format amount
        $amount = $row->compensation_type === 'extra_off' ? '1 day' : 'Rp ' . number_format($row->compensation_amount, 0, ',', '.');
        
        return [
            $row->holiday_date,
            $row->nama_lengkap,
            $row->nik,
            $row->nama_jabatan,
            $row->nama_level,
            $row->nama_outlet ?? '',
            $row->nama_divisi ?? '',
            $compensationTypeText,
            $amount,
            $statusText,
            $row->used_date ?? '',
            $row->notes ?? ''
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header style
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0']
            ],
        ]);
        
        // Auto-size columns
        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, // Date
            'B' => 25, // Employee Name
            'C' => 12, // NIK
            'D' => 30, // Job Position
            'E' => 20, // Level
            'F' => 25, // Outlet
            'G' => 25, // Division
            'H' => 18, // Compensation Type
            'I' => 15, // Amount
            'J' => 12, // Status
            'K' => 12, // Used Date
            'L' => 30, // Notes
        ];
    }

}
