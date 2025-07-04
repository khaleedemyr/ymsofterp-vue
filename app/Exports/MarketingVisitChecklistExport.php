<?php

namespace App\Exports;

use App\Models\MarketingVisitChecklist;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class MarketingVisitChecklistExport implements FromArray, WithHeadings, WithTitle
{
    protected $checklist;

    public function __construct(MarketingVisitChecklist $checklist)
    {
        $this->checklist = $checklist;
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->checklist->items as $item) {
            $rows[] = [
                $item->no,
                $item->category,
                $item->checklist_point,
                $item->checked ? '✔' : '',
                $item->actual_condition,
                $item->action,
                collect($item->photos)->map(fn($p) => url('/storage/'.$p->photo_path))->implode(', '),
                $item->remarks,
            ];
        }
        return $rows;
    }

    public function headings(): array
    {
        return [
            ['Outlet', $this->checklist->outlet->nama_outlet ?? '-'],
            ['Tanggal Kunjungan', $this->checklist->visit_date],
            ['User Input', $this->checklist->user->name ?? '-'],
            [],
            ['No', 'Kategori', 'Checklist Point', 'Check', 'Actual Condition', 'Action', 'Picture', 'Remarks']
        ];
    }

    public function title(): string
    {
        return 'Checklist';
    }
} 