<?php

namespace App\Exports;

use App\Exports\MampReport\DetailSheetExport;
use App\Exports\MampReport\OutletSummarySheetExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MampReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $report
    ) {}

    public function sheets(): array
    {
        return [
            new DetailSheetExport($this->report),
            new OutletSummarySheetExport($this->report),
        ];
    }
}
