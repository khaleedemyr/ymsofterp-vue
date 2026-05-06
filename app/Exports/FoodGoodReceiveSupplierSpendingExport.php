<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FoodGoodReceiveSupplierSpendingExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, Responsable
{
    protected Collection $data;

    public string $fileName;

    public function __construct(Collection $data)
    {
        $this->data = $data;
        $this->fileName = 'warehouse_gr_supplier_spending_' . date('Y-m-d_H-i-s') . '.xlsx';
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Supplier',
            'Kode Supplier',
            'GR Number',
            'Tanggal Terima',
            'PO Number',
            'Nomor PR',
            'Nomor RO (Supplier)',
            'User buat PO',
            'User terima GR',
            'User request PR',
            'Total Qty',
            'Total Belanja',
        ];
    }

    public function map($row): array
    {
        return [
            $row['supplier_name'] ?? '-',
            $row['supplier_code'] ?? '-',
            $row['gr_number'] ?? '-',
            isset($row['receive_date']) && $row['receive_date']
                ? date('d/m/Y', strtotime($row['receive_date']))
                : '-',
            $row['po_number'] ?? '-',
            ! empty($row['pr_numbers']) ? $row['pr_numbers'] : '-',
            ! empty($row['ro_order_numbers']) ? $row['ro_order_numbers'] : '-',
            ! empty($row['po_created_by_name']) ? $row['po_created_by_name'] : '-',
            ! empty($row['gr_received_by_name']) ? $row['gr_received_by_name'] : '-',
            ! empty($row['pr_requester_names']) ? $row['pr_requester_names'] : '-',
            number_format((float) ($row['total_qty'] ?? 0), 2, ',', '.'),
            number_format((float) ($row['total_amount'] ?? 0), 0, ',', '.'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
            ],
        ];
    }

    public function toResponse($request)
    {
        return Excel::download($this, $this->fileName);
    }
}
