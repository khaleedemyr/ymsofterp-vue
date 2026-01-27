<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockOpnameImportTemplateExport implements WithMultipleSheets
{
    protected $outlet;
    protected $wh;
    protected $items;
    protected $opnameDate;
    protected $notes;

    /**
     * @param object|null       $outlet  { nama_outlet }
     * @param object|null       $wh      { name }
     * @param array|iterable    $items   from getInventoryItems: { category_name, item_name, small_unit_name }
     * @param string            $opnameDate
     * @param string            $notes
     */
    public function __construct($outlet, $wh, $items = [], $opnameDate = '', $notes = '')
    {
        $this->outlet = $outlet;
        $this->wh = $wh;
        $this->items = $items;
        $this->opnameDate = $opnameDate ?: now()->format('Y-m-d');
        $this->notes = $notes;
    }

    public function sheets(): array
    {
        return [
            'Info' => new StockOpnameInfoSheet($this->outlet, $this->wh, $this->opnameDate, $this->notes),
            'Items' => new StockOpnameItemsSheet($this->items),
        ];
    }
}

class StockOpnameInfoSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    protected $outlet;
    protected $wh;
    protected $opnameDate;
    protected $notes;

    public function __construct($outlet, $wh, $opnameDate, $notes)
    {
        $this->outlet = $outlet;
        $this->wh = $wh;
        $this->opnameDate = $opnameDate;
        $this->notes = $notes;
    }

    public function array(): array
    {
        $outletName = $this->outlet ? $this->outlet->nama_outlet : 'Pilih Outlet di halaman Create, lalu download ulang';
        $whName = $this->wh ? $this->wh->name : 'Pilih Warehouse Outlet di halaman Create, lalu download ulang';

        $rows = [
            ['Outlet', $outletName],
            ['Warehouse Outlet', $whName],
            ['Tanggal Opname', $this->opnameDate],
            ['Catatan', $this->notes],
        ];

        if ($this->outlet && $this->wh) {
            array_unshift($rows, ['Petunjuk', 'Outlet & Warehouse sudah terisi. Di sheet Items: isi hanya Qty Terkecil (dan Alasan bila ada selisih).']);
        } else {
            array_unshift($rows, ['Petunjuk', 'Pilih Outlet dan Warehouse Outlet di halaman Create, lalu download ulang template.']);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Key', 'Value'];
    }

    public function title(): string
    {
        return 'Info';
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = 6; // 1 header + 5 info rows (Petunjuk + Outlet + WH + Tanggal + Catatan)
        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0'],
            ],
        ]);
        $sheet->getStyle('A1:B' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }
}

class StockOpnameItemsSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    protected $items;

    /** @param array|\Illuminate\Support\Collection $items */
    public function __construct($items = [])
    {
        $this->items = $items;
    }

    public function array(): array
    {
        $rows = [];
        $n = 1;
        foreach ($this->items as $it) {
            $rows[] = [
                $n++,
                $it->category_name ?? '',
                $it->item_name ?? '',
                '', // Qty Terkecil — user isi
                $it->small_unit_name ?? '',
                '', // Alasan — user isi bila ada selisih
            ];
        }

        if (empty($rows)) {
            $rows[] = [1, 'Contoh: Bahan Baku', 'Contoh: Tepung Terigu', '', 'kg', ''];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'No',
            'Kategori',
            'Nama Item',
            'Qty Terkecil',
            'Unit Terkecil',
            'Alasan',
        ];
    }

    public function title(): string
    {
        return 'Items';
    }

    public function styles(Worksheet $sheet)
    {
        $rowCount = count($this->items) ?: 1;
        $lastRow = $rowCount + 1;
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0070C0'],
            ],
        ]);
        $sheet->getStyle('A1:F' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }
}
