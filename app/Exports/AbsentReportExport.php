<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AbsentReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return collect($this->data);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nama Karyawan',
            'Outlet',
            'Divisi',
            'Jenis Izin/Cuti',
            'Tanggal Mulai',
            'Tanggal Selesai',
            'Alasan',
            'Status',
            'Tanggal Pengajuan',
            'Disetujui Oleh',
            'Tanggal Disetujui',
            'Disetujui HRD Oleh',
            'Tanggal Disetujui HRD',
            'Alasan Penolakan'
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->employee_name ?? '-',
            $row->nama_outlet ?? '-',
            $row->nama_divisi ?? '-',
            $row->leave_type_name ?? '-',
            $row->date_from ? date('d/m/Y', strtotime($row->date_from)) : '-',
            $row->date_to ? date('d/m/Y', strtotime($row->date_to)) : '-',
            $row->reason ?? '-',
            $this->getStatusText($row->status),
            $row->created_at ? date('d/m/Y H:i', strtotime($row->created_at)) : '-',
            $row->approver_name ?? '-',
            $row->approved_at ? date('d/m/Y H:i', strtotime($row->approved_at)) : '-',
            $row->hrd_approver_name ?? '-',
            $row->hrd_approved_at ? date('d/m/Y H:i', strtotime($row->hrd_approved_at)) : '-',
            $row->rejection_reason ?? '-'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function getStatusText($status)
    {
        switch ($status) {
            case 'pending':
                return 'Menunggu Persetujuan';
            case 'supervisor_approved':
                return 'Disetujui Atasan';
            case 'approved':
                return 'Disetujui';
            case 'rejected':
                return 'Ditolak';
            default:
                return ucfirst($status);
        }
    }
}
