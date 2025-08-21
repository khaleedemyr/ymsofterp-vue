<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Facades\Excel;

class SalesPivotSpecialExport implements WithMultipleSheets, Responsable
{
    private $report;
    private $retailReport;
    private $warehouseReport;
    private $tanggal;
    public $fileName = 'sales_pivot_special.xlsx';

    public function __construct($report, $tanggal = null, $retailReport = null, $warehouseReport = null)
    {
        $this->report = $report;
        $this->retailReport = $retailReport;
        $this->warehouseReport = $warehouseReport;
        $this->tanggal = $tanggal;
        if ($tanggal) {
            $this->fileName = 'sales_pivot_special_' . $tanggal . '.xlsx';
        }
    }

    public function sheets(): array
    {
        return [
            'OUTLET' => new OutletSheet($this->report, $this->tanggal),
            'Retail Warehouse Sales' => new RetailSheet($this->retailReport, $this->tanggal),
            'Warehouse Sales' => new WarehouseSheet($this->warehouseReport, $this->tanggal),
        ];
    }

    public function toResponse($request)
    {
        try {
            return Excel::download($this, $this->fileName);
        } catch (\Exception $e) {
            \Log::error('Export toResponse error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat generate file Excel'], 500);
        }
    }
} 