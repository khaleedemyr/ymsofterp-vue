<?php

namespace App\Exports\JustAcademy;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceRecapExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /** @var list<array{id: int, title: string}> */
    private array $quizColumns;

    public function __construct(
        private readonly Collection $sections,
        private readonly array $meta,
    ) {
        $seen = [];
        $this->quizColumns = [];
        foreach ($this->sections as $section) {
            foreach ($section['quizzes'] ?? [] as $quiz) {
                $id = (int) ($quiz['id'] ?? 0);
                if ($id === 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $this->quizColumns[] = [
                    'id' => $id,
                    'title' => (string) ($quiz['title'] ?? 'Test'),
                ];
            }
        }
    }

    public function title(): string
    {
        return 'Rekap Kehadiran';
    }

    public function array(): array
    {
        $quizHeadings = array_map(fn (array $quiz) => $quiz['title'], $this->quizColumns);

        $rows = [
            ['Rekap Kehadiran Training'],
            [
                'Month',
                $this->meta['month_label'] ?? '',
                'Department',
                $this->meta['department_label'] ?? 'Semua Departemen',
                'Training Plan',
                $this->meta['schedule_label'] ?? 'Semua Training Plan',
            ],
            [],
            array_merge([
                'Training Plan',
                'Tanggal',
                'Venue',
                'Trainer',
                'Method',
                'Ringkasan Hadir',
                'No',
                'Peserta',
                'Kehadiran',
                'Check-in',
                'Method Check-in',
            ], $quizHeadings),
        ];

        if ($this->sections->isEmpty()) {
            $empty = array_fill(0, 11 + count($this->quizColumns), '');
            $empty[0] = 'Tidak ada data training untuk filter ini.';
            $rows[] = $empty;

            return $rows;
        }

        foreach ($this->sections as $section) {
            $summary = $section['summary'] ?? [];
            $registered = (int) ($summary['registered'] ?? 0);
            $attendees = (int) ($summary['attendees'] ?? 0);
            $rate = $summary['attendance_rate'] ?? null;
            $hadirSummary = $registered > 0
                ? sprintf('%d/%d%s', $attendees, $registered, $rate !== null ? ' ('.$rate.'%)' : '')
                : '0/0';

            $participants = $section['participants'] ?? [];
            if ($participants === []) {
                $rows[] = array_merge([
                    $section['title'] ?? '',
                    $section['training_date'] ?? '',
                    $section['venue'] ?? '',
                    $section['trainer'] ?? '',
                    $section['method'] ?? '',
                    $hadirSummary,
                    '',
                    'Belum ada peserta',
                    '',
                    '',
                    '',
                ], array_fill(0, count($this->quizColumns), ''));

                continue;
            }

            $sectionQuizIds = collect($section['quizzes'] ?? [])
                ->map(fn ($quiz) => (int) ($quiz['id'] ?? 0))
                ->all();

            foreach (array_values($participants) as $idx => $row) {
                $quizResults = collect($row['quiz_results'] ?? [])->keyBy('quiz_id');
                $quizCells = [];
                foreach ($this->quizColumns as $quiz) {
                    $quizCells[] = in_array($quiz['id'], $sectionQuizIds, true)
                        ? $this->quizResultLabel($quizResults->get($quiz['id']))
                        : '';
                }

                $rows[] = array_merge([
                    $section['title'] ?? '',
                    $section['training_date'] ?? '',
                    $section['venue'] ?? '',
                    $section['trainer'] ?? '',
                    $section['method'] ?? '',
                    $hadirSummary,
                    $idx + 1,
                    $row['user_name'] ?? '',
                    ! empty($row['attended']) ? 'Hadir' : 'Tidak hadir',
                    $row['check_in_at'] ?? '',
                    $this->methodLabel($row['method'] ?? null),
                ], $quizCells);
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = Coordinate::stringFromColumnIndex(11 + count($this->quizColumns));
        $lastRow = max(4, $sheet->getHighestRow());

        $sheet->mergeCells('A1:'.$lastCol.'1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle('A2:F2')->applyFromArray([
            'font' => ['bold' => true],
        ]);

        $sheet->getStyle('A4:'.$lastCol.'4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $sheet->getStyle('A4:'.$lastCol.$lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        $sheet->getRowDimension(4)->setRowHeight(22);
        $sheet->freezePane('A5');

        return [];
    }

    private function methodLabel(mixed $method): string
    {
        if (! $method) {
            return '';
        }
        if ($method === 'qr') {
            return 'QR';
        }
        if ($method === 'manual') {
            return 'Manual';
        }

        return (string) $method;
    }

    private function quizResultLabel(mixed $result): string
    {
        if (! is_array($result) || ($result['status'] ?? 'not_started') === 'not_started') {
            return 'Belum mengerjakan';
        }
        if (($result['status'] ?? null) === 'in_progress') {
            return 'Sedang mengerjakan';
        }

        $score = $result['score'] ?? null;
        $scoreText = $score === null || $score === '' ? '—' : $score.'%';
        if (($result['passed'] ?? null) === true) {
            return $scoreText.' (Lulus)';
        }
        if (($result['passed'] ?? null) === false) {
            return $scoreText.' (Tidak lulus)';
        }

        return $scoreText;
    }
}
