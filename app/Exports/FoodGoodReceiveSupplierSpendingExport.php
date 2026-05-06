<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FoodGoodReceiveSupplierSpendingExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithColumnFormatting, Responsable
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

    /**
     * Angka untuk Excel harus bertipe numerik (bukan string "156.000"), agar tidak dibaca sebagai 156 desimal.
     */
    protected function floatOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
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
            (float) ($row['total_amount'] ?? 0),
            ! empty($row['item_name']) ? $row['item_name'] : '-',
            ! empty($row['unit_name']) ? $row['unit_name'] : '-',
            $this->floatOrNull($row['qty_pr'] ?? null),
            $this->floatOrNull($row['qty_po'] ?? null),
            $this->floatOrNull($row['qty_gr'] ?? null),
            ! empty($row['line_pr_number']) ? $row['line_pr_number'] : '-',
            $fmtDt($row['line_pr_created_at'] ?? null),
            ! empty($row['line_ro_number']) ? $row['line_ro_number'] : '-',
            $fmtDt($row['line_ro_created_at'] ?? null),
            ! empty($row['line_fo_outlet_name']) ? $row['line_fo_outlet_name'] : '-',
            ! empty($row['line_fo_creator_name']) ? $row['line_fo_creator_name'] : '-',
            $this->floatOrNull($row['line_amount'] ?? null),
        ];
    }

    /**
     * Kolom angka: titik/koma pada output PHP bikin Excel salah parse — pakai format sheet + nilai numerik.
     *
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'O' => '#,##0',
            'R' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'S' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'T' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'AA' => '#,##0',
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
