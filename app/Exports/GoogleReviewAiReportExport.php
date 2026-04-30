<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GoogleReviewAiReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        protected Collection $rows,
        protected ?string $outletName = null
    ) {}

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'No',
            'Outlet',
            'Penulis',
            'Rating',
            'Tanggal',
            'Teks',
            'Severity',
            'Topik',
            'Ringkasan AI',
        ];
    }

    /**
     * @param  object  $row
     */
    public function map($row): array
    {
        $topics = $row->topics ?? '[]';
        if (is_string($topics)) {
            $dec = json_decode($topics, true);
            $topicsStr = is_array($dec) ? implode(', ', $dec) : $topics;
        } elseif (is_array($topics)) {
            $topicsStr = implode(', ', $topics);
        } else {
            $topicsStr = '';
        }

        $outletName = trim((string) ($row->nama_outlet ?? $this->outletName ?? ''));
        if ($outletName === '' || strtolower($outletName) === 'null') {
            $outletName = '-';
        }

        return [
            (int) $row->sort_order + 1,
            $outletName,
            $row->author,
            $row->rating,
            $row->review_date,
            $row->text,
            $this->severityLabel($row->severity ?? null),
            $topicsStr,
            $row->summary_id,
        ];
    }

    protected function severityLabel(?string $s): string
    {
        return match ($s) {
            'positive' => 'Positif',
            'neutral' => 'Netral',
            'mild_negative' => 'Negatif ringan',
            'negative' => 'Negatif',
            'severe' => 'Sangat parah',
            default => $s ?? '',
        };
    }
}
