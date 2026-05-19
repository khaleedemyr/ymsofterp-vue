<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel: sheet Instructions + sheet non_pos_pricing (data import).
 */
class NonPosPricingTemplateExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(
        protected Collection $rows
    ) {}

    public function sheets(): array
    {
        return [
            'Instructions' => new NonPosPricingInstructionsSheet(),
            'non_pos_pricing' => new NonPosPricingDataSheet($this->rows),
        ];
    }
}

class NonPosPricingInstructionsSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    public function array(): array
    {
        return [
            ['', ''],
            ['Langkah', 'Keterangan'],
            ['1', 'Buka sheet "non_pos_pricing" — jangan ubah nama sheet tersebut.'],
            ['2', 'Edit hanya kolom pricing_mode dan price pada baris item yang ingin diperbarui.'],
            ['3', 'Jangan ubah item_id, category_id, category_name, sku, name, large_unit (untuk referensi & validasi).'],
            ['4', 'Simpan file Excel, lalu di menu Items → Harga non-POS klik Import Excel.'],
            ['', ''],
            ['Scope harga', 'Harga default scope All (seluruh outlet/region), per satuan BESAR (large).'],
            ['Item', 'Hanya item kategori non-POS (is_asset=0, show_pos=0) yang aktif.'],
            ['', ''],
            ['Kolom', 'Keterangan & contoh'],
            ['item_id', 'ID item di sistem. WAJIB ada. Jangan diubah. Contoh: 1523'],
            ['category_id', 'ID kategori (referensi). Jangan diubah. Jika diisi saat import harus cocok dengan item. Contoh: 12'],
            ['category_name', 'Nama kategori (referensi). Jangan diubah. Jika diisi saat import harus cocok dengan item. Contoh: Bahan Baku'],
            ['sku', 'Kode SKU item. Referensi saja. Contoh: GC-20251230-8234'],
            ['name', 'Nama item. Referensi saja. Contoh: Abon Sapi'],
            ['large_unit', 'Nama satuan besar. Referensi saja. Contoh: Pack, Liter'],
            ['pricing_mode', 'WAJIB diisi. Nilai: manual ATAU auto. Lihat penjelasan di bawah.'],
            ['price', 'Harga per satuan large. Wajib > 0 jika pricing_mode = manual. Kosongkan jika auto.'],
            ['', ''],
            ['pricing_mode = manual', 'Isi kolom price dengan angka (tanpa titik ribuan). Contoh: 138200'],
            ['pricing_mode = auto', 'Kosongkan kolom price. Sistem hitung: Food Good Receive terakhir + 12% (satuan large). Gagal jika belum ada GR.'],
            ['', ''],
            ['Tips', 'Baris yang tidak diubah tetap boleh di-import; hanya baris dengan item_id valid yang diproses.'],
            ['Tips', 'Hapus baris contoh jika ada; jangan hapus baris header di sheet non_pos_pricing.'],
        ];
    }

    public function headings(): array
    {
        return [
            'TATA CARA — Update harga item non-POS',
            '',
        ];
    }

    public function title(): string
    {
        return 'Instructions';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D97706'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT],
        ]);

        $sheet->getStyle('A3:B3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0'],
            ],
        ]);

        $sheet->getStyle('A11:B11')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0'],
            ],
        ]);

        $sheet->getStyle('A19:B20')->applyFromArray([
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEF3C7'],
            ],
        ]);

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(72);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A3');
            },
        ];
    }
}

class NonPosPricingDataSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithEvents
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(
        protected Collection $rows
    ) {}

    public function title(): string
    {
        return 'non_pos_pricing';
    }

    public function headings(): array
    {
        return [
            'item_id',
            'category_id',
            'category_name',
            'sku',
            'name',
            'large_unit',
            'pricing_mode',
            'price',
        ];
    }

    public function collection()
    {
        return $this->rows->map(function (array $r) {
            $mode = ($r['pricing_mode'] ?? 'manual') === 'auto' ? 'auto' : 'manual';
            $price = $r['price'] ?? null;
            if ($mode === 'auto') {
                $priceCell = '';
            } else {
                $priceCell = $price !== null && $price !== '' ? round((float) $price, 2) : '';
            }

            return [
                (int) $r['item_id'],
                (int) ($r['category_id'] ?? 0),
                (string) ($r['category_name'] ?? ''),
                (string) ($r['sku'] ?? ''),
                (string) ($r['name'] ?? ''),
                (string) ($r['large_unit'] ?? ''),
                $mode,
                $priceCell,
            ];
        });
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = 'H';
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0'],
            ],
        ]);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                foreach (range('A', 'H') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
