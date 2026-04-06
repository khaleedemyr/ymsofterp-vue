<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeLeaveBalanceExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected Collection $rows;

    public function __construct($rows)
    {
        $this->rows = $rows instanceof Collection ? $rows : collect($rows);
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'NIK',
            'Nama Karyawan',
            'Outlet',
            'Divisi',
            'Jabatan',
            'Status',
            'Saldo Cuti Tahunan (hari)',
            'PH Extra Off tersisa (hari, approved)',
            'Extra Off saldo (hari)',
        ];
    }

    public function map($row): array
    {
        return [
            $row->nik ?? '-',
            $row->nama_lengkap ?? '-',
            $row->nama_outlet ?? '-',
            $row->nama_divisi ?? '-',
            $row->nama_jabatan ?? '-',
            $this->statusLabel($row->status ?? ''),
            (float) ($row->cuti ?? 0),
            (float) ($row->ph_extra_off_days_approved ?? 0),
            (int) ($row->extra_off_balance_days ?? 0),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'A' => 'Aktif',
            'N' => 'Non-aktif',
            'B' => 'Baru',
            default => $status ?: '-',
        };
    }
}
