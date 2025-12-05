<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Facades\Excel;

class ItemEngineeringMultiSheetExport implements WithMultipleSheets, Responsable
{
    private $items;
    private $modifiers;
    private $outletName;
    private $dateFrom;
    private $dateTo;
    public $fileName;

    public function __construct($items, $modifiers, $outletName, $dateFrom, $dateTo)
    {
        $this->items = $items;
        $this->modifiers = $modifiers;
        $this->outletName = $outletName;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->fileName = 'item_engineering_' . ($outletName ? str_replace(' ', '_', $outletName) : 'all') . '_' . $dateFrom . '_to_' . $dateTo . '.xlsx';
    }

    public function sheets(): array
    {
        return [
            'Item Engineering' => new ItemEngineeringGroupedSheetExport($this->items),
            'Modifier Engineering' => new ModifierEngineeringSheetExport($this->modifiers),
        ];
    }

    public function toResponse($request)
    {
        return Excel::download($this, $this->fileName);
    }
} 