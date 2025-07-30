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

class AttendanceReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, Responsable
{
    private $rows;
    public $fileName = 'attendance_report.xlsx';

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
            'Tanggal', 'Nama Karyawan', 'Outlet', 'Jam Masuk', 'Jam Keluar', 'Total IN', 'Total OUT', 'Telat (menit)', 'Lembur (jam)', 'Status', 'Nama Libur', 'Detail', 'Shift'
        ];
    }

    public function map($row): array
    {
        // Status: OFF/libur/masuk
        $status = $row->is_off ? 'OFF' : ($row->is_holiday ? 'LIBUR' : 'MASUK');
        // Detail: jam masuk/keluar per outlet (jika ada)
        $detail = $row->detail ?? '';
        // Shift: nama, jam mulai, jam selesai
        $shift = $row->shift_name;
        if (!empty($row->shift_time_start) && !empty($row->shift_time_end)) {
            $shift .= ' (' . $row->shift_time_start . ' - ' . $row->shift_time_end . ')';
        }
        // Tambahkan indicator cross-day
        $lembur_display = $row->lembur ?? 0;
        if ($row->is_cross_day ?? false) {
            $lembur_display .= ' 🌙';
        }
        
        return [
            $row->tanggal,
            $row->nama_lengkap,
            $row->nama_outlet ?? '-',
            $row->jam_masuk ?? '-',
            $row->jam_keluar ?? '-',
            $row->total_masuk ?? 0,
            $row->total_keluar ?? 0,
            $row->telat ?? 0,
            $lembur_display,
            $status,
            $row->holiday_name ?? '',
            $detail,
            $shift,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header style
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0']
            ],
        ]);
        
        // Auto-size columns
        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, // Tanggal
            'B' => 25, // Nama Karyawan
            'C' => 20, // Outlet
            'D' => 10, // Jam Masuk
            'E' => 10, // Jam Keluar
            'F' => 8,  // Total IN
            'G' => 8,  // Total OUT
            'H' => 12, // Telat
            'I' => 12, // Lembur
            'J' => 10, // Status
            'K' => 20, // Nama Libur
            'L' => 40, // Detail
            'M' => 25, // Shift
        ];
    }

    public function toResponse($request)
    {
        return Excel::download($this, $this->fileName);
    }
} 