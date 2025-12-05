<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserShiftCalendarExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, Responsable
{
    private $data;
    private $headings;
    private $meta;
    public $fileName;

    public function __construct($data, $headings, $meta)
    {
        $this->data = $data;
        $this->headings = $headings;
        $this->meta = $meta;
        $this->fileName = 'jadwal_kerja_' . ($meta['outlet'] ?? 'all') . '_' . ($meta['bulan'] ?? '') . '_' . ($meta['tahun'] ?? '') . '.xlsx';
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:Z1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563eb']
            ],
        ]);
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $highestCol . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        // Meta info di atas tabel
        $sheet->setCellValue('A' . ($highestRow + 2), 'Outlet: ' . ($this->meta['outlet'] ?? '-'));
        $sheet->setCellValue('A' . ($highestRow + 3), 'Divisi: ' . ($this->meta['divisi'] ?? '-'));
        $sheet->setCellValue('A' . ($highestRow + 4), 'Periode: ' . ($this->meta['bulan'] ?? '-') . ' ' . ($this->meta['tahun'] ?? '-'));
        $sheet->getStyle('A' . ($highestRow + 2) . ':A' . ($highestRow + 4))->getFont()->setBold(true);
        return [];
    }

    public function columnWidths(): array
    {
        $widths = [];
        foreach (range('A', 'Z') as $col) {
            $widths[$col] = 18;
        }
        return $widths;
    }

    public function toResponse($request)
    {
        return Excel::download($this, $this->fileName);
    }
} 