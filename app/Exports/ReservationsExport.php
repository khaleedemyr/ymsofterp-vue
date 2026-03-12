<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReservationsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(private Collection $rows)
    {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Nama',
            'No. Telepon',
            'Outlet',
            'Tanggal Reservasi',
            'Jam Reservasi',
            'Jumlah Tamu',
            'Area',
            'DP',
            'Kode DP',
            'Jenis Pembayaran',
            'Sales',
            'Status',
            'Dibuat Oleh',
            'Dibuat Pada',
        ];
    }

    public function map($reservation): array
    {
        return [
            $reservation->name,
            $reservation->phone ?? '-',
            $reservation->outlet?->nama_outlet ?? '-',
            $reservation->reservation_date ? Carbon::parse($reservation->reservation_date)->format('Y-m-d') : '-',
            $reservation->reservation_time ? Carbon::parse($reservation->reservation_time)->format('H:i') : '-',
            (int) ($reservation->number_of_guests ?? 0),
            $reservation->smoking_preference === 'smoking' ? 'Smoking' : 'Non-Smoking',
            (float) ($reservation->dp ?? 0),
            $reservation->dp_code ?? '-',
            $reservation->paymentType?->name ?? '-',
            $reservation->from_sales
                ? ('Dari Sales' . ($reservation->salesUser?->nama_lengkap ? ' - ' . $reservation->salesUser->nama_lengkap : ''))
                : 'Bukan Sales',
            $this->formatStatus($reservation->status),
            $reservation->creator ? ($reservation->creator->nama_lengkap ?? $reservation->creator->name ?? '-') : '-',
            $reservation->created_at ? Carbon::parse($reservation->created_at)->format('Y-m-d H:i:s') : '-',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'H' => '[$Rp-421] #,##0',
        ];
    }

    private function formatStatus(?string $status): string
    {
        return match ($status) {
            'confirmed' => 'Confirmed',
            'arrived' => 'Datang',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
            default => 'Pending',
        };
    }
}
