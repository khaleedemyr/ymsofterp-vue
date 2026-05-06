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
            'GR dibuat',
            'PO Number',
            'PO dibuat',
            'Nomor PR',
            'Nomor RO (Supplier)',
            'Outlet (floor order)',
            'Pembuat floor order',
            'User buat PO',
            'User terima GR',
            'User request PR',
            'Total Qty',
            'Total Belanja',
            'Item',
            'Satuan',
            'Qty PR',
            'Qty PO',
            'Qty GR',
            'PR (baris)',
            'PR baris dibuat',
            'RO (baris)',
            'RO baris dibuat',
            'Outlet (RO baris)',
            'Pembuat FO (baris)',
            'Subtotal baris',
        ];
    }

    public function map($row): array
    {
        $fmtDt = function ($value) {
            if (empty($value)) {
                return '-';
            }
            $t = strtotime((string) $value);

            return $t ? date('d/m/Y H:i', $t) : '-';
        };

        return [
            $row['supplier_name'] ?? '-',
            $row['supplier_code'] ?? '-',
            $row['gr_number'] ?? '-',
            isset($row['receive_date']) && $row['receive_date']
                ? date('d/m/Y', strtotime((string) $row['receive_date']))
                : '-',
            $fmtDt($row['gr_created_at'] ?? null),
            $row['po_number'] ?? '-',
            $fmtDt($row['po_created_at'] ?? null),
            ! empty($row['pr_numbers']) ? $row['pr_numbers'] : '-',
            ! empty($row['ro_order_numbers']) ? $row['ro_order_numbers'] : '-',
            ! empty($row['fo_outlet_names']) ? $row['fo_outlet_names'] : '-',
            ! empty($row['fo_creator_names']) ? $row['fo_creator_names'] : '-',
            ! empty($row['po_created_by_name']) ? $row['po_created_by_name'] : '-',
            ! empty($row['gr_received_by_name']) ? $row['gr_received_by_name'] : '-',
            ! empty($row['pr_requester_names']) ? $row['pr_requester_names'] : '-',
            number_format((float) ($row['total_qty'] ?? 0), 2, ',', '.'),
            number_format((float) ($row['total_amount'] ?? 0), 0, ',', '.'),
            ! empty($row['item_name']) ? $row['item_name'] : '-',
            ! empty($row['unit_name']) ? $row['unit_name'] : '-',
            isset($row['qty_pr']) && $row['qty_pr'] !== null && $row['qty_pr'] !== ''
                ? number_format((float) $row['qty_pr'], 2, ',', '.')
                : '-',
            isset($row['qty_po']) && $row['qty_po'] !== null && $row['qty_po'] !== ''
                ? number_format((float) $row['qty_po'], 2, ',', '.')
                : '-',
            isset($row['qty_gr']) && $row['qty_gr'] !== null && $row['qty_gr'] !== ''
                ? number_format((float) $row['qty_gr'], 2, ',', '.')
                : '-',
            ! empty($row['line_pr_number']) ? $row['line_pr_number'] : '-',
            $fmtDt($row['line_pr_created_at'] ?? null),
            ! empty($row['line_ro_number']) ? $row['line_ro_number'] : '-',
            $fmtDt($row['line_ro_created_at'] ?? null),
            ! empty($row['line_fo_outlet_name']) ? $row['line_fo_outlet_name'] : '-',
            ! empty($row['line_fo_creator_name']) ? $row['line_fo_creator_name'] : '-',
            isset($row['line_amount']) && $row['line_amount'] !== null && $row['line_amount'] !== ''
                ? number_format((float) $row['line_amount'], 0, ',', '.')
                : '-',
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
