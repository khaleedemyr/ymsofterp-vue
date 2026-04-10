<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketAttachment;
use App\Models\TicketAssignment;
use App\Models\PurchaseRequisition;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Outlet;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TicketController extends Controller
{
    /**
     * @return array{categories: \Illuminate\Support\Collection, priorities: \Illuminate\Support\Collection, divisions: \Illuminate\Support\Collection, outlets: \Illuminate\Support\Collection, issueTypes: array<int, array{excel_value: string, label: string, notes: string}>}
     */
    private function ticketImportMasterPayload(): array
    {
        $categories = TicketCategory::active()->orderBy('name')->get(['id', 'name', 'description']);
        $priorities = TicketPriority::active()->orderBy('level')->orderBy('name')->get(['id', 'name', 'description', 'max_days', 'level']);
        $divisions = Divisi::active()->orderBy('nama_divisi')->get(['id', 'nama_divisi']);
        $outlets = Outlet::active()->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet', 'lokasi', 'keterangan']);

        $issueTypes = [
            [
                'excel_value' => 'Defect',
                'label' => 'Defect',
                'notes' => 'Ketik persis: Defect (huruf besar D, sisanya kecil).',
            ],
            [
                'excel_value' => 'Ops Issue',
                'label' => 'Ops Issue',
                'notes' => 'Ketik persis: Ops Issue — ada spasi, huruf O dan I kapital.',
            ],
        ];

        return compact('categories', 'priorities', 'divisions', 'outlets', 'issueTypes');
    }

    /**
     * @return array<int, object>
     */
    private function ticketImportReferenceExcelSheets(): array
    {
        $m = $this->ticketImportMasterPayload();

        $categoryRows = $m['categories']->map(fn ($c) => [
            $c->name,
            $c->description ?? '',
        ])->all();

        $priorityRows = $m['priorities']->map(fn ($p) => [
            $p->name,
            $p->description ?? '',
            (string) ($p->max_days ?? ''),
            (string) ($p->level ?? ''),
        ])->all();

        $divisionRows = $m['divisions']->map(fn ($d) => [
            $d->nama_divisi,
        ])->all();

        $outletRows = $m['outlets']->map(fn ($o) => [
            $o->nama_outlet,
            $o->lokasi ?? '',
            $o->keterangan ?? '',
        ])->all();

        $issueRows = collect($m['issueTypes'])->map(fn ($it) => [
            $it['excel_value'],
            $it['notes'],
        ])->all();

        return [
            $this->makeTicketImportReferenceSheet(
                'Ref Category',
                ['nilai_category (salin persis)', 'deskripsi'],
                $categoryRows
            ),
            $this->makeTicketImportReferenceSheet(
                'Ref Priority',
                ['nilai_priority (salin persis)', 'deskripsi', 'max_days', 'level'],
                $priorityRows
            ),
            $this->makeTicketImportReferenceSheet(
                'Ref Division',
                ['nilai_division (salin persis)'],
                $divisionRows
            ),
            $this->makeTicketImportReferenceSheet(
                'Ref Outlet',
                ['nilai_outlet (salin persis)', 'lokasi', 'keterangan'],
                $outletRows
            ),
            $this->makeTicketImportReferenceSheet(
                'Issue Type',
                ['nilai_issue_type (salin persis)', 'cara mengetik'],
                $issueRows
            ),
        ];
    }

    private function makeTicketImportReferenceSheet(string $title, array $headings, array $rows): object
    {
        return new class($title, $headings, $rows) implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents {
            public function __construct(
                private string $sheetTitle,
                private array $headingsArr,
                private array $dataRows,
            ) {}

            public function title(): string
            {
                return $this->sheetTitle;
            }

            public function headings(): array
            {
                return $this->headingsArr;
            }

            public function array(): array
            {
                return $this->dataRows;
            }

            public function registerEvents(): array
            {
                $headingsCount = count($this->headingsArr);

                return [
                    AfterSheet::class => function (AfterSheet $event) use ($headingsCount) {
                        $sheet = $event->sheet;
                        $sheet->freezePane('A2');
                        $lastCol = Coordinate::stringFromColumnIndex(max(1, $headingsCount));
                        $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'color' => ['rgb' => 'FFFFFF'],
                            ],
                            'fill' => [
                                'fillType' => 'solid',
                                'color' => ['rgb' => '059669'],
                            ],
                            'alignment' => [
                                'horizontal' => 'center',
                                'vertical' => 'center',
                            ],
                        ]);
                    },
                ];
            }
        };
    }

    private function buildTicketImportTemplateDataSheet(): object
    {
        return new class implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents {
            public function title(): string
            {
                return 'Template Data';
            }

            public function headings(): array
            {
                return [
                    'title',
                    'description',
                    'category',
                    'priority',
                    'division',
                    'outlet',
                    'due_date',
                    'issue_type',
                ];
            }

            public function array(): array
            {
                return [
                    [
                        'Mesin Kasir Error',
                        'Mesin kasir tidak bisa print struk saat jam sibuk',
                        'IT Support',
                        'HIGH',
                        'IT',
                        'Outlet A',
                        '2026-04-10',
                        'Defect',
                    ],
                    [
                        'Stok Bahan Baku Minus',
                        'Selisih stok tepung dan gula, perlu cek gudang',
                        'Operasional',
                        'MEDIUM',
                        'OPERASIONAL',
                        'Outlet B',
                        '',
                        'Ops Issue',
                    ],
                ];
            }

            public function registerEvents(): array
            {
                return [
                    AfterSheet::class => function (AfterSheet $event) {
                        $sheet = $event->sheet;
                        $sheet->freezePane('A2');

                        $sheet->getStyle('A1:H1')->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'color' => ['rgb' => 'FFFFFF'],
                            ],
                            'fill' => [
                                'fillType' => 'solid',
                                'color' => ['rgb' => '2563EB'],
                            ],
                            'alignment' => [
                                'horizontal' => 'center',
                                'vertical' => 'center',
                            ],
                        ]);

                        $sheet->getStyle('A2:H3')->applyFromArray([
                            'fill' => [
                                'fillType' => 'solid',
                                'color' => ['rgb' => 'EFF6FF'],
                            ],
                        ]);

                        $sheet->getStyle('A1:H3')->applyFromArray([
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => 'thin',
                                    'color' => ['rgb' => 'D1D5DB'],
                                ],
                            ],
                        ]);

                        $sheet->setCellValue('A5', 'Catatan:');
                        $sheet->setCellValue('A6', '- Silakan hapus baris contoh (baris 2-3) sebelum import data final.');
                        $sheet->setCellValue('A7', '- Kolom wajib: title, description, category, priority, division, outlet.');
                        $sheet->setCellValue('A8', '- due_date opsional (format YYYY-MM-DD), issue_type opsional — lihat sheet "Issue Type".');
                        $sheet->setCellValue('A9', '- Nilai category/priority/division/outlet harus sama persis dengan sheet "Ref …" di file workbook ini.');
                        $sheet->mergeCells('A5:H5');
                        $sheet->mergeCells('A6:H6');
                        $sheet->mergeCells('A7:H7');
                        $sheet->mergeCells('A8:H8');
                        $sheet->mergeCells('A9:H9');
                        $sheet->getStyle('A5:A9')->getFont()->setBold(true);
                        $sheet->getStyle('A5:H9')->getAlignment()->setWrapText(true);
                    },
                ];
            }
        };
    }

    private function buildTicketImportGuideSheet(): object
    {
        return new class implements FromArray, WithTitle, ShouldAutoSize, WithEvents {
            public function title(): string
            {
                return 'Panduan';
            }

            public function array(): array
            {
                return [
                    ['PANDUAN IMPORT TICKET DARI EXCEL'],
                    [''],
                    ['1. Langkah Umum'],
                    ['1) Download template dari tombol "Download Template".'],
                    ['2) Isi data di sheet "Template Data".'],
                    ['3) Pastikan nama category, priority, division, dan outlet sama persis dengan master (sheet Ref … di workbook template ini).'],
                    ['4) Upload file lewat tombol "Import Excel" di menu Ticket.'],
                    [''],
                    ['2. Format Kolom'],
                    ['title', 'Wajib', 'Text singkat judul ticket'],
                    ['description', 'Wajib', 'Deskripsi detail issue'],
                    ['category', 'Wajib', 'Salin dari sheet Ref Category — persis termasuk huruf besar/kecil'],
                    ['priority', 'Wajib', 'Salin dari sheet Ref Priority (kolom nilai_priority)'],
                    ['division', 'Wajib', 'Salin dari sheet Ref Division'],
                    ['outlet', 'Wajib', 'Salin dari sheet Ref Outlet (kolom nilai_outlet)'],
                    ['due_date', 'Opsional', 'Format: YYYY-MM-DD (contoh: 2026-04-10)'],
                    ['issue_type', 'Opsional', 'Salin dari sheet Issue Type — hanya nilai yang terdaftar'],
                    [''],
                    ['3. Tips Agar Import Sukses'],
                    ['- Jangan ubah nama header kolom di baris pertama sheet "Template Data".'],
                    ['- Hapus baris contoh sebelum import final.'],
                    ['- Gunakan sheet Ref … untuk copy-paste supaya tidak typo.'],
                    ['- Jika ada error validasi, perbaiki baris yang disebutkan lalu import ulang.'],
                ];
            }

            public function registerEvents(): array
            {
                return [
                    AfterSheet::class => function (AfterSheet $event) {
                        $sheet = $event->sheet;
                        $sheet->mergeCells('A1:C1');
                        $sheet->getStyle('A1:C1')->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'size' => 14,
                                'color' => ['rgb' => '1D4ED8'],
                            ],
                        ]);
                        $sheet->getStyle('A1:C40')->getAlignment()->setWrapText(true);
                        $sheet->getStyle('A10:C17')->applyFromArray([
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => 'thin',
                                    'color' => ['rgb' => 'D1D5DB'],
                                ],
                            ],
                        ]);
                        $sheet->getStyle('A10:C10')->getFont()->setBold(true);
                        $sheet->getStyle('A10:C10')->applyFromArray([
                            'fill' => [
                                'fillType' => 'solid',
                                'color' => ['rgb' => 'DBEAFE'],
                            ],
                        ]);
                    },
                ];
            }
        };
    }

    public function downloadImportTemplate()
    {
        $sheets = array_merge(
            [
                $this->buildTicketImportTemplateDataSheet(),
                $this->buildTicketImportGuideSheet(),
            ],
            $this->ticketImportReferenceExcelSheets()
        );

        $export = new class($sheets) implements WithMultipleSheets {
            public function __construct(private array $allSheets) {}

            public function sheets(): array
            {
                return $this->allSheets;
            }
        };

        return Excel::download($export, 'ticket_import_template.xlsx');
    }

    public function importFromExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $statusOpen = TicketStatus::where('slug', 'open')->first() ?? TicketStatus::active()->first() ?? TicketStatus::first();
        if (!$statusOpen) {
            return response()->json([
                'success' => false,
                'message' => 'Status ticket tidak ditemukan. Mohon cek data master ticket status.',
            ], 422);
        }

        $filePath = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'File import kosong. Mohon isi data minimal 1 baris.',
            ], 422);
        }

        $headerRow = array_shift($rows);
        $headers = [];
        foreach ($headerRow as $column => $value) {
            $headers[$column] = strtolower(trim((string) $value));
        }

        $requiredHeaders = ['title', 'description', 'category', 'priority', 'division', 'outlet'];
        $headerMap = [];
        foreach ($requiredHeaders as $requiredHeader) {
            $column = array_search($requiredHeader, $headers, true);
            if ($column === false) {
                return response()->json([
                    'success' => false,
                    'message' => "Header '{$requiredHeader}' tidak ditemukan di template import.",
                ], 422);
            }
            $headerMap[$requiredHeader] = $column;
        }

        $optionalHeaders = ['due_date', 'issue_type'];
        foreach ($optionalHeaders as $optionalHeader) {
            $column = array_search($optionalHeader, $headers, true);
            if ($column !== false) {
                $headerMap[$optionalHeader] = $column;
            }
        }

        $categories = TicketCategory::active()->get()->keyBy(fn($item) => strtolower(trim($item->name)));
        $priorities = TicketPriority::active()->get()->keyBy(fn($item) => strtolower(trim($item->name)));
        $divisions = Divisi::active()->get()->keyBy(fn($item) => strtolower(trim($item->nama_divisi)));
        $outlets = Outlet::active()->get()->keyBy(fn($item) => strtolower(trim($item->nama_outlet)));

        $errors = [];
        $createdCount = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $rowIndex => $row) {
                $excelRowNumber = $rowIndex + 2; // +1 header, +1 array index
                $title = trim((string) ($row[$headerMap['title']] ?? ''));
                $description = trim((string) ($row[$headerMap['description']] ?? ''));
                $categoryName = strtolower(trim((string) ($row[$headerMap['category']] ?? '')));
                $priorityName = strtolower(trim((string) ($row[$headerMap['priority']] ?? '')));
                $divisionName = strtolower(trim((string) ($row[$headerMap['division']] ?? '')));
                $outletName = strtolower(trim((string) ($row[$headerMap['outlet']] ?? '')));
                $dueDateRaw = isset($headerMap['due_date']) ? trim((string) ($row[$headerMap['due_date']] ?? '')) : '';

                if ($title === '' && $description === '' && $categoryName === '' && $priorityName === '' && $divisionName === '' && $outletName === '') {
                    continue;
                }

                $rowErrors = [];
                if ($title === '') $rowErrors[] = 'title wajib diisi';
                if ($description === '') $rowErrors[] = 'description wajib diisi';
                if ($categoryName === '' || !$categories->has($categoryName)) $rowErrors[] = 'category tidak valid';
                if ($priorityName === '' || !$priorities->has($priorityName)) $rowErrors[] = 'priority tidak valid';
                if ($divisionName === '' || !$divisions->has($divisionName)) $rowErrors[] = 'division tidak valid';
                if ($outletName === '' || !$outlets->has($outletName)) $rowErrors[] = 'outlet tidak valid';

                $dueDate = null;
                if ($dueDateRaw !== '') {
                    try {
                        $dueDate = Carbon::parse($dueDateRaw);
                    } catch (\Throwable $th) {
                        $rowErrors[] = 'due_date tidak valid (gunakan format YYYY-MM-DD)';
                    }
                }

                if (!empty($rowErrors)) {
                    $errors[] = "Baris {$excelRowNumber}: " . implode(', ', $rowErrors);
                    continue;
                }

                $category = $categories->get($categoryName);
                $priority = $priorities->get($priorityName);
                $division = $divisions->get($divisionName);
                $outlet = $outlets->get($outletName);

                $ticket = Ticket::create([
                    'ticket_number' => Ticket::generateTicketNumber(),
                    'title' => $title,
                    'description' => $description,
                    'category_id' => $category->id,
                    'priority_id' => $priority->id,
                    'status_id' => $statusOpen->id,
                    'divisi_id' => $division->id,
                    'outlet_id' => $outlet->id_outlet,
                    'created_by' => auth()->id(),
                    'due_date' => $dueDate ? $dueDate : now()->addDays($priority->max_days ?? 7),
                    'source' => 'manual',
                ]);

                // Keep ticket history consistent with create/manual flows.
                $this->createTicketHistory(
                    $ticket,
                    'created',
                    null,
                    null,
                    'Ticket created via Excel import'
                );

                $createdCount++;
            }

            if ($createdCount === 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data yang berhasil diimport.',
                    'errors' => $errors,
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Import berhasil. {$createdCount} ticket ditambahkan.",
                'created_count' => $createdCount,
                'errors' => $errors,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal import ticket: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a listing of tickets
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', 'all');
        $priority = $request->get('priority', 'all');
        $category = $request->get('category', 'all');
        $division = $request->get('division', 'all');
        $outlet = $request->get('outlet', 'all');
        $paymentStatus = $request->get('payment_status', 'all');
        $perPage = $request->get('per_page', 15);

        $query = Ticket::with([
            'category',
            'priority', 
            'status',
            'divisi',
            'outlet',
            'creator',
            'assignedUsers:id,nama_lengkap,avatar'
        ])->withCount('comments');

        // Apply filters
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status !== 'all') {
            $query->whereHas('status', function($q) use ($status) {
                $q->where('slug', $status);
            });
        }

        if ($priority !== 'all') {
            $query->where('priority_id', $priority);
        }

        if ($category !== 'all') {
            $query->where('category_id', $category);
        }

        if ($division !== 'all') {
            $query->where('divisi_id', $division);
        }

        if ($outlet !== 'all') {
            $query->where('outlet_id', $outlet);
        }

        if ($paymentStatus !== 'all') {
            $paidCondition = function ($prQuery) {
                $prQuery->where(function ($q) {
                    $q->whereRaw("UPPER(status) = 'PAID'")
                        ->orWhereExists(function ($subQuery) {
                            $subQuery->select(DB::raw(1))
                                ->from('non_food_payments as nfp')
                                ->whereColumn('nfp.purchase_requisition_id', 'purchase_requisitions.id')
                                ->whereIn('nfp.status', ['paid', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled');
                        });
                });
            };

            $hasAnyPaymentCondition = function ($prQuery) {
                $prQuery->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('non_food_payments as nfp')
                        ->whereColumn('nfp.purchase_requisition_id', 'purchase_requisitions.id')
                        ->where('nfp.status', '!=', 'cancelled');
                });
            };

            if ($paymentStatus === 'paid') {
                $query->whereHas('purchaseRequisitions', $paidCondition);
            } elseif ($paymentStatus === 'on_process') {
                $query->whereDoesntHave('purchaseRequisitions', $paidCondition)
                    ->whereHas('purchaseRequisitions', $hasAnyPaymentCondition);
            } elseif ($paymentStatus === 'no_pr') {
                $query->whereDoesntHave('purchaseRequisitions');
            } elseif ($paymentStatus === 'with_pr') {
                $query->whereHas('purchaseRequisitions');
            }
        }

        $tickets = $query->orderBy('created_at', 'desc')
                        ->paginate($perPage)
                        ->withQueryString();

        $ticketIds = $tickets->getCollection()->pluck('id')->toArray();
        $prsByTicket = collect();
        $paymentStatsByPr = collect();

        if (!empty($ticketIds)) {
            $prs = PurchaseRequisition::whereIn('ticket_id', $ticketIds)
                ->select('id', 'ticket_id', 'pr_number', 'status', 'mode', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();

            $prsByTicket = $prs->groupBy('ticket_id');

            $prIds = $prs->pluck('id')->toArray();
            if (!empty($prIds)) {
                $paymentStatsByPr = DB::table('non_food_payments')
                    ->whereIn('purchase_requisition_id', $prIds)
                    ->select(
                        'purchase_requisition_id as pr_id',
                        DB::raw('COUNT(*) as total_payments'),
                        DB::raw("SUM(CASE WHEN status IN ('paid', 'approved') AND status != 'cancelled' THEN 1 ELSE 0 END) as paid_payments")
                    )
                    ->groupBy('purchase_requisition_id')
                    ->get()
                    ->keyBy('pr_id');
            }
        }

        $tickets->getCollection()->transform(function ($ticket) use ($prsByTicket, $paymentStatsByPr) {
            $relatedPrs = collect($prsByTicket->get($ticket->id, collect()));

            $prSummaries = $relatedPrs->map(function ($pr) use ($paymentStatsByPr) {
                $paymentStat = $paymentStatsByPr->get($pr->id);
                $totalPayments = (int) ($paymentStat->total_payments ?? 0);
                $paidPayments = (int) ($paymentStat->paid_payments ?? 0);

                $paymentStatus = 'NO_PAYMENT';
                if (strtoupper((string) $pr->status) === 'PAID' || $paidPayments > 0) {
                    $paymentStatus = 'PAID';
                } elseif ($totalPayments > 0) {
                    $paymentStatus = 'ON_PROCESS';
                }

                return [
                    'id' => $pr->id,
                    'pr_number' => $pr->pr_number,
                    'mode' => $pr->mode,
                    'status' => $pr->status,
                    'payment_status' => $paymentStatus,
                    'total_payments' => $totalPayments,
                    'paid_payments' => $paidPayments,
                ];
            })->values();

            $ticket->payment_info = [
                'total_pr' => $prSummaries->count(),
                'total_paid_pr' => $prSummaries->where('payment_status', 'PAID')->count(),
                'total_processing_pr' => $prSummaries->where('payment_status', 'ON_PROCESS')->count(),
                'prs' => $prSummaries,
            ];

            return $ticket;
        });

        // Get filter options
        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        $statuses = TicketStatus::active()->ordered()->get();
        
        // Get only divisions that have tickets
        $divisis = Divisi::whereHas('tickets')->active()->orderBy('nama_divisi')->get();
        
        $outlets = Outlet::active()->orderBy('nama_outlet')->get();
        $assignableUsers = User::where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'avatar', 'division_id']);

        // Statistics
        $statistics = [
            'total' => Ticket::count(),
            'open' => Ticket::open()->count(),
            'in_progress' => Ticket::inProgress()->count(),
            'resolved' => Ticket::resolved()->count(),
            'closed' => Ticket::closed()->count(),
        ];

        return Inertia::render('Tickets/Index', [
            'data' => $tickets,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'priority' => $priority,
                'category' => $category,
                'division' => $division,
                'outlet' => $outlet,
                'payment_status' => $paymentStatus,
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'categories' => $categories,
                'priorities' => $priorities,
                'statuses' => $statuses,
                'divisions' => $divisis,
                'outlets' => $outlets,
            ],
            'statistics' => $statistics,
            'assignableUsers' => $assignableUsers,
        ]);
    }

    /**
     * Download ticket report (styled XLSX) based on active filters.
     */
    public function downloadReport(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', 'all');
        $priority = $request->get('priority', 'all');
        $category = $request->get('category', 'all');
        $division = $request->get('division', 'all');
        $outlet = $request->get('outlet', 'all');
        $paymentStatus = $request->get('payment_status', 'all');

        $query = Ticket::with([
            'category',
            'priority',
            'status',
            'divisi',
            'outlet',
            'creator',
            'assignedUsers:id,nama_lengkap',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($status !== 'all') {
            $query->whereHas('status', function ($q) use ($status) {
                $q->where('slug', $status);
            });
        }
        if ($priority !== 'all') {
            $query->where('priority_id', $priority);
        }
        if ($category !== 'all') {
            $query->where('category_id', $category);
        }
        if ($division !== 'all') {
            $query->where('divisi_id', $division);
        }
        if ($outlet !== 'all') {
            $query->where('outlet_id', $outlet);
        }

        if ($paymentStatus !== 'all') {
            $paidCondition = function ($prQuery) {
                $prQuery->where(function ($q) {
                    $q->whereRaw("UPPER(status) = 'PAID'")
                        ->orWhereExists(function ($subQuery) {
                            $subQuery->select(DB::raw(1))
                                ->from('non_food_payments as nfp')
                                ->whereColumn('nfp.purchase_requisition_id', 'purchase_requisitions.id')
                                ->whereIn('nfp.status', ['paid', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled');
                        });
                });
            };
            $hasAnyPaymentCondition = function ($prQuery) {
                $prQuery->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('non_food_payments as nfp')
                        ->whereColumn('nfp.purchase_requisition_id', 'purchase_requisitions.id')
                        ->where('nfp.status', '!=', 'cancelled');
                });
            };

            if ($paymentStatus === 'paid') {
                $query->whereHas('purchaseRequisitions', $paidCondition);
            } elseif ($paymentStatus === 'on_process') {
                $query->whereDoesntHave('purchaseRequisitions', $paidCondition)
                    ->whereHas('purchaseRequisitions', $hasAnyPaymentCondition);
            } elseif ($paymentStatus === 'no_pr') {
                $query->whereDoesntHave('purchaseRequisitions');
            } elseif ($paymentStatus === 'with_pr') {
                $query->whereHas('purchaseRequisitions');
            }
        }

        $tickets = $query->orderBy('created_at', 'desc')->get();

        $ticketIds = $tickets->pluck('id')->toArray();
        $prsByTicket = collect();
        $paymentStatsByPr = collect();

        if (!empty($ticketIds)) {
            $prs = PurchaseRequisition::whereIn('ticket_id', $ticketIds)
                ->select('id', 'ticket_id', 'pr_number', 'status', 'mode')
                ->orderBy('created_at', 'desc')
                ->get();
            $prsByTicket = $prs->groupBy('ticket_id');

            $prIds = $prs->pluck('id')->toArray();
            if (!empty($prIds)) {
                $paymentStatsByPr = DB::table('non_food_payments')
                    ->whereIn('purchase_requisition_id', $prIds)
                    ->select(
                        'purchase_requisition_id as pr_id',
                        DB::raw('COUNT(*) as total_payments'),
                        DB::raw("SUM(CASE WHEN status IN ('paid', 'approved') AND status != 'cancelled' THEN 1 ELSE 0 END) as paid_payments")
                    )
                    ->groupBy('purchase_requisition_id')
                    ->get()
                    ->keyBy('pr_id');
            }
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ticket Report');

        $headers = [
            'Ticket Number',
            'Issue Type',
            'Title',
            'Category',
            'Priority',
            'Status',
            'Division',
            'Outlet',
            'Creator',
            'Team',
            'Created At',
            'Due Date',
            'Selesai (Closed At)',
            'Total PR',
            'Paid PR',
            'Processing PR',
        ];

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'Ticket Report');
        $sheet->setCellValue('A2', 'Generated: ' . now()->format('d M Y H:i:s'));

        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($col, 3, $h);
            $col++;
        }

        $row = 4;
        foreach ($tickets as $ticket) {
            $issueMeta = $this->ticketCalendarIssueMeta($ticket);
            $relatedPrs = collect($prsByTicket->get($ticket->id, collect()));
            $prSummaries = $relatedPrs->map(function ($pr) use ($paymentStatsByPr) {
                $paymentStat = $paymentStatsByPr->get($pr->id);
                $totalPayments = (int) ($paymentStat->total_payments ?? 0);
                $paidPayments = (int) ($paymentStat->paid_payments ?? 0);
                if (strtoupper((string) $pr->status) === 'PAID' || $paidPayments > 0) {
                    return 'PAID';
                }
                if ($totalPayments > 0) {
                    return 'ON_PROCESS';
                }
                return 'NO_PAYMENT';
            });

            $teamNames = $ticket->assignedUsers
                ->pluck('nama_lengkap')
                ->filter()
                ->implode(', ');

            $statusSlug = $ticket->status?->slug;
            $selesaiAt = ($statusSlug === 'closed' && $ticket->closed_at)
                ? $ticket->closed_at->format('Y-m-d H:i:s')
                : '';

            $values = [
                $ticket->ticket_number,
                $issueMeta['label'],
                $ticket->title,
                $ticket->category?->name,
                $ticket->priority?->name,
                $ticket->status?->name,
                $ticket->divisi?->nama_divisi,
                $ticket->outlet?->nama_outlet,
                $ticket->creator?->nama_lengkap,
                $teamNames,
                optional($ticket->created_at)->format('Y-m-d H:i:s'),
                $ticket->due_date ? Carbon::parse($ticket->due_date)->format('Y-m-d H:i:s') : '',
                $selesaiAt,
                $prSummaries->count(),
                $prSummaries->where(fn ($s) => $s === 'PAID')->count(),
                $prSummaries->where(fn ($s) => $s === 'ON_PROCESS')->count(),
            ];

            $c = 1;
            foreach ($values as $v) {
                $sheet->setCellValueByColumnAndRow($c, $row, $v);
                $c++;
            }
            $row++;
        }

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F2937']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        ]);
        $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        ]);
        if ($row > 4) {
            $sheet->getStyle("A4:{$lastCol}" . ($row - 1))->getAlignment()->setVertical('top');
        }

        for ($i = 1; $i <= count($headers); $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->freezePane('A4');
        $sheet->setAutoFilter("A3:{$lastCol}3");

        $fileName = 'ticket-report-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Kalender bulanan: ticket di tanggal due & tanggal dibuat (bulan berjalan).
     */
    public function calendar(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        if ($month < 1 || $month > 12) {
            $month = now()->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = now()->year;
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        $tickets = Ticket::query()
            ->with([
                'status:id,name,slug,is_final',
                'priority:id,name',
                'category:id,name',
                'outlet:id_outlet,nama_outlet',
                'creator:id,nama_lengkap',
                'assignedUsers:id,nama_lengkap,avatar',
                'attachments:id,ticket_id,file_name,file_path,file_size,mime_type',
            ])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('due_date', [$start, $end])
                    ->orWhereBetween('created_at', [$start, $end]);
            })
            ->orderBy('ticket_number')
            ->get();

        $ticketIds = $tickets->pluck('id')->toArray();
        $prsByTicket = collect();
        $paymentStatsByPr = collect();

        if (!empty($ticketIds)) {
            $prs = PurchaseRequisition::whereIn('ticket_id', $ticketIds)
                ->select('id', 'ticket_id', 'pr_number', 'status', 'mode', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();
            $prsByTicket = $prs->groupBy('ticket_id');

            $prIds = $prs->pluck('id')->toArray();
            if (!empty($prIds)) {
                $paymentStatsByPr = DB::table('non_food_payments')
                    ->whereIn('purchase_requisition_id', $prIds)
                    ->select(
                        'purchase_requisition_id as pr_id',
                        DB::raw('COUNT(*) as total_payments'),
                        DB::raw("SUM(CASE WHEN status IN ('paid', 'approved') AND status != 'cancelled' THEN 1 ELSE 0 END) as paid_payments")
                    )
                    ->groupBy('purchase_requisition_id')
                    ->get()
                    ->keyBy('pr_id');
            }
        }

        $calendarEvents = [];
        foreach ($tickets as $ticket) {
            $dueDay = $ticket->due_date ? Carbon::parse($ticket->due_date)->toDateString() : null;
            $createdDay = $ticket->created_at
                ? $ticket->created_at->clone()->timezone(config('app.timezone'))->toDateString()
                : null;

            $inMonth = function (?string $dateStr) use ($start, $end): bool {
                if ($dateStr === null || $dateStr === '') {
                    return false;
                }
                $d = Carbon::parse($dateStr);

                return $d->gte($start->copy()->startOfDay()) && $d->lte($end->copy()->endOfDay());
            };

            if ($dueDay && $inMonth($dueDay)) {
                $calendarEvents[] = $this->buildTicketCalendarEvent($ticket, $dueDay, 'due', $prsByTicket, $paymentStatsByPr);
            }
            if ($createdDay && $inMonth($createdDay) && $createdDay !== $dueDay) {
                $calendarEvents[] = $this->buildTicketCalendarEvent($ticket, $createdDay, 'created', $prsByTicket, $paymentStatsByPr);
            }
        }

        return Inertia::render('Tickets/Calendar', [
            'calendarEvents' => $calendarEvents,
            'year' => $year,
            'month' => $month,
        ]);
    }

    private function buildTicketCalendarEvent(Ticket $ticket, string $date, string $kind, $prsByTicket, $paymentStatsByPr): array
    {
        $slug = $ticket->status?->slug ?? '';
        $isFinal = (bool) ($ticket->status?->is_final ?? false);
        $due = $ticket->due_date ? Carbon::parse($ticket->due_date) : null;
        $overdue = $kind === 'due' && $due && $due->copy()->startOfDay()->lt(now()->startOfDay()) && !$isFinal;

        $palette = $this->ticketCalendarColors($slug, $overdue, $kind);

        $prefix = $kind === 'due' ? 'Due' : 'Baru';
        $title = $prefix . ' · ' . $ticket->ticket_number;
        $relatedPrs = collect($prsByTicket->get($ticket->id, collect()));
        $prSummaries = $relatedPrs->map(function ($pr) use ($paymentStatsByPr) {
            $paymentStat = $paymentStatsByPr->get($pr->id);
            $totalPayments = (int) ($paymentStat->total_payments ?? 0);
            $paidPayments = (int) ($paymentStat->paid_payments ?? 0);

            $paymentStatus = 'NO_PAYMENT';
            if (strtoupper((string) $pr->status) === 'PAID' || $paidPayments > 0) {
                $paymentStatus = 'PAID';
            } elseif ($totalPayments > 0) {
                $paymentStatus = 'ON_PROCESS';
            }

            return [
                'id' => $pr->id,
                'pr_number' => $pr->pr_number,
                'mode' => $pr->mode,
                'status' => $pr->status,
                'payment_status' => $paymentStatus,
                'total_payments' => $totalPayments,
                'paid_payments' => $paidPayments,
                'created_at' => $pr->created_at,
            ];
        })->values();

        $paymentInfo = [
            'total_pr' => $prSummaries->count(),
            'total_paid_pr' => $prSummaries->where('payment_status', 'PAID')->count(),
            'total_processing_pr' => $prSummaries->where('payment_status', 'ON_PROCESS')->count(),
            'prs' => $prSummaries,
        ];

        $issueMeta = $this->ticketCalendarIssueMeta($ticket);

        return [
            'id' => $ticket->id . '-' . $kind . '-' . $date,
            'title' => $title,
            'start' => $date,
            'allDay' => true,
            'backgroundColor' => $palette['bg'],
            'borderColor' => $palette['border'],
            'textColor' => $palette['text'],
            'extendedProps' => [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'title_full' => $ticket->title,
                'status' => $ticket->status?->name,
                'status_slug' => $slug,
                'priority' => $ticket->priority?->name,
                'outlet_name' => $ticket->outlet?->nama_outlet,
                'creator_name' => $ticket->creator?->nama_lengkap,
                'issue_type_label' => $issueMeta['label'],
                'issue_type_variant' => $issueMeta['variant'],
                'created_at_label' => $ticket->created_at?->format('d M Y H:i'),
                'due_date_label' => $ticket->due_date ? Carbon::parse($ticket->due_date)->format('d M Y H:i') : null,
                'assigned_team' => $ticket->assignedUsers->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->nama_lengkap,
                        'avatar' => $user->avatar,
                    ];
                })->values()->all(),
                'attachments' => $ticket->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'name' => $attachment->file_name,
                        'path' => $attachment->file_path,
                        'mime' => $attachment->mime_type,
                        'size' => $attachment->file_size,
                    ];
                })->values()->all(),
                'kind' => $kind,
                'overdue' => $overdue,
                'payment_info' => $paymentInfo,
            ],
        ];
    }

    /**
     * Selaras dengan inferensi issue type di Tickets/Index.vue (defect / ops issue + fallback teks mentah).
     *
     * @return array{label: string, variant: string}
     */
    private function ticketCalendarIssueMeta(Ticket $ticket): array
    {
        $normalize = static function (?string $value): string {
            if ($value === null || $value === '') {
                return '';
            }

            return strtolower(str_replace(['_', '-'], ' ', trim($value)));
        };

        $raw = $ticket->getAttribute('issue_type');
        $rawStr = $raw !== null && $raw !== '' ? (string) $raw : '';
        $issueNorm = $normalize($rawStr);
        $categoryNorm = $normalize($ticket->category?->name);

        $key = '';
        if ($issueNorm === 'defect') {
            $key = 'defect';
        } elseif ($issueNorm === 'ops issue') {
            $key = 'ops_issue';
        } elseif (str_contains($categoryNorm, 'defect')) {
            $key = 'defect';
        } elseif (
            str_contains($categoryNorm, 'ops issue')
            || str_contains($categoryNorm, 'ops')
            || str_contains($categoryNorm, 'operation')
        ) {
            $key = 'ops_issue';
        }

        if ($key === 'defect') {
            return ['label' => 'Defect', 'variant' => 'defect'];
        }
        if ($key === 'ops_issue') {
            return ['label' => 'Ops Issue', 'variant' => 'ops_issue'];
        }

        if ($rawStr !== '') {
            return ['label' => $rawStr, 'variant' => 'custom'];
        }

        return ['label' => '', 'variant' => ''];
    }

    /**
     * @return array{bg: string, border: string, text: string}
     */
    private function ticketCalendarColors(string $slug, bool $overdue, string $kind): array
    {
        if ($overdue) {
            return ['bg' => '#fee2e2', 'border' => '#dc2626', 'text' => '#991b1b'];
        }
        if ($kind === 'created') {
            return ['bg' => '#e0e7ff', 'border' => '#6366f1', 'text' => '#312e81'];
        }

        return match ($slug) {
            'open' => ['bg' => '#dbeafe', 'border' => '#2563eb', 'text' => '#1e3a8a'],
            'in_progress' => ['bg' => '#fef9c3', 'border' => '#ca8a04', 'text' => '#713f12'],
            'resolved' => ['bg' => '#dcfce7', 'border' => '#16a34a', 'text' => '#14532d'],
            'closed' => ['bg' => '#f3f4f6', 'border' => '#6b7280', 'text' => '#1f2937'],
            default => ['bg' => '#f3f4f6', 'border' => '#9ca3af', 'text' => '#374151'],
        };
    }

    /**
     * Sync closed_at when status transitions to/from slug "closed".
     *
     * @return array{closed_at?: \Illuminate\Support\Carbon|null}
     */
    private function closedAtAttributesForStatusChange(?string $oldSlug, TicketStatus $newStatus): array
    {
        $newSlug = $newStatus->slug;
        if ($oldSlug !== 'closed' && $newSlug === 'closed') {
            return ['closed_at' => now()];
        }
        if ($oldSlug === 'closed' && $newSlug !== 'closed') {
            return ['closed_at' => null];
        }

        return [];
    }

    /**
     * Show the form for creating a new ticket
     */
    public function create()
    {
        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        $divisis = Divisi::active()->orderBy('nama_divisi')->get();
        $outlets = Outlet::active()->orderBy('nama_outlet')->get();

        return Inertia::render('Tickets/Create', [
            'categories' => $categories,
            'priorities' => $priorities,
            'divisis' => $divisis,
            'outlets' => $outlets,
        ]);
    }

    /**
     * Store a newly created ticket
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:ticket_categories,id',
            'priority_id' => 'required|exists:ticket_priorities,id',
            'divisi_id' => 'required|exists:tbl_data_divisi,id',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Get default status (Open) or first available status
            $defaultStatus = TicketStatus::where('slug', 'open')->first();
            if (!$defaultStatus) {
                // Fallback: get first active status
                $defaultStatus = TicketStatus::active()->first();
                if (!$defaultStatus) {
                    // Last resort: get any status
                    $defaultStatus = TicketStatus::first();
                    if (!$defaultStatus) {
                        throw new \Exception('No ticket status found. Please run database migrations and seed data.');
                    }
                }
            }

            // Get priority to calculate due date
            $priority = TicketPriority::findOrFail($request->priority_id);
            $dueDate = now()->addDays($priority->max_days ?? 7);

            $ticket = Ticket::create([
                'ticket_number' => Ticket::generateTicketNumber(),
                'title' => $request->title,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'priority_id' => $request->priority_id,
                'status_id' => $defaultStatus->id,
                'divisi_id' => $request->divisi_id,
                'outlet_id' => $request->outlet_id,
                'created_by' => auth()->id(),
                'due_date' => $dueDate,
                'source' => 'manual',
            ]);

            // Create ticket history
            $this->createTicketHistory($ticket, 'created', null, null, 'Ticket created');

            // Handle file uploads
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('ticket-attachments', 'public');
                    
                    TicketAttachment::create([
                        'ticket_id' => $ticket->id,
                        'uploaded_by' => auth()->id(),
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            // Send notifications to users in the selected division
            $this->sendTicketCreatedNotifications($ticket);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket berhasil dibuat',
                'data' => $ticket->load(['category', 'priority', 'status', 'divisi', 'outlet', 'creator'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified ticket
     */
    public function show($id)
    {
        return Inertia::render('Tickets/Show', [
            'ticket' => $this->buildTicketDetailArray((int) $id),
        ]);
    }

    /**
     * Payload detail ticket (web + Approval App API).
     */
    protected function buildTicketDetailArray(int $id): array
    {
        $ticket = Ticket::with([
            'category',
            'priority',
            'status',
            'divisi',
            'outlet',
            'creator',
            'comments.user',
            'comments.attachments',
            'attachments',
            'history.user',
            'assignedUsers:id,nama_lengkap,avatar',
        ])->findOrFail($id);

        // If ticket source is daily_report, get attachments from daily report area
        if ($ticket->source === 'daily_report' && $ticket->source_id) {
            $dailyReport = \App\Models\DailyReport::find($ticket->source_id);
            if ($dailyReport) {
                // Get the area name from ticket title (format: "Area Name - Description...")
                $titleParts = explode(' - ', $ticket->title);
                $areaName = $titleParts[0] ?? '';

                // Find the area by name
                $area = \App\Models\Area::where('nama_area', $areaName)->first();

                if ($area) {
                    // Get daily report area with documentation
                    $reportArea = \App\Models\DailyReportArea::where('daily_report_id', $dailyReport->id)
                        ->where('area_id', $area->id)
                        ->first();

                    if ($reportArea && $reportArea->documentation && !empty($reportArea->documentation)) {
                        // Create virtual attachments from daily report documentation
                        $dailyReportAttachments = [];
                        foreach ($reportArea->documentation as $index => $documentPath) {
                            $fileInfo = pathinfo($documentPath);
                            $fileName = $fileInfo['basename'];

                            // Get file info
                            $cleanPath = ltrim($documentPath, '/storage/');
                            $fullPath = storage_path('app/public/' . $cleanPath);

                            if (file_exists($fullPath)) {
                                $fileSize = filesize($fullPath);
                                $mimeType = mime_content_type($fullPath);

                                $dailyReportAttachments[] = (object) [
                                    'id' => 'daily_report_' . $index,
                                    'ticket_id' => $ticket->id,
                                    'comment_id' => null,
                                    'file_name' => $fileName,
                                    'file_path' => $documentPath, // Use original path from daily report
                                    'file_size' => $fileSize,
                                    'mime_type' => $mimeType,
                                    'uploaded_by' => $ticket->created_by,
                                    'created_at' => $reportArea->created_at,
                                    'updated_at' => $reportArea->updated_at,
                                    'is_daily_report' => true, // Flag to identify daily report attachments
                                ];
                            }
                        }

                        // Merge daily report attachments with regular attachments
                        $ticket->attachments = $ticket->attachments->concat(collect($dailyReportAttachments));
                    }
                }
            }
        }

        // Convert attachments collection to array for proper frontend serialization
        $ticketData = $ticket->toArray();
        $ticketData['attachments'] = $ticket->attachments->toArray();

        $relatedPrs = PurchaseRequisition::where('ticket_id', $ticket->id)
            ->select('id', 'pr_number', 'status', 'mode', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $paymentStatsByPr = collect();
        if ($relatedPrs->isNotEmpty()) {
            $paymentStatsByPr = DB::table('non_food_payments')
                ->whereIn('purchase_requisition_id', $relatedPrs->pluck('id')->toArray())
                ->select(
                    'purchase_requisition_id as pr_id',
                    DB::raw('COUNT(*) as total_payments'),
                    DB::raw("SUM(CASE WHEN status IN ('paid', 'approved') AND status != 'cancelled' THEN 1 ELSE 0 END) as paid_payments")
                )
                ->groupBy('purchase_requisition_id')
                ->get()
                ->keyBy('pr_id');
        }

        $ticketData['payment_info'] = $relatedPrs->map(function ($pr) use ($paymentStatsByPr) {
            $paymentStat = $paymentStatsByPr->get($pr->id);
            $totalPayments = (int) ($paymentStat->total_payments ?? 0);
            $paidPayments = (int) ($paymentStat->paid_payments ?? 0);

            $paymentStatus = 'NO_PAYMENT';
            if (strtoupper((string) $pr->status) === 'PAID' || $paidPayments > 0) {
                $paymentStatus = 'PAID';
            } elseif ($totalPayments > 0) {
                $paymentStatus = 'ON_PROCESS';
            }

            return [
                'id' => $pr->id,
                'pr_number' => $pr->pr_number,
                'mode' => $pr->mode,
                'status' => $pr->status,
                'created_at' => $pr->created_at,
                'payment_status' => $paymentStatus,
                'total_payments' => $totalPayments,
                'paid_payments' => $paidPayments,
            ];
        })->values();

        return $ticketData;
    }

    /**
     * Detail ticket — Approval App (JSON).
     */
    public function apiShow($id)
    {
        return response()->json([
            'success' => true,
            'ticket' => $this->buildTicketDetailArray((int) $id),
        ]);
    }

    /**
     * Opsi form (create/edit) + assign — Approval App.
     */
    public function apiFormOptions()
    {
        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        $statuses = TicketStatus::active()->ordered()->get();
        $divisis = Divisi::active()->orderBy('nama_divisi')->get();
        $outlets = Outlet::active()->orderBy('nama_outlet')->get();
        $assignableUsers = User::where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'avatar', 'division_id']);

        return response()->json([
            'success' => true,
            'categories' => $categories,
            'priorities' => $priorities,
            'statuses' => $statuses,
            'divisions' => $divisis,
            'outlets' => $outlets,
            'assignable_users' => $assignableUsers,
        ]);
    }

    /**
     * Daftar ticket terpaginasi — selaras filter Tickets/Index (Approval App).
     */
    public function apiIndex(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', 'all');
        $priority = $request->get('priority', 'all');
        $category = $request->get('category', 'all');
        $division = $request->get('division', 'all');
        $outlet = $request->get('outlet', 'all');
        $paymentStatus = $request->get('payment_status', 'all');
        $perPage = (int) $request->get('per_page', 15);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

        $query = Ticket::with([
            'category',
            'priority',
            'status',
            'divisi',
            'outlet',
            'creator',
            'assignedUsers:id,nama_lengkap,avatar',
        ])->withCount('comments');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status !== 'all') {
            $query->whereHas('status', function ($q) use ($status) {
                $q->where('slug', $status);
            });
        }

        if ($priority !== 'all') {
            $query->where('priority_id', $priority);
        }

        if ($category !== 'all') {
            $query->where('category_id', $category);
        }

        if ($division !== 'all') {
            $query->where('divisi_id', $division);
        }

        if ($outlet !== 'all') {
            $query->where('outlet_id', $outlet);
        }

        if ($paymentStatus !== 'all') {
            $paidCondition = function ($prQuery) {
                $prQuery->where(function ($q) {
                    $q->whereRaw("UPPER(status) = 'PAID'")
                        ->orWhereExists(function ($subQuery) {
                            $subQuery->select(DB::raw(1))
                                ->from('non_food_payments as nfp')
                                ->whereColumn('nfp.purchase_requisition_id', 'purchase_requisitions.id')
                                ->whereIn('nfp.status', ['paid', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled');
                        });
                });
            };

            $hasAnyPaymentCondition = function ($prQuery) {
                $prQuery->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('non_food_payments as nfp')
                        ->whereColumn('nfp.purchase_requisition_id', 'purchase_requisitions.id')
                        ->where('nfp.status', '!=', 'cancelled');
                });
            };

            if ($paymentStatus === 'paid') {
                $query->whereHas('purchaseRequisitions', $paidCondition);
            } elseif ($paymentStatus === 'on_process') {
                $query->whereDoesntHave('purchaseRequisitions', $paidCondition)
                    ->whereHas('purchaseRequisitions', $hasAnyPaymentCondition);
            } elseif ($paymentStatus === 'no_pr') {
                $query->whereDoesntHave('purchaseRequisitions');
            } elseif ($paymentStatus === 'with_pr') {
                $query->whereHas('purchaseRequisitions');
            }
        }

        $tickets = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $ticketIds = $tickets->getCollection()->pluck('id')->toArray();
        $prsByTicket = collect();
        $paymentStatsByPr = collect();

        if (! empty($ticketIds)) {
            $prs = PurchaseRequisition::whereIn('ticket_id', $ticketIds)
                ->select('id', 'ticket_id', 'pr_number', 'status', 'mode', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();

            $prsByTicket = $prs->groupBy('ticket_id');

            $prIds = $prs->pluck('id')->toArray();
            if (! empty($prIds)) {
                $paymentStatsByPr = DB::table('non_food_payments')
                    ->whereIn('purchase_requisition_id', $prIds)
                    ->select(
                        'purchase_requisition_id as pr_id',
                        DB::raw('COUNT(*) as total_payments'),
                        DB::raw("SUM(CASE WHEN status IN ('paid', 'approved') AND status != 'cancelled' THEN 1 ELSE 0 END) as paid_payments")
                    )
                    ->groupBy('purchase_requisition_id')
                    ->get()
                    ->keyBy('pr_id');
            }
        }

        $tickets->getCollection()->transform(function ($ticket) use ($prsByTicket, $paymentStatsByPr) {
            $relatedPrs = collect($prsByTicket->get($ticket->id, collect()));

            $prSummaries = $relatedPrs->map(function ($pr) use ($paymentStatsByPr) {
                $paymentStat = $paymentStatsByPr->get($pr->id);
                $totalPayments = (int) ($paymentStat->total_payments ?? 0);
                $paidPayments = (int) ($paymentStat->paid_payments ?? 0);

                $payStatus = 'NO_PAYMENT';
                if (strtoupper((string) $pr->status) === 'PAID' || $paidPayments > 0) {
                    $payStatus = 'PAID';
                } elseif ($totalPayments > 0) {
                    $payStatus = 'ON_PROCESS';
                }

                return [
                    'id' => $pr->id,
                    'pr_number' => $pr->pr_number,
                    'mode' => $pr->mode,
                    'status' => $pr->status,
                    'payment_status' => $payStatus,
                    'total_payments' => $totalPayments,
                    'paid_payments' => $paidPayments,
                ];
            })->values();

            $ticket->payment_info = [
                'total_pr' => $prSummaries->count(),
                'total_paid_pr' => $prSummaries->where('payment_status', 'PAID')->count(),
                'total_processing_pr' => $prSummaries->where('payment_status', 'ON_PROCESS')->count(),
                'prs' => $prSummaries,
            ];

            return $ticket;
        });

        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        $statuses = TicketStatus::active()->ordered()->get();
        $divisis = Divisi::whereHas('tickets')->active()->orderBy('nama_divisi')->get();
        $outlets = Outlet::active()->orderBy('nama_outlet')->get();
        $assignableUsers = User::where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'avatar', 'division_id']);

        $statistics = [
            'total' => Ticket::count(),
            'open' => Ticket::open()->count(),
            'in_progress' => Ticket::inProgress()->count(),
            'resolved' => Ticket::resolved()->count(),
            'closed' => Ticket::closed()->count(),
        ];

        return response()->json([
            'success' => true,
            'tickets' => $tickets->items(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
            'filter_options' => [
                'categories' => $categories,
                'priorities' => $priorities,
                'statuses' => $statuses,
                'divisions' => $divisis,
                'outlets' => $outlets,
            ],
            'statistics' => $statistics,
            'assignable_users' => $assignableUsers,
        ]);
    }

    /**
     * Show the form for editing the specified ticket
     */
    public function edit($id)
    {
        $ticket = Ticket::with([
            'category',
            'priority',
            'status',
            'divisi',
            'outlet',
            'creator',
            'attachments'
        ])->findOrFail($id);

        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        $statuses = TicketStatus::active()->ordered()->get();
        $divisis = Divisi::active()->orderBy('nama_divisi')->get();
        $outlets = Outlet::active()->orderBy('nama_outlet')->get();

        return Inertia::render('Tickets/Edit', [
            'ticket' => $ticket,
            'categories' => $categories,
            'priorities' => $priorities,
            'statuses' => $statuses,
            'divisis' => $divisis,
            'outlets' => $outlets,
        ]);
    }

    /**
     * Update the specified ticket
     */
    public function update(Request $request, $id)
    {
        $ticket = Ticket::with('status')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:ticket_categories,id',
            'priority_id' => 'required|exists:ticket_priorities,id',
            'status_id' => 'required|exists:ticket_statuses,id',
            'divisi_id' => 'required|exists:tbl_data_divisi,id',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldData = $ticket->toArray();
            $oldStatusSlug = $ticket->status?->slug;
            $newStatus = TicketStatus::findOrFail($request->status_id);

            // Recalculate due date if priority changed
            $dueDate = $ticket->due_date;
            if ($oldData['priority_id'] != $request->priority_id) {
                $priority = TicketPriority::findOrFail($request->priority_id);
                $dueDate = now()->addDays($priority->max_days ?? 7);
            }

            $closedAtAttrs = $this->closedAtAttributesForStatusChange($oldStatusSlug, $newStatus);

            $ticket->update(array_merge([
                'title' => $request->title,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'priority_id' => $request->priority_id,
                'status_id' => $request->status_id,
                'divisi_id' => $request->divisi_id,
                'outlet_id' => $request->outlet_id,
                'due_date' => $dueDate,
            ], $closedAtAttrs));

            // Create ticket history for changes
            $this->createTicketHistory($ticket, 'updated', null, null, 'Ticket updated');


            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket berhasil diperbarui',
                'data' => $ticket->load(['category', 'priority', 'status', 'divisi', 'outlet', 'creator'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quick status change from ticket list (no full edit form).
     */
    public function updateStatus(Request $request, $id)
    {
        $ticket = Ticket::with('status')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status_id' => 'required|exists:ticket_statuses,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newStatus = TicketStatus::active()->where('id', $request->status_id)->first();
        if (!$newStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Status tidak valid atau tidak aktif.',
            ], 422);
        }

        if ((int) $ticket->status_id === (int) $newStatus->id) {
            return response()->json([
                'success' => true,
                'message' => 'Status tidak berubah.',
                'data' => $ticket->load(['category', 'priority', 'status', 'divisi', 'outlet', 'creator']),
            ]);
        }

        $oldLabel = $ticket->status?->name ?? '-';
        $oldStatusSlug = $ticket->status?->slug;
        $previousStatusId = $ticket->status_id;

        DB::beginTransaction();
        try {
            $closedAtAttrs = $this->closedAtAttributesForStatusChange($oldStatusSlug, $newStatus);
            $ticket->update(array_merge(['status_id' => $newStatus->id], $closedAtAttrs));
            $this->createTicketHistory(
                $ticket,
                'updated',
                'status_id',
                (string) $previousStatusId,
                'Status diubah dari ' . $oldLabel . ' ke ' . $newStatus->name
            );
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status ticket diperbarui.',
                'data' => $ticket->fresh()->load(['category', 'priority', 'status', 'divisi', 'outlet', 'creator']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified ticket
     */
    public function destroy($id)
    {
        $ticket = Ticket::findOrFail($id);

        try {
            $ticket->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ticket berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create ticket from daily report concern
     */
    public function createFromDailyReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'daily_report_id' => 'required|exists:daily_reports,id',
            'area_id' => 'required|exists:areas,id',
            'finding_problem' => 'required|string',
            'divisi_concern_id' => 'required|exists:tbl_data_divisi,id',
            'category_id' => 'required|exists:ticket_categories,id',
            'priority_id' => 'required|exists:ticket_priorities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Get daily report data
            $dailyReport = \App\Models\DailyReport::with(['outlet', 'department'])->findOrFail($request->daily_report_id);
            $area = \App\Models\Area::findOrFail($request->area_id);
            $divisiConcern = \App\Models\Divisi::findOrFail($request->divisi_concern_id);

            // Get default status (Open) or first available status
            $defaultStatus = TicketStatus::where('slug', 'open')->first();
            if (!$defaultStatus) {
                // Fallback: get first active status
                $defaultStatus = TicketStatus::active()->first();
                if (!$defaultStatus) {
                    // Last resort: get any status
                    $defaultStatus = TicketStatus::first();
                    if (!$defaultStatus) {
                        throw new \Exception('No ticket status found. Please run database migrations and seed data.');
                    }
                }
            }

            // Get priority to calculate due date
            $priority = TicketPriority::findOrFail($request->priority_id);
            $dueDate = now()->addDays($priority->max_days ?? 7);

            // Create ticket title
            $title = "Daily Report Issue - {$area->nama_area}";
            
            // Create ticket description
            $description = "Issue found during daily report inspection:\n\n";
            $description .= "Outlet: {$dailyReport->outlet->nama_outlet}\n";
            $description .= "Department: {$dailyReport->department->nama_departemen}\n";
            $description .= "Area: {$area->nama_area}\n";
            $description .= "Divisi Concern: {$divisiConcern->nama_divisi}\n\n";
            $description .= "Finding Problem:\n{$request->finding_problem}";

            $ticket = Ticket::create([
                'ticket_number' => Ticket::generateTicketNumber(),
                'title' => $title,
                'description' => $description,
                'category_id' => $request->category_id,
                'priority_id' => $request->priority_id,
                'status_id' => $defaultStatus->id,
                'divisi_id' => $divisiConcern->id, // Divisi concern
                'outlet_id' => $dailyReport->outlet_id,
                'created_by' => auth()->id(),
                'due_date' => $dueDate,
                'source' => 'daily_report',
                'source_id' => $dailyReport->id,
            ]);

            // Create ticket history
            $this->createTicketHistory($ticket, 'created', null, null, 
                "Ticket created from Daily Report #{$dailyReport->id}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket berhasil dibuat dari Daily Report',
                'data' => $ticket->load(['category', 'priority', 'status', 'divisi', 'outlet', 'creator'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add comment to ticket
     */
    public function addComment(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'comment' => 'nullable|string|max:1000|required_without:attachments',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,pdf,doc,docx',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $comment = \App\Models\TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'comment' => trim((string) $request->comment),
                'is_internal' => false,
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('ticket-comment-attachments', 'public');
                    TicketAttachment::create([
                        'ticket_id' => $ticket->id,
                        'comment_id' => $comment->id,
                        'uploaded_by' => auth()->id(),
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            // Create ticket history
            $historyDescription = trim((string) $request->comment) !== ''
                ? 'Comment added: ' . substr(trim((string) $request->comment), 0, 50) . '...'
                : 'Comment with attachment added';
            $this->createTicketHistory($ticket, 'comment_added', null, null, $historyDescription);

            // Send notifications for new comment
            $this->sendCommentNotifications($ticket, $comment);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil ditambahkan',
                'data' => $comment->load(['user', 'attachments'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan komentar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ticket comments for index modal
     */
    public function getComments($id)
    {
        $ticket = Ticket::findOrFail($id);

        $comments = \App\Models\TicketComment::with(['user', 'attachments'])
            ->where('ticket_id', $ticket->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments,
        ]);
    }

    /**
     * Update comment
     */
    public function updateComment(Request $request, $id)
    {
        $comment = \App\Models\TicketComment::findOrFail($id);

        // Check if user can edit this comment (only the author or admin)
        if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk mengedit komentar ini'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $comment->update([
                'comment' => $request->comment,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil diperbarui',
                'data' => $comment->load('user')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui komentar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete comment
     */
    public function deleteComment($id)
    {
        $comment = \App\Models\TicketComment::findOrFail($id);

        // Check if user can delete this comment (only the author or admin)
        if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk menghapus komentar ini'
            ], 403);
        }

        try {
            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus komentar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign team members to ticket
     */
    public function assignTeam(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|integer|exists:users,id',
            'primary_user_id' => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userIds = collect($request->user_ids)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $primaryUserId = $request->primary_user_id ? (int) $request->primary_user_id : null;
        if (!$primaryUserId || !$userIds->contains($primaryUserId)) {
            $primaryUserId = $userIds->first();
        }

        DB::beginTransaction();
        try {
            TicketAssignment::where('ticket_id', $ticket->id)->delete();

            foreach ($userIds as $userId) {
                TicketAssignment::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $userId,
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                    'is_primary' => $userId === $primaryUserId,
                ]);
            }

            $assignedUsers = User::whereIn('id', $userIds)->pluck('nama_lengkap')->toArray();
            $this->createTicketHistory(
                $ticket,
                'assigned',
                'assigned_users',
                null,
                'Assigned team: ' . implode(', ', $assignedUsers)
            );

            $this->sendTicketAssignmentNotifications($ticket, $userIds->all(), $primaryUserId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Team ticket berhasil di-assign',
                'data' => $ticket->fresh()->load(['assignedUsers:id,nama_lengkap,avatar']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal assign team: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send notifications to users in the selected division when a new ticket is created
     */
    private function sendTicketCreatedNotifications($ticket)
    {
        try {
            // Get users in the selected division with status 'A'
            $users = \App\Models\User::where('division_id', $ticket->divisi_id)
                ->where('status', 'A')
                ->get();

            // Get creator name
            $creator = auth()->user();
            
            // Get outlet name
            $outlet = \App\Models\Outlet::find($ticket->outlet_id);
            
            // Get divisi name
            $divisi = \App\Models\Divisi::find($ticket->divisi_id);

            foreach ($users as $user) {
                NotificationService::insert([
                    'user_id' => $user->id,
                    'task_id' => $ticket->id,
                    'type' => 'ticket_created',
                    'message' => "Ticket baru telah dibuat:\n\nNo: {$ticket->ticket_number}\nJudul: {$ticket->title}\nDivisi: {$divisi->nama_divisi}\nOutlet: {$outlet->nama_outlet}\nDibuat oleh: {$creator->nama_lengkap}",
                    'url' => config('app.url') . '/tickets/' . $ticket->id,
                    'is_read' => 0,
                ]);
            }

            \Log::info('Ticket created notifications sent', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'divisi_id' => $ticket->divisi_id,
                'notified_users_count' => $users->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send ticket created notifications', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send notifications for new comment to all commenters and division users
     */
    private function sendCommentNotifications($ticket, $comment)
    {
        try {
            // Get all users who have commented on this ticket
            $commenters = \App\Models\TicketComment::where('ticket_id', $ticket->id)
                ->where('user_id', '!=', auth()->id()) // Exclude the current commenter
                ->pluck('user_id')
                ->unique();

            // Get all users in the ticket's division with status 'A'
            $divisionUsers = \App\Models\User::where('division_id', $ticket->divisi_id)
                ->where('status', 'A')
                ->where('id', '!=', auth()->id()) // Exclude the current commenter
                ->pluck('id');

            // Combine and remove duplicates
            $notifyUserIds = $commenters->merge($divisionUsers)->unique();

            // Get commenter name
            $commenter = auth()->user();
            
            // Get outlet name
            $outlet = \App\Models\Outlet::find($ticket->outlet_id);
            
            // Get divisi name
            $divisi = \App\Models\Divisi::find($ticket->divisi_id);

            // Create notification message
            $message = "Komentar baru pada ticket:\n\n";
            $message .= "No: {$ticket->ticket_number}\n";
            $message .= "Judul: {$ticket->title}\n";
            $message .= "Divisi: {$divisi->nama_divisi}\n";
            $message .= "Outlet: {$outlet->nama_outlet}\n";
            $message .= "Komentar: " . substr($comment->comment, 0, 100) . (strlen($comment->comment) > 100 ? '...' : '') . "\n";
            $message .= "Dari: {$commenter->nama_lengkap}";

            foreach ($notifyUserIds as $userId) {
                NotificationService::insert([
                    'user_id' => $userId,
                    'task_id' => $ticket->id,
                    'type' => 'ticket_comment',
                    'message' => $message,
                    'url' => config('app.url') . '/tickets/' . $ticket->id,
                    'is_read' => 0,
                ]);
            }

            \Log::info('Ticket comment notifications sent', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'comment_id' => $comment->id,
                'commenter_id' => auth()->id(),
                'notified_users_count' => $notifyUserIds->count(),
                'commenters_count' => $commenters->count(),
                'division_users_count' => $divisionUsers->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send ticket comment notifications', [
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send notifications to assigned team members
     */
    private function sendTicketAssignmentNotifications($ticket, array $userIds, ?int $primaryUserId = null)
    {
        try {
            if (empty($userIds)) {
                return;
            }

            $ticket->loadMissing(['divisi', 'outlet']);
            $assignerName = auth()->user()->nama_lengkap ?? 'System';
            $outletName = $ticket->outlet->nama_outlet ?? '-';
            $divisiName = $ticket->divisi->nama_divisi ?? '-';

            foreach ($userIds as $userId) {
                $isPrimary = $primaryUserId && ((int) $userId === (int) $primaryUserId);
                $roleLabel = $isPrimary ? 'PIC Utama' : 'Team Support';

                $message = "Anda di-assign ke ticket:\n\n";
                $message .= "No: {$ticket->ticket_number}\n";
                $message .= "Judul: {$ticket->title}\n";
                $message .= "Peran: {$roleLabel}\n";
                $message .= "Divisi: {$divisiName}\n";
                $message .= "Outlet: {$outletName}\n";
                $message .= "Assigned by: {$assignerName}";

                NotificationService::insert([
                    'user_id' => $userId,
                    'task_id' => $ticket->id,
                    'type' => 'ticket_assigned',
                    'message' => $message,
                    'url' => config('app.url') . '/tickets/' . $ticket->id,
                    'is_read' => 0,
                ]);
            }

            \Log::info('Ticket assignment notifications sent', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'assigned_user_ids' => $userIds,
                'primary_user_id' => $primaryUserId,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send ticket assignment notifications', [
                'ticket_id' => $ticket->id ?? null,
                'assigned_user_ids' => $userIds,
                'primary_user_id' => $primaryUserId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create ticket history record
     */
    private function createTicketHistory($ticket, $action, $fieldName = null, $oldValue = null, $description = null)
    {
        \App\Models\TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => null,
            'description' => $description,
        ]);
    }

    /**
     * Get ticket categories for API
     */
    public function getCategories()
    {
        $categories = TicketCategory::active()->orderBy('name')->get();
        
        return response()->json([
            'success' => true,
            'categories' => $categories
        ]);
    }

    /**
     * Get ticket priorities for API
     */
    public function getPriorities()
    {
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'priorities' => $priorities
        ]);
    }

    /**
     * Get tickets by area and outlet for API
     */
    public function getTicketsByArea($areaId, Request $request)
    {
        $area = \App\Models\Area::findOrFail($areaId);
        $outletId = $request->get('outlet_id');
        
        if (!$outletId) {
            return response()->json([
                'success' => false,
                'message' => 'Outlet ID is required'
            ], 400);
        }
        
        // Get tickets that contain the area name in title, match outlet, and are not closed/cancelled
        $tickets = Ticket::with(['status', 'category', 'priority', 'divisi', 'outlet'])
            ->where('title', 'like', "%{$area->nama_area}%")
            ->where('outlet_id', $outletId)
            ->whereHas('status', function($query) {
                $query->whereNotIn('slug', ['closed', 'cancelled']);
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
        
        return response()->json([
            'success' => true,
            'tickets' => $tickets
        ]);
    }
}
