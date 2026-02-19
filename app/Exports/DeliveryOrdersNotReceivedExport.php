<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Facades\Excel;

class DeliveryOrdersNotReceivedExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, Responsable
{
    protected $data;
    public $fileName = 'delivery_orders_not_received.xlsx';

    public function __construct($data)
    {
        $this->data = $data;
        $this->fileName = 'delivery_orders_not_received_' . date('Y-m-d_H-i-s') . '.xlsx';
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'DO Number',
            'DO Date',
            'Outlet Name',
            'Warehouse Outlet',
            'Division',
            'Days Not Received',
            'FO Mode',
            'Created By'
        ];
    }

    public function map($row): array
    {
        return [
            $row['do_number'] ?? '-',
            $row['do_date'] ? date('d/m/Y H:i', strtotime($row['do_date'])) : '-',
            $row['outlet_name'] ?? '-',
            $row['warehouse_outlet_name'] ?? '-',
            $row['division_name'] ?? '-',
            $row['days_not_received'] ?? 0,
            $row['fo_mode'] ?? '-',
            $row['created_by'] ?? '-'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB']
                ]
            ]
        ];
    }

    public function toResponse($request)
    {
        return Excel::download($this, $this->fileName);
    }
}
