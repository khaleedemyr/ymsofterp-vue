<?php

namespace App\Http\Controllers;

use App\Models\ManualCogsDeviationCatcost;
use App\Models\ManualCogsDeviationCatcostItem;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManualCogsDeviationCatcostController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->get('month', '');
        $year = $request->get('year', '');

        $query = ManualCogsDeviationCatcost::query()
            ->with(['creator', 'items.outlet'])
            ->withCount('items')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id');

        if ($month !== '') {
            $query->where('month', (int) $month);
        }
        if ($year !== '') {
            $query->where('year', (int) $year);
        }

        $records = $query->paginate(15)->withQueryString();

        return Inertia::render('ManualCogsDeviationCatcost/Index', [
            'records' => $records,
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ManualCogsDeviationCatcost/Form', [
            'record' => null,
            'outlets' => $this->outletOptions(),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $exists = ManualCogsDeviationCatcost::where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => 'Data untuk periode bulan dan tahun ini sudah ada.',
            ]);
        }

        DB::beginTransaction();
        try {
            $record = ManualCogsDeviationCatcost::create([
                'month' => $validated['month'],
                'year' => $validated['year'],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncItems($record, $validated['items']);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('manual-cogs-deviation-catcost.index')
            ->with('success', 'Manual COGS, Deviation & Catcost berhasil disimpan.');
    }

    public function show(ManualCogsDeviationCatcost $manualCogsDeviationCatcost): Response
    {
        $manualCogsDeviationCatcost->load(['creator', 'items.outlet']);

        return Inertia::render('ManualCogsDeviationCatcost/Show', [
            'record' => $manualCogsDeviationCatcost,
            'monthLabel' => $this->monthLabel((int) $manualCogsDeviationCatcost->month),
        ]);
    }

    public function edit(ManualCogsDeviationCatcost $manualCogsDeviationCatcost): Response
    {
        $manualCogsDeviationCatcost->load(['items.outlet']);

        return Inertia::render('ManualCogsDeviationCatcost/Form', [
            'record' => $manualCogsDeviationCatcost,
            'outlets' => $this->outletOptions(),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    public function update(Request $request, ManualCogsDeviationCatcost $manualCogsDeviationCatcost)
    {
        $validated = $this->validatePayload($request);

        $exists = ManualCogsDeviationCatcost::where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->where('id', '!=', $manualCogsDeviationCatcost->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => 'Data untuk periode bulan dan tahun ini sudah ada.',
            ]);
        }

        DB::beginTransaction();
        try {
            $manualCogsDeviationCatcost->update([
                'month' => $validated['month'],
                'year' => $validated['year'],
                'updated_by' => auth()->id(),
            ]);

            $manualCogsDeviationCatcost->items()->delete();
            $this->syncItems($manualCogsDeviationCatcost, $validated['items']);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('manual-cogs-deviation-catcost.index')
            ->with('success', 'Manual COGS, Deviation & Catcost berhasil diperbarui.');
    }

    public function destroy(ManualCogsDeviationCatcost $manualCogsDeviationCatcost)
    {
        $manualCogsDeviationCatcost->delete();

        return redirect()
            ->route('manual-cogs-deviation-catcost.index')
            ->with('success', 'Data berhasil dihapus.');
    }

    public function downloadTemplate(): StreamedResponse
    {
        $outlets = Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']);

        $spreadsheet = new Spreadsheet();

        $instructionSheet = $spreadsheet->getActiveSheet();
        $instructionSheet->setTitle('Instruction');
        $instructionSheet->fromArray([
            ['Manual COGS, Deviation & Catcost - Upload Template'],
            [''],
            ['Cara pakai'],
            ['1. Pilih Bulan dan Tahun di form web terlebih dahulu.'],
            ['2. Isi sheet "Template_Data" (kolom A-H).'],
            ['3. outlet_id wajib diisi (lihat sheet Master_Outlets).'],
            ['4. Kolom nilai dan persen boleh dikosongkan (default 0).'],
            ['5. Upload file Excel dari form — data outlet di tabel akan diganti dengan isi file.'],
            ['6. Outlet tidak boleh duplikat dalam satu file.'],
            [''],
            ['Kolom Template_Data:'],
            ['A outlet_id (wajib)'],
            ['B outlet_name (opsional, referensi)'],
            ['C nilai_cogs'],
            ['D persen_cogs'],
            ['E nilai_deviation'],
            ['F persen_deviation'],
            ['G nilai_catcost'],
            ['H persen_catcost'],
        ]);
        $instructionSheet->mergeCells('A1:H1');
        $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $instructionSheet->getStyle('A3')->getFont()->setBold(true);
        $instructionSheet->getColumnDimension('A')->setWidth(80);
        $instructionSheet->getStyle('A1:H20')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $instructionSheet->getStyle('A1:H20')->getAlignment()->setWrapText(true);

        $masterSheet = $spreadsheet->createSheet();
        $masterSheet->setTitle('Master_Outlets');
        $masterSheet->fromArray([['outlet_id', 'outlet_name']], null, 'A1');
        $masterSheet->getStyle('A1:B1')->getFont()->setBold(true);
        $masterSheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
        $rowMaster = 2;
        foreach ($outlets as $outlet) {
            $masterSheet->setCellValue("A{$rowMaster}", (int) $outlet->id_outlet);
            $masterSheet->setCellValue("B{$rowMaster}", (string) $outlet->nama_outlet);
            $rowMaster++;
        }
        $masterSheet->getColumnDimension('A')->setWidth(12);
        $masterSheet->getColumnDimension('B')->setWidth(45);

        $dataSheet = $spreadsheet->createSheet();
        $dataSheet->setTitle('Template_Data');
        $dataSheet->fromArray([
            [
                'outlet_id',
                'outlet_name',
                'nilai_cogs',
                'persen_cogs',
                'nilai_deviation',
                'persen_deviation',
                'nilai_catcost',
                'persen_catcost',
            ],
        ], null, 'A1');
        $dataSheet->getStyle('A1:H1')->getFont()->setBold(true);
        $dataSheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE');
        foreach (range('A', 'H') as $col) {
            $dataSheet->getColumnDimension($col)->setWidth($col === 'B' ? 35 : 16);
        }

        $row = 2;
        foreach ($outlets as $outlet) {
            $dataSheet->setCellValue("A{$row}", (int) $outlet->id_outlet);
            $dataSheet->setCellValue("B{$row}", (string) $outlet->nama_outlet);
            $dataSheet->setCellValue("C{$row}", 0);
            $dataSheet->setCellValue("D{$row}", 0);
            $dataSheet->setCellValue("E{$row}", 0);
            $dataSheet->setCellValue("F{$row}", 0);
            $dataSheet->setCellValue("G{$row}", 0);
            $dataSheet->setCellValue("H{$row}", 0);
            $row++;
        }

        $fileName = 'manual_cogs_deviation_catcost_template_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName);
    }

    public function importFromExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $sheet = IOFactory::load($request->file('file')->getRealPath())->getSheetByName('Template_Data');
        if (!$sheet) {
            return response()->json([
                'success' => false,
                'message' => 'Sheet "Template_Data" tidak ditemukan di file Excel.',
            ], 422);
        }

        $rows = $sheet->toArray(null, true, true, true);
        if (count($rows) <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Sheet "Template_Data" kosong. Isi minimal 1 baris data.',
            ], 422);
        }

        $outletsById = Outlet::where('status', 'A')
            ->get(['id_outlet', 'nama_outlet'])
            ->keyBy('id_outlet');
        $outletsByName = $outletsById->mapWithKeys(fn ($o) => [mb_strtolower(trim($o->nama_outlet)) => $o]);

        $errors = [];
        $items = [];
        $usedOutletIds = [];

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber === 1) {
                continue;
            }

            $outletIdRaw = trim((string) ($row['A'] ?? ''));
            $outletNameRaw = trim((string) ($row['B'] ?? ''));
            $cogsValueRaw = trim((string) ($row['C'] ?? ''));
            $cogsPercentRaw = trim((string) ($row['D'] ?? ''));
            $deviationValueRaw = trim((string) ($row['E'] ?? ''));
            $deviationPercentRaw = trim((string) ($row['F'] ?? ''));
            $catcostValueRaw = trim((string) ($row['G'] ?? ''));
            $catcostPercentRaw = trim((string) ($row['H'] ?? ''));

            if ($outletIdRaw === '' && $outletNameRaw === '' && $cogsValueRaw === '' && $cogsPercentRaw === ''
                && $deviationValueRaw === '' && $deviationPercentRaw === '' && $catcostValueRaw === '' && $catcostPercentRaw === '') {
                continue;
            }

            $outlet = null;
            if ($outletIdRaw !== '' && is_numeric($outletIdRaw)) {
                $outlet = $outletsById->get((int) $outletIdRaw);
            }
            if (!$outlet && $outletNameRaw !== '') {
                $outlet = $outletsByName->get(mb_strtolower($outletNameRaw));
            }

            if (!$outlet) {
                $errors[] = "Baris {$rowNumber}: outlet tidak valid (id={$outletIdRaw}, nama={$outletNameRaw}).";
                continue;
            }

            $outletId = (int) $outlet->id_outlet;
            if (in_array($outletId, $usedOutletIds, true)) {
                $errors[] = "Baris {$rowNumber}: outlet {$outlet->nama_outlet} duplikat.";
                continue;
            }
            $usedOutletIds[] = $outletId;

            $items[] = [
                'outlet_id' => $outletId,
                'cogs_value' => $this->parseImportNumber($cogsValueRaw),
                'cogs_percent' => $this->parseImportNumber($cogsPercentRaw),
                'deviation_value' => $this->parseImportNumber($deviationValueRaw),
                'deviation_percent' => $this->parseImportNumber($deviationPercentRaw),
                'catcost_value' => $this->parseImportNumber($catcostValueRaw),
                'catcost_percent' => $this->parseImportNumber($catcostPercentRaw),
            ];
        }

        if ($items === []) {
            return response()->json([
                'success' => false,
                'message' => $errors[0] ?? 'Tidak ada data outlet yang valid di file Excel.',
                'errors' => $errors,
            ], 422);
        }

        if ($errors !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Import gagal. Perbaiki error berikut.',
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => count($items) . ' outlet berhasil diimport ke form.',
            'items' => $items,
        ]);
    }

    private function parseImportNumber(string $value): float
    {
        $value = trim(str_replace([',', ' '], ['', ''], $value));
        if ($value === '' || !is_numeric($value)) {
            return 0.0;
        }

        return (float) $value;
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'items' => 'required|array|min:1',
            'items.*.outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'items.*.cogs_value' => 'nullable|numeric',
            'items.*.cogs_percent' => 'nullable|numeric',
            'items.*.deviation_value' => 'nullable|numeric',
            'items.*.deviation_percent' => 'nullable|numeric',
            'items.*.catcost_value' => 'nullable|numeric',
            'items.*.catcost_percent' => 'nullable|numeric',
        ], [
            'items.required' => 'Minimal satu outlet harus diisi.',
            'items.min' => 'Minimal satu outlet harus diisi.',
        ]);

        $outletIds = collect($validated['items'])->pluck('outlet_id')->map(fn ($id) => (int) $id);
        if ($outletIds->unique()->count() !== $outletIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'Outlet tidak boleh duplikat dalam satu periode.',
            ]);
        }

        return $validated;
    }

    private function syncItems(ManualCogsDeviationCatcost $record, array $items): void
    {
        foreach ($items as $item) {
            ManualCogsDeviationCatcostItem::create([
                'manual_cogs_deviation_catcost_id' => $record->id,
                'outlet_id' => (int) $item['outlet_id'],
                'cogs_value' => $item['cogs_value'] ?? 0,
                'cogs_percent' => $item['cogs_percent'] ?? 0,
                'deviation_value' => $item['deviation_value'] ?? 0,
                'deviation_percent' => $item['deviation_percent'] ?? 0,
                'catcost_value' => $item['catcost_value'] ?? 0,
                'catcost_percent' => $item['catcost_percent'] ?? 0,
            ]);
        }
    }

    private function outletOptions(): array
    {
        return Outlet::where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet'])
            ->toArray();
    }

    private function monthOptions(): array
    {
        return collect(range(1, 12))->map(fn ($m) => [
            'value' => $m,
            'label' => $this->monthLabel($m),
        ])->all();
    }

    private function yearOptions(): array
    {
        $current = (int) date('Y');

        return collect(range($current - 2, $current + 1))->map(fn ($y) => [
            'value' => $y,
            'label' => (string) $y,
        ])->reverse()->values()->all();
    }

    private function monthLabel(int $month): string
    {
        $labels = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $labels[$month] ?? (string) $month;
    }
}
