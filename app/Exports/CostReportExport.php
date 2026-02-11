<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CostReportExport implements WithMultipleSheets
{
    public function __construct(
        protected array $reportRows,
        protected array $cogsRows,
        protected array $categoryCostRows,
        protected string $bulan
    ) {
    }

    public function sheets(): array
    {
        return [
            'Cost Inventory' => new CostReport\CostInventorySheetExport($this->reportRows),
            'COGS' => new CostReport\CogsSheetExport($this->cogsRows),
            'Category Cost' => new CostReport\CategoryCostSheetExport($this->categoryCostRows),
        ];
    }
}
