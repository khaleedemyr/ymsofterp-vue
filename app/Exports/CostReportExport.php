<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CostReportExport implements WithMultipleSheets
{
    public function __construct(
        protected array $reportRows,
        protected array $cogsRows,
        protected array $categoryCostRows,
        protected string $bulan,
        protected array $cogsRowsMtd = []
    ) {
    }

    public function sheets(): array
    {
        return [
            new CostReport\CostInventorySheetExport($this->reportRows),
            new CostReport\CogsSheetExport($this->cogsRows, 'Weekly'),
            new CostReport\CogsSheetExport($this->cogsRowsMtd, 'MTD'),
            new CostReport\CategoryCostSheetExport($this->categoryCostRows),
        ];
    }
}
