<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerVoiceCasesExcelExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  Collection<int, object>  $rows
     */
    public function __construct(protected Collection $rows)
    {
    }

    public function title(): string
    {
        return 'Customer Voice';
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'No',
            'Event At',
            'Outlet',
            'Regional',
            'Source',
            'Author / Tamu',
            'Contact',
            'Email',
            'Follow-up Target',
            'Severity',
            'Complaint Types',
            'Summary',
            'Raw Text',
            'Risk Score',
            'CAPA Filled',
            'CAPA Verification',
            'Due At',
            'Status',
            'Assigned To',
            'Case ID',
        ];
    }

    public function map($row): array
    {
        $complaintTypes = is_array($row->complaint_type_labels ?? null)
            ? implode(', ', $row->complaint_type_labels)
            : '';

        $capaVerification = '-';
        if (is_array($row->capa_verification ?? null)) {
            $parts = [];
            foreach ($row->capa_verification as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $parts[] = $key.': '.($value ?? '-');
                }
            }
            if ($parts !== []) {
                $capaVerification = implode(' | ', $parts);
            }
        }

        $followUpTarget = '-';
        if (is_array($row->follow_up_target ?? null)) {
            $followUpTarget = implode(', ', array_filter(array_map(
                fn ($v) => is_scalar($v) ? (string) $v : null,
                $row->follow_up_target
            )));
        } elseif (is_scalar($row->follow_up_target ?? null)) {
            $followUpTarget = (string) $row->follow_up_target;
        }

        return [
            (int) ($row->export_no ?? 0),
            $row->event_at ?? '',
            $row->nama_outlet ?? '',
            $row->regional ?? '',
            $row->source_type ?? '',
            $row->author_name ?? '',
            $row->customer_contact ?? '',
            $row->customer_email ?? '',
            $followUpTarget,
            $row->severity ?? '',
            $complaintTypes,
            $row->summary_short ?? '',
            $row->raw_short ?? '',
            $row->risk_score ?? 0,
            ! empty($row->capa_filled) ? 'Yes' : 'No',
            $capaVerification,
            $row->due_at ?? '',
            $row->status ?? '',
            $row->assigned_to_name ?? '',
            $row->id ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:T1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        return [];
    }
}
