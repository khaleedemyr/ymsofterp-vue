<?php

namespace App\Exports;

use App\Models\BankBook;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class BankBookReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    protected $bankAccountId;
    protected $dateFrom;
    protected $dateTo;
    protected $transactionType;

    public function __construct($bankAccountId = null, $dateFrom = null, $dateTo = null, $transactionType = null)
    {
        $this->bankAccountId = $bankAccountId;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->transactionType = $transactionType;
    }

    public function collection()
    {
        $query = BankBook::with(['bankAccount.outlet', 'creator', 'updater'])
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc');

        if ($this->bankAccountId) {
            $query->where('bank_account_id', $this->bankAccountId);
        }

        if ($this->dateFrom) {
            $query->whereDate('transaction_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('transaction_date', '<=', $this->dateTo);
        }

        if ($this->transactionType) {
            $query->where('transaction_type', $this->transactionType);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Bank',
            'No. Rekening',
            'Nama Rekening',
            'Outlet',
            'Tipe Transaksi',
            'Keterangan',
            'Jumlah',
            'Saldo',
            'Referensi',
        ];
    }

    public function map($bankBook): array
    {
        return [
            $bankBook->transaction_date ? date('d/m/Y', strtotime($bankBook->transaction_date)) : '-',
            $bankBook->bankAccount->bank_name ?? '-',
            $bankBook->bankAccount->account_number ?? '-',
            $bankBook->bankAccount->account_name ?? '-',
            $bankBook->bankAccount->outlet->nama_outlet ?? 'Head Office',
            $bankBook->transaction_type === 'credit' ? 'Credit' : 'Debit',
            $bankBook->description ?? '-',
            $bankBook->amount ?? 0,
            $bankBook->balance ?? 0,
            $bankBook->reference_type && $bankBook->reference_id 
                ? "{$bankBook->reference_type} #{$bankBook->reference_id}" 
                : '-',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Tanggal
            'B' => 20, // Bank
            'C' => 18, // No. Rekening
            'D' => 25, // Nama Rekening
            'E' => 20, // Outlet
            'F' => 15, // Tipe Transaksi
            'G' => 40, // Keterangan
            'H' => 18, // Jumlah
            'I' => 18, // Saldo
            'J' => 25, // Referensi
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Buku Bank';
    }
}
