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
            'Tanggal', 'Nama Karyawan', 'Jam Masuk', 'Jam Keluar', 'Telat (menit)', 'Lembur (jam)', 'Status', 'Nama Libur', 'Detail', 'Shift'
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
        return [
            $row->tanggal,
            $row->nama_lengkap,
            $row->jam_masuk ?? '-',
            $row->jam_keluar ?? '-',
            $row->telat ?? 0,
            $row->lembur ?? 0,
            $status,
            $row->holiday_name ?? '',
            $detail,
            $shift,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header style
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0']
            ],
        ]);
        // Baris warna sesuai status
        $highestRow = $sheet->getHighestRow();
        for ($i = 2; $i <= $highestRow; $i++) {
            $status = $sheet->getCell('G'.$i)->getValue();
            if ($status === 'OFF') {
                $sheet->getStyle('A'.$i.':J'.$i)->applyFromArray([
                    'font' => ['italic' => true, 'color' => ['rgb' => '888888']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E5E7EB'] // abu-abu
                    ],
                ]);
            } elseif ($status === 'LIBUR') {
                $sheet->getStyle('A'.$i.':J'.$i)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'B91C1C']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FECACA'] // merah muda
                    ],
                ]);
            } elseif ($status === 'MASUK') {
                if ($i % 2 === 0) {
                    $sheet->getStyle('A'.$i.':J'.$i)->applyFromArray([
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'EFF6FF'] // biru muda
                        ],
                    ]);
                }
            }
        }
        // Border semua cell
        $highestCol = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $highestCol . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        return [];
    }

    public function columnWidths(): array
    {
        $widths = [];
        foreach (range('A', 'J') as $col) {
            $widths[$col] = 22;
        }
        return $widths;
    }

    public function toResponse($request)
    {
        return Excel::download($this, $this->fileName);
    }
} 