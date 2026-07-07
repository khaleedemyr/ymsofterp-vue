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
use App\Services\TicketTeamAutoAssignService;
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
    public const TICKET_MANAGER_ROLE_ID = '5af56935b011a';

    public const TICKET_MANAGER_DIVISION_ID = 20;

    public const TICKET_MANAGER_JABATAN_ID = 343;

    public const TICKET_VENDOR_DIVISION_ID = 18;

    /**
     * Superadmin, division 20, atau jabatan 343: edit penuh, assign tim, payment/PR, hapus.
     * Membuat ticket (form, API store, import Excel, dari daily report) boleh semua user yang sudah login.
     */
    public static function userCanManageTickets($user): bool
    {
        if (!$user) {
            return false;
        }
        if ((string) ($user->id_role ?? '') === self::TICKET_MANAGER_ROLE_ID) {
            return true;
        }
        if ((int) ($user->division_id ?? 0) === self::TICKET_MANAGER_DIVISION_ID) {
            return true;
        }
        if ((int) ($user->id_jabatan ?? 0) === self::TICKET_MANAGER_JABATAN_ID) {
            return true;
        }

        return false;
    }

    /**
     * Ubah status ticket: admin global ATAU user divisi yang sama dengan divisi concern ticket.
     */
    public static function userCanUpdateTicketStatus($user, Ticket $ticket): bool
    {
        if (! $user) {
            return false;
        }
        if (self::userCanManageTickets($user)) {
            return true;
        }
        if (self::userCanManageVendorExternalTicket($user, $ticket)) {
            return true;
        }
        $userDivisionId = (int) ($user->division_id ?? 0);
        $ticketDivisiId = (int) ($ticket->divisi_id ?? 0);

        return $userDivisionId > 0 && $userDivisionId === $ticketDivisiId;
    }

    /**
     * Divisi 18 dapat mengelola ticket yang dikerjakan external vendor.
     */
    public static function userCanManageVendorExternalTicket($user, Ticket $ticket): bool
    {
        if (! $user || ! $ticket->isExternalVendorTicket()) {
            return false;
        }

        return (int) ($user->division_id ?? 0) === self::TICKET_VENDOR_DIVISION_ID;
    }

    /**
     * Edit, assign tim, dll: admin global ATAU divisi 18 pada ticket external vendor.
     */
    public static function userCanManageTicket($user, Ticket $ticket): bool
    {
        if (! $user) {
            return false;
        }
        if (self::userCanManageTickets($user)) {
            return true;
        }

        return self::userCanManageVendorExternalTicket($user, $ticket);
    }

    /**
     * Isi dikerjakan oleh (internal / external vendor): hanya divisi concern ticket.
     */
    public static function userCanSetWorkExecutorType($user, Ticket $ticket): bool
    {
        if (! $user) {
            return false;
        }
        $userDivisionId = (int) ($user->division_id ?? 0);
        $ticketDivisiId = (int) ($ticket->divisi_id ?? 0);

        return $userDivisionId > 0 && $userDivisionId === $ticketDivisiId;
    }

    /**
     * Nama vendor (opsional): divisi concern, divisi 18, atau admin global — hanya ticket external vendor.
     */
    public static function userCanUpdateVendorName($user, Ticket $ticket): bool
    {
        if (! $user || ! $ticket->isExternalVendorTicket()) {
            return false;
        }
        if (self::userCanManageTickets($user)) {
            return true;
        }
        if (self::userCanManageVendorExternalTicket($user, $ticket)) {
            return true;
        }

        return self::userCanSetWorkExecutorType($user, $ticket);
    }

    protected function ticketWorkExecutorDeniedJsonResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Hanya divisi terkait ticket yang dapat mengisi dikerjakan oleh.',
        ], 403);
    }

    /**
     * @return array<string, string>
     */
    protected function workExecutorTypeOptions(): array
    {
        return Ticket::workExecutorTypeOptions();
    }

    protected function workExecutorTypeLabel(?string $type): ?string
    {
        if (! $type) {
            return null;
        }

        return $this->workExecutorTypeOptions()[$type] ?? $type;
    }

    protected function enrichTicketWorkExecutorFields(Ticket $ticket, $user = null): void
    {
        $ticket->setAttribute(
            'work_executor_type_label',
            $this->workExecutorTypeLabel($ticket->work_executor_type)
        );
        if ($user) {
            $ticket->setAttribute(
                'can_set_work_executor_type',
                self::userCanSetWorkExecutorType($user, $ticket)
            );
            $ticket->setAttribute(
                'can_update_vendor_name',
                self::userCanUpdateVendorName($user, $ticket)
            );
            $ticket->setAttribute(
                'can_manage_ticket',
                self::userCanManageTicket($user, $ticket)
            );
        }
    }

    protected function ticketStatusUpdateDeniedJsonResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki izin mengubah status ticket ini. Hanya divisi terkait ticket, superadmin, atau jabatan terkait yang dapat mengubah status.',
        ], 403);
    }

    protected function ticketManageDeniedJsonResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki izin untuk aksi ini. Hanya superadmin, divisi 20, atau jabatan terkait yang dapat mengelola ticket.',
        ], 403);
    }

    protected function ticketViewDeniedJsonResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Ticket tidak ditemukan.',
        ], 404);
    }

    /** User dengan outlet pusat (id_outlet = 1) melihat semua ticket; lainnya hanya ticket outlet mereka. */
    public const TICKET_VIEW_ALL_OUTLETS_ID = 1;

    public static function userSeesAllTicketOutlets($user): bool
    {
        if (!$user) {
            return false;
        }

        return (int) ($user->id_outlet ?? 0) === self::TICKET_VIEW_ALL_OUTLETS_ID;
    }

    /** Laporan ticket (report per categories/outlet/vendor & export) hanya outlet pusat. */
    public static function userCanAccessTicketReports($user): bool
    {
        return self::userSeesAllTicketOutlets($user);
    }

    protected function ensureUserCanAccessTicketReports($user): void
    {
        if (! self::userCanAccessTicketReports($user)) {
            abort(403, 'Laporan ticket hanya tersedia untuk user outlet pusat.');
        }
    }

    public static function userCanViewTicket($user, Ticket $ticket): bool
    {
        if (self::userSeesAllTicketOutlets($user)) {
            return true;
        }
        $oid = $user?->id_outlet;
        if ($oid === null || $oid === '') {
            return false;
        }

        return (int) $ticket->outlet_id === (int) $oid;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Ticket>  $query
     */
    protected function applyTicketOutletVisibility(\Illuminate\Database\Eloquent\Builder $query, $user): void
    {
        if (self::userSeesAllTicketOutlets($user)) {
            return;
        }
        $oid = $user?->id_outlet;
        if ($oid === null || $oid === '') {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where('outlet_id', (int) $oid);
    }

    /**
     * Filter jenis issue (defect / ops_issue), selaras inferensi di ticketCalendarIssueMeta.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Ticket>  $query
     */
    protected function applyTicketIssueTypeFilter(\Illuminate\Database\Eloquent\Builder $query, string $issueType): void
    {
        $issueType = strtolower(trim($issueType));
        if ($issueType === '' || $issueType === 'all') {
            return;
        }

        if ($issueType === 'defect') {
            $query->where(function ($q) {
                $q->whereRaw("LOWER(REPLACE(REPLACE(TRIM(COALESCE(issue_type, '')), '_', ' '), '-', ' ')) = ?", ['defect'])
                    ->orWhereHas('category', function ($c) {
                        $c->whereRaw('LOWER(name) LIKE ?', ['%defect%']);
                    });
            });

            return;
        }

        if ($issueType === 'ops_issue') {
            $query->where(function ($q) {
                $q->whereRaw("LOWER(REPLACE(REPLACE(TRIM(COALESCE(issue_type, '')), '_', ' '), '-', ' ')) = ?", ['ops issue'])
                    ->orWhereHas('category', function ($c) {
                        $c->where(function ($c2) {
                            $c2->whereRaw('LOWER(name) LIKE ?', ['%ops issue%'])
                                ->orWhereRaw('LOWER(name) LIKE ?', ['%operation%'])
                                ->orWhere(function ($c3) {
                                    $c3->whereRaw('LOWER(name) LIKE ?', ['%ops%'])
                                        ->whereRaw('LOWER(name) NOT LIKE ?', ['%defect%']);
                                });
                        });
                    });
            });
        }
    }

    /**
     * Daftar outlet untuk form buat/edit ticket (bukan pusat → hanya outlet user).
     */
    protected function outletsForTicketUser($user)
    {
        $q = Outlet::active()->orderBy('nama_outlet');
        if (! self::userSeesAllTicketOutlets($user)) {
            $oid = $user?->id_outlet;
            if ($oid !== null && $oid !== '') {
                $q->where('id_outlet', (int) $oid);
            }
        }

        return $q->get();
    }

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

                $this->autoAssignTeamFromSetting($ticket);

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
        $issueType = $request->get('issue_type', 'all');
        $perPage = $request->get('per_page', 15);

        $user = $request->user();

        $query = Ticket::with([
            'category',
            'priority', 
            'status',
            'divisi',
            'outlet',
            'creator',
            'assignedUsers:id,nama_lengkap,avatar'
        ])->withCount('comments');

        $this->applyTicketOutletVisibility($query, $request->user());
        $this->applyTicketIssueTypeFilter($query, (string) $issueType);

        // Apply filters
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status !== 'all') {
            $this->applyTicketStatusFilter($query, (string) $status);
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

        $tickets->getCollection()->transform(function ($ticket) use ($prsByTicket, $paymentStatsByPr, $user) {
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

            $ticket->can_update_status = self::userCanUpdateTicketStatus($user, $ticket);
            $this->enrichTicketWorkExecutorFields($ticket, $user);

            return $ticket;
        });

        // Get filter options
        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        $statuses = $this->selectableTicketStatuses();

        // Get only divisions that have tickets (terbatas outlet user bila bukan pusat)
        $divisis = Divisi::whereHas('tickets', function ($q) use ($user) {
            $this->applyTicketOutletVisibility($q, $user);
        })->active()->orderBy('nama_divisi')->get();

        $outletsQuery = Outlet::active()->orderBy('nama_outlet');
        if (! self::userSeesAllTicketOutlets($user)) {
            $oid = $user?->id_outlet;
            if ($oid !== null && $oid !== '') {
                $outletsQuery->where('id_outlet', (int) $oid);
            }
        }
        $outlets = $outletsQuery->get();
        $assignableUsers = User::where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'avatar', 'division_id']);

        // Statistics — selaras filter outlet + jenis issue
        $statsBase = Ticket::query();
        $this->applyTicketOutletVisibility($statsBase, $user);
        $this->applyTicketIssueTypeFilter($statsBase, (string) $issueType);
        $statistics = [
            'total' => (clone $statsBase)->count(),
            'open' => (clone $statsBase)->open()->count(),
            'in_progress' => (clone $statsBase)->inProgress()->count(),
            'closed' => (clone $statsBase)->closed()->count(),
        ];

        return Inertia::render('Tickets/Index', [
            'data' => $tickets,
            'can_manage_tickets' => self::userCanManageTickets($request->user()),
            'tickets_view_all_outlets' => self::userSeesAllTicketOutlets($request->user()),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'priority' => $priority,
                'category' => $category,
                'division' => $division,
                'outlet' => $outlet,
                'payment_status' => $paymentStatus,
                'issue_type' => $issueType,
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
        $this->ensureUserCanAccessTicketReports($request->user());

        $search = $request->get('search', '');
        $status = $request->get('status', 'all');
        $priority = $request->get('priority', 'all');
        $category = $request->get('category', 'all');
        $division = $request->get('division', 'all');
        $outlet = $request->get('outlet', 'all');
        $paymentStatus = $request->get('payment_status', 'all');
        $issueType = $request->get('issue_type', 'all');

        $query = Ticket::with([
            'category',
            'priority',
            'status',
            'divisi',
            'outlet',
            'creator',
            'assignedUsers:id,nama_lengkap',
        ]);

        $this->applyTicketOutletVisibility($query, $request->user());
        $this->applyTicketIssueTypeFilter($query, (string) $issueType);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($status !== 'all') {
            $this->applyTicketStatusFilter($query, (string) $status);
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
     * Report ticket per kategori — tampilan grid (NO, TANGGAL, FINDING, COMPLAIN, RESULT, REMARK).
     */
    public function reportPerCategories(Request $request)
    {
        $this->ensureUserCanAccessTicketReports($request->user());

        $filters = $this->parseTicketReportFilters($request);
        $groups = $this->buildReportPerCategoriesData($request, $filters);

        return Inertia::render('Tickets/ReportPerCategories', [
            'groups' => $groups,
            'filters' => $filters,
            'filterOptions' => $this->ticketReportFilterOptions(),
        ]);
    }

    /**
     * Export Report Ticket Per Categories ke XLSX (dengan gambar complain/result).
     */
    public function exportReportPerCategories(Request $request)
    {
        $this->ensureUserCanAccessTicketReports($request->user());

        $filters = $this->parseTicketReportFilters($request);
        $groups = $this->buildReportPerCategoriesData($request, $filters);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report Per Categories');

        $headers = ['NO', 'TANGGAL', 'FINDING PROBLEM', 'COMPLAIN', 'RESULT', 'REMARK'];
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $row = 1;

        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", 'Report Ticket Per Categories');
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16],
            'alignment' => ['horizontal' => 'center'],
        ]);
        $row++;
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", 'Generated: ' . now()->format('d M Y H:i:s'));
        $row += 2;

        foreach ($groups as $group) {
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->setCellValue("A{$row}", '[' . strtoupper((string) $group['category_name']) . ']');
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFFF00'],
                ],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(22);
            $row++;

            $col = 1;
            foreach ($headers as $h) {
                $sheet->setCellValueByColumnAndRow($col, $row, $h);
                $col++;
            }
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ]);
            $row++;

            foreach ($group['rows'] as $ticketRow) {
                $sheet->setCellValue("A{$row}", $ticketRow['no']);
                $sheet->setCellValue("B{$row}", $ticketRow['tanggal']);
                $sheet->setCellValue("C{$row}", $ticketRow['finding_problem']);
                $sheet->setCellValue("F{$row}", $ticketRow['remark']);
                $sheet->getStyle("A{$row}:F{$row}")->getAlignment()->setVertical('top')->setWrapText(true);
                $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ]);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal('center');
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal('center');

                $imageRowHeight = 95;
                $sheet->getRowDimension($row)->setRowHeight($imageRowHeight);

                $this->embedTicketReportImage($sheet, 'D', $row, $ticketRow['complain_image_path'] ?? null);
                $this->embedTicketReportImage($sheet, 'E', $row, $ticketRow['result_image_path'] ?? null);

                $row++;
            }

            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(34);
        $sheet->getColumnDimension('D')->setWidth(24);
        $sheet->getColumnDimension('E')->setWidth(24);
        $sheet->getColumnDimension('F')->setWidth(28);

        $fileName = 'report-ticket-per-categories-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Report ticket per outlet — grid maintenance style per lokasi outlet.
     */
    public function reportPerOutlet(Request $request)
    {
        $this->ensureUserCanAccessTicketReports($request->user());

        $filters = $this->parseTicketReportFilters($request);
        $groups = $this->buildReportPerOutletsData($request, $filters);

        return Inertia::render('Tickets/ReportPerOutlet', [
            'groups' => $groups,
            'filters' => $filters,
            'filterOptions' => $this->ticketReportFilterOptions(),
        ]);
    }

    /**
     * Export Report Ticket Per Outlet ke XLSX.
     */
    public function exportReportPerOutlet(Request $request)
    {
        $this->ensureUserCanAccessTicketReports($request->user());

        $filters = $this->parseTicketReportFilters($request);
        $groups = $this->buildReportPerOutletsData($request, $filters);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report Per Outlet');

        $lastCol = 'I';
        $row = 1;

        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", 'Report Ticket Per Outlet');
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16],
            'alignment' => ['horizontal' => 'center'],
        ]);
        $row++;
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", 'Generated: ' . now()->format('d M Y H:i:s'));
        $row += 2;

        foreach ($groups as $group) {
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->setCellValue("A{$row}", strtoupper((string) $group['outlet_name']));
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '334155'],
                ],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);
            $row++;

            $sheet->setCellValue("A{$row}", 'NO');
            $sheet->setCellValue("B{$row}", 'FINDING PROBLEM');
            $sheet->setCellValue("C{$row}", 'DOCUMENTATION');
            $sheet->mergeCells("D{$row}:E{$row}");
            $sheet->setCellValue("D{$row}", 'HANDLED BY');
            $sheet->setCellValue("F{$row}", 'EST EXPENSE');
            $sheet->setCellValue("G{$row}", 'PRIORITY');
            $sheet->setCellValue("H{$row}", 'RESULT');
            $sheet->setCellValue("I{$row}", 'STATUS');
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E293B'],
                ],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);
            $row++;
            $sheet->setCellValue("D{$row}", 'ENGINEERING');
            $sheet->setCellValue("E{$row}", 'VENDOR');
            $sheet->getStyle("D{$row}:E{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '475569'],
                ],
                'alignment' => ['horizontal' => 'center'],
            ]);
            $row++;

            foreach ($group['rows'] as $ticketRow) {
                $sheet->setCellValue("A{$row}", $ticketRow['no']);
                $sheet->setCellValue("B{$row}", $ticketRow['finding_problem']);
                $sheet->setCellValue("D{$row}", $ticketRow['handled_internal'] ? 'v' : '');
                $sheet->setCellValue("E{$row}", $ticketRow['handled_vendor'] ? 'v' : '');
                $sheet->setCellValue("F{$row}", $ticketRow['est_expense'] > 0 ? $ticketRow['est_expense'] : '-');
                $sheet->setCellValue("G{$row}", $ticketRow['priority_name'] ?? '-');
                $sheet->setCellValue("I{$row}", $ticketRow['remark']);
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getAlignment()->setVertical('top')->setWrapText(true);
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ]);
                if ($ticketRow['handled_internal']) {
                    $sheet->getStyle("D{$row}")->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFFF00');
                }
                if ($ticketRow['handled_vendor']) {
                    $sheet->getStyle("E{$row}")->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFFF00');
                }

                $imageRowHeight = 95;
                $sheet->getRowDimension($row)->setRowHeight($imageRowHeight);
                $docPath = $ticketRow['documentation_images'][0]['path'] ?? $ticketRow['complain_image_path'] ?? null;
                $this->embedTicketReportImage($sheet, 'C', $row, $docPath);
                $this->embedTicketReportImage($sheet, 'H', $row, $ticketRow['result_image_path'] ?? null);
                $row++;
            }

            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(34);
        $sheet->getColumnDimension('C')->setWidth(24);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(14);
        $sheet->getColumnDimension('G')->setWidth(14);
        $sheet->getColumnDimension('H')->setWidth(24);
        $sheet->getColumnDimension('I')->setWidth(28);

        $fileName = 'report-ticket-per-outlet-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Report khusus ticket dikerjakan external vendor.
     */
    public function reportExternalVendor(Request $request)
    {
        $this->ensureUserCanAccessTicketReports($request->user());

        $filters = $this->parseTicketReportFilters($request);
        $report = $this->buildReportExternalVendorData($request, $filters);

        return Inertia::render('Tickets/ReportExternalVendor', [
            'rows' => $report['rows'],
            'total' => $report['total'],
            'filters' => $filters,
            'filterOptions' => $this->ticketReportFilterOptions(),
        ]);
    }

    /**
     * Export report external vendor ke XLSX.
     */
    public function exportReportExternalVendor(Request $request)
    {
        $this->ensureUserCanAccessTicketReports($request->user());

        $filters = $this->parseTicketReportFilters($request);
        $report = $this->buildReportExternalVendorData($request, $filters);
        $rows = $report['rows'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('External Vendor');

        $headers = ['NO', 'OUTLET', 'TICKET', 'FINDING PROBLEM', 'VENDOR', 'PRIORITY', 'EST EXPENSE', 'STATUS', 'TANGGAL'];
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $row = 1;

        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", 'Report Ticket External Vendor');
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16],
            'alignment' => ['horizontal' => 'center'],
        ]);
        $row++;
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", 'Generated: ' . now()->format('d M Y H:i:s'));
        $row += 2;

        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($col, $row, $h);
            $col++;
        }
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => ['horizontal' => 'center'],
        ]);
        $row++;

        foreach ($rows as $ticketRow) {
            $sheet->setCellValue("A{$row}", $ticketRow['no']);
            $sheet->setCellValue("B{$row}", $ticketRow['outlet_name'] ?? '-');
            $sheet->setCellValue("C{$row}", $ticketRow['ticket_number'] ?? '');
            $sheet->setCellValue("D{$row}", $ticketRow['finding_problem']);
            $sheet->setCellValue("E{$row}", $ticketRow['vendor_name'] ?? '-');
            $sheet->setCellValue("F{$row}", $ticketRow['priority_name'] ?? '-');
            $sheet->setCellValue("G{$row}", $ticketRow['est_expense'] > 0 ? $ticketRow['est_expense'] : '-');
            $sheet->setCellValue("H{$row}", $ticketRow['remark']);
            $sheet->setCellValue("I{$row}", $ticketRow['tanggal'] ?? '');
            $row++;
        }

        for ($i = 1; $i <= count($headers); $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $fileName = 'report-ticket-external-vendor-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Detail PR / ticket untuk card Expenses di dashboard.
     */
    public function dashboardExpenseDetail(Request $request)
    {
        $this->ensureUserCanAccessTicketReports($request->user());

        $type = (string) $request->get('type', 'est');
        $allowedTypes = ['est', 'paid', 'pending', 'no_pr', 'with_pr', 'paid_pr'];
        if (! in_array($type, $allowedTypes, true)) {
            return response()->json(['success' => false, 'message' => 'Tipe tidak valid.'], 422);
        }

        $year = max(2000, min(2100, (int) $request->get('year', now()->year)));
        $month = max(1, min(12, (int) $request->get('month', now()->month)));
        $division = (string) $request->get('division', 'all');

        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth()->endOfDay();

        $filters = [
            'search' => '',
            'status' => 'all',
            'priority' => 'all',
            'category' => 'all',
            'outlet' => 'all',
            'issue_type' => 'all',
            'division' => $division,
            'date_from' => $periodStart->toDateString(),
            'date_to' => $periodEnd->toDateString(),
        ];

        $tickets = $this->fetchTicketsForReport($request, $filters)
            ->load(['outlet:id_outlet,nama_outlet', 'status:id,name']);

        if ($type === 'no_pr') {
            $rows = $tickets
                ->filter(fn (Ticket $ticket) => $ticket->purchaseRequisitions->isEmpty())
                ->map(fn (Ticket $ticket) => [
                    'row_type' => 'ticket',
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'ticket_title' => $ticket->title,
                    'outlet' => $ticket->outlet?->nama_outlet ?? '-',
                    'ticket_status' => $ticket->status?->name ?? '-',
                    'ticket_created_at' => $ticket->created_at?->format('d M Y'),
                ])
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'type' => $type,
                'title' => 'Ticket Tanpa PR',
                'rows' => $rows,
                'total' => count($rows),
            ]);
        }

        $prs = PurchaseRequisition::query()
            ->with(['creator:id,nama_lengkap'])
            ->whereIn('ticket_id', $tickets->pluck('id')->all())
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        if ($prs->isEmpty()) {
            return response()->json([
                'success' => true,
                'type' => $type,
                'title' => $this->dashboardExpenseDetailTitle($type),
                'rows' => [],
                'total' => 0,
            ]);
        }

        $prIds = $prs->pluck('id')->all();
        $paymentStatsByPr = $this->loadTicketDashboardPaymentStats(
            $tickets->filter(fn (Ticket $t) => $t->purchaseRequisitions->isNotEmpty())
        );

        $poByPr = DB::table('purchase_order_ops_items as poi')
            ->join('purchase_order_ops as po', 'poi.purchase_order_ops_id', '=', 'po.id')
            ->leftJoin('users as creator', 'po.created_by', '=', 'creator.id')
            ->where('poi.source_type', 'purchase_requisition_ops')
            ->whereIn('poi.source_id', $prIds)
            ->select(
                'poi.source_id as pr_id',
                'po.id as po_id',
                'po.number as po_number',
                'po.created_at as po_created_at',
                'creator.nama_lengkap as po_creator'
            )
            ->orderByDesc('po.created_at')
            ->get()
            ->groupBy('pr_id');

        $poIds = $poByPr->flatten()->pluck('po_id')->unique()->values()->all();

        $nfpDirectByPr = DB::table('non_food_payments as nfp')
            ->leftJoin('users as creator', 'nfp.created_by', '=', 'creator.id')
            ->whereIn('nfp.purchase_requisition_id', $prIds)
            ->where('nfp.status', '!=', 'cancelled')
            ->select(
                'nfp.purchase_requisition_id as pr_id',
                'nfp.payment_number',
                'nfp.payment_date',
                'nfp.created_at',
                'nfp.status',
                'nfp.amount',
                'creator.nama_lengkap as creator_name'
            )
            ->orderByDesc('nfp.created_at')
            ->get()
            ->groupBy('pr_id');

        $nfpByPo = collect();
        if (! empty($poIds)) {
            $nfpByPo = DB::table('non_food_payments as nfp')
                ->leftJoin('users as creator', 'nfp.created_by', '=', 'creator.id')
                ->whereIn('nfp.purchase_order_ops_id', $poIds)
                ->where('nfp.status', '!=', 'cancelled')
                ->select(
                    'nfp.purchase_order_ops_id as po_id',
                    'nfp.payment_number',
                    'nfp.payment_date',
                    'nfp.created_at',
                    'nfp.status',
                    'nfp.amount',
                    'creator.nama_lengkap as creator_name'
                )
                ->orderByDesc('nfp.created_at')
                ->get()
                ->groupBy('po_id');
        }

        $ticketMap = $tickets->keyBy('id');
        $rows = [];

        foreach ($prs as $pr) {
            $isPaid = $this->isDashboardPrPaid($pr, $paymentStatsByPr);
            $ticket = $ticketMap->get($pr->ticket_id);

            if ($type === 'paid' || $type === 'paid_pr') {
                if (! $isPaid) {
                    continue;
                }
            } elseif ($type === 'pending') {
                if ($isPaid) {
                    continue;
                }
            }

            $pos = ($poByPr->get($pr->id) ?? collect())->unique('po_id')->values();
            $nfps = collect();

            foreach ($pos as $po) {
                foreach ($nfpByPo->get($po->po_id) ?? [] as $nfp) {
                    $nfps->push($nfp);
                }
            }
            foreach ($nfpDirectByPr->get($pr->id) ?? [] as $nfp) {
                $nfps->push($nfp);
            }
            $nfps = $nfps->unique('payment_number')->values();

            $rows[] = [
                'row_type' => 'pr',
                'pr_id' => $pr->id,
                'pr_number' => $pr->pr_number,
                'pr_date' => $pr->date?->format('d M Y') ?? ($pr->created_at?->format('d M Y') ?? '-'),
                'pr_creator' => $pr->creator?->nama_lengkap ?? '-',
                'pr_status' => $pr->status,
                'amount' => (float) $pr->amount,
                'is_paid' => $isPaid,
                'ticket_id' => $pr->ticket_id,
                'ticket_number' => $ticket?->ticket_number ?? '-',
                'ticket_title' => $ticket?->title ?? '-',
                'outlet' => $ticket?->outlet?->nama_outlet ?? '-',
                'has_po' => $pos->isNotEmpty(),
                'po_list' => $pos->map(fn ($po) => [
                    'number' => $po->po_number,
                    'date' => $po->po_created_at ? Carbon::parse($po->po_created_at)->format('d M Y') : '-',
                    'creator' => $po->po_creator ?? '-',
                ])->values()->all(),
                'nfp_list' => $nfps->map(fn ($nfp) => [
                    'number' => $nfp->payment_number,
                    'date' => $nfp->payment_date
                        ? Carbon::parse($nfp->payment_date)->format('d M Y')
                        : ($nfp->created_at ? Carbon::parse($nfp->created_at)->format('d M Y') : '-'),
                    'status' => $nfp->status,
                    'amount' => (float) $nfp->amount,
                    'creator' => $nfp->creator_name ?? '-',
                ])->values()->all(),
            ];
        }

        return response()->json([
            'success' => true,
            'type' => $type,
            'title' => $this->dashboardExpenseDetailTitle($type),
            'rows' => $rows,
            'total' => count($rows),
        ]);
    }

    private function dashboardExpenseDetailTitle(string $type): string
    {
        return match ($type) {
            'paid', 'paid_pr' => 'PR Sudah Dibayar',
            'pending' => 'PR Pending / Belum Lunas',
            'with_pr' => 'Ticket dengan PR',
            default => 'Semua PR (Est. Expense)',
        };
    }

    /**
     * Dashboard analitik ticket bulanan (hanya outlet pusat).
     */
    public function dashboard(Request $request)
    {
        $this->ensureUserCanAccessTicketReports($request->user());

        $year = max(2000, min(2100, (int) $request->get('year', now()->year)));
        $month = max(1, min(12, (int) $request->get('month', now()->month)));
        $division = (string) $request->get('division', 'all');

        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth()->endOfDay();

        $prevStart = $periodStart->copy()->subMonth()->startOfMonth();
        $prevEnd = $prevStart->copy()->endOfMonth()->endOfDay();

        $baseFilters = [
            'search' => '',
            'status' => 'all',
            'priority' => 'all',
            'category' => 'all',
            'outlet' => 'all',
            'issue_type' => 'all',
            'division' => $division,
        ];

        $currentFilters = array_merge($baseFilters, [
            'date_from' => $periodStart->toDateString(),
            'date_to' => $periodEnd->toDateString(),
        ]);

        $prevFilters = array_merge($baseFilters, [
            'date_from' => $prevStart->toDateString(),
            'date_to' => $prevEnd->toDateString(),
        ]);

        $dashboard = $this->buildTicketDashboardData(
            $request,
            $currentFilters,
            $prevFilters,
            $periodStart,
            $periodEnd
        );

        $divisions = Divisi::active()->orderBy('nama_divisi')->get(['id', 'nama_divisi']);

        return Inertia::render('Tickets/Dashboard', [
            'dashboard' => $dashboard,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'division' => $division,
            ],
            'filterOptions' => [
                'divisions' => $divisions,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $prevFilters
     * @return array<string, mixed>
     */
    private function buildTicketDashboardData(
        Request $request,
        array $filters,
        array $prevFilters,
        Carbon $periodStart,
        Carbon $periodEnd
    ): array {
        $tickets = $this->fetchTicketsForReport($request, $filters)
            ->load(['divisi:id,nama_divisi', 'assignedUsers:id,nama_lengkap,avatar']);

        $prevTickets = $this->fetchTicketsForReport($request, $prevFilters);

        $paymentStatsByPr = $this->loadTicketDashboardPaymentStats($tickets);
        $prevPaymentStatsByPr = $this->loadTicketDashboardPaymentStats($prevTickets);

        $current = $this->aggregateTicketDashboardPeriod($tickets, $paymentStatsByPr, $periodStart, $periodEnd);
        $previous = $this->aggregateTicketDashboardPeriod($prevTickets, $prevPaymentStatsByPr, null, null, false);

        $closedInMonthQuery = Ticket::query()
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$periodStart, $periodEnd]);
        $this->applyTicketOutletVisibility($closedInMonthQuery, $request->user());
        if (($filters['division'] ?? 'all') !== 'all') {
            $closedInMonthQuery->where('divisi_id', $filters['division']);
        }
        $current['closed_in_month'] = $closedInMonthQuery->count();

        $current['comparison'] = [
            'total_tickets' => $this->dashboardDelta($current['total_tickets'], $previous['total_tickets']),
            'closed' => $this->dashboardDelta($current['closed'], $previous['closed']),
            'est_expense' => $this->dashboardDelta($current['expenses']['est_expense'], $previous['expenses']['est_expense']),
            'completion_rate' => $this->dashboardDelta($current['progress']['completion_rate'], $previous['progress']['completion_rate']),
        ];

        $current['period'] = [
            'label' => $periodStart->translatedFormat('F Y'),
            'year' => (int) $periodStart->year,
            'month' => (int) $periodStart->month,
            'date_from' => $periodStart->toDateString(),
            'date_to' => $periodEnd->toDateString(),
        ];

        if (($filters['division'] ?? 'all') !== 'all') {
            $divisi = Divisi::find($filters['division']);
            $current['division_label'] = $divisi?->nama_divisi ?? 'Divisi #' . $filters['division'];
        } else {
            $current['division_label'] = 'Semua Divisi';
        }

        return $current;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Ticket>  $tickets
     * @return \Illuminate\Support\Collection<int|string, object>
     */
    private function loadTicketDashboardPaymentStats($tickets)
    {
        $prIds = $tickets
            ->flatMap(fn (Ticket $ticket) => $ticket->purchaseRequisitions->pluck('id'))
            ->unique()
            ->values()
            ->all();

        if (empty($prIds)) {
            return collect();
        }

        return DB::table('non_food_payments')
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

    private function isDashboardPrPaid($pr, $paymentStatsByPr): bool
    {
        if (strtoupper((string) $pr->status) === 'PAID') {
            return true;
        }

        $stat = $paymentStatsByPr->get($pr->id);

        return ((int) ($stat->paid_payments ?? 0)) > 0;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Ticket>  $tickets
     * @param  \Illuminate\Support\Collection<int|string, object>  $paymentStatsByPr
     * @return array<string, mixed>
     */
    private function aggregateTicketDashboardPeriod(
        $tickets,
        $paymentStatsByPr,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null,
        bool $withCharts = true
    ): array {
        $open = 0;
        $inProgress = 0;
        $closed = 0;
        $overdue = 0;
        $withPr = 0;
        $noPr = 0;
        $paidPrCount = 0;
        $onProcessPrCount = 0;
        $internal = 0;
        $externalVendor = 0;
        $unsetExecutor = 0;
        $estExpense = 0.0;
        $paidExpense = 0.0;
        $closeDurations = [];

        $byCategory = [];
        $byPriority = [];
        $byOutlet = [];
        $byDivisi = [];
        $byStatusChart = [];
        $byExecutor = [];
        $dailyMap = [];
        $expenseByOutlet = [];
        $expenseByCategory = [];
        $topExpenseTickets = [];
        $teamStats = [];

        $today = now()->startOfDay();

        foreach ($tickets as $ticket) {
            $slug = $ticket->status?->slug ?? 'unknown';
            $isFinal = (bool) ($ticket->status?->is_final ?? false);
            $isClosed = $isFinal || in_array($slug, ['closed', 'resolved', 'done'], true);

            if ($slug === 'open') {
                $open++;
                $bucket = 'Open';
            } elseif ($slug === 'in_progress') {
                $inProgress++;
                $bucket = 'In Progress';
            } elseif ($isClosed) {
                $closed++;
                $bucket = 'Closed';
            } else {
                $bucket = ucfirst(str_replace('_', ' ', $slug));
            }

            $byStatusChart[$bucket] = ($byStatusChart[$bucket] ?? 0) + 1;

            if (! $isFinal && $ticket->due_date && $ticket->due_date->lt($today)) {
                $overdue++;
            }

            if ($ticket->closed_at && $ticket->created_at) {
                $closeDurations[] = $ticket->created_at->diffInDays($ticket->closed_at);
            }

            $ticketExpense = (float) $ticket->purchaseRequisitions->sum('amount');
            $estExpense += $ticketExpense;

            $ticketPaidExpense = 0.0;
            $hasPr = $ticket->purchaseRequisitions->isNotEmpty();

            foreach ($ticket->purchaseRequisitions as $pr) {
                $amount = (float) $pr->amount;
                if ($this->isDashboardPrPaid($pr, $paymentStatsByPr)) {
                    $paidPrCount++;
                    $ticketPaidExpense += $amount;
                } else {
                    $stat = $paymentStatsByPr->get($pr->id);
                    if (((int) ($stat->total_payments ?? 0)) > 0) {
                        $onProcessPrCount++;
                    }
                }
            }

            $paidExpense += $ticketPaidExpense;

            if ($hasPr) {
                $withPr++;
            } else {
                $noPr++;
            }

            if ($ticket->work_executor_type === Ticket::WORK_EXECUTOR_EXTERNAL_VENDOR) {
                $externalVendor++;
                $byExecutor['External Vendor'] = ($byExecutor['External Vendor'] ?? 0) + 1;
            } elseif ($ticket->work_executor_type === Ticket::WORK_EXECUTOR_INTERNAL) {
                $internal++;
                $byExecutor['Internal'] = ($byExecutor['Internal'] ?? 0) + 1;
            } else {
                $unsetExecutor++;
                $byExecutor['Belum diisi'] = ($byExecutor['Belum diisi'] ?? 0) + 1;
            }

            $catName = $ticket->category?->name ?? 'Tanpa Kategori';
            $byCategory[$catName] = ($byCategory[$catName] ?? 0) + 1;
            $expenseByCategory[$catName] = ($expenseByCategory[$catName] ?? 0) + $ticketExpense;

            $priName = $ticket->priority?->name ?? 'Tanpa Prioritas';
            $byPriority[$priName] = ($byPriority[$priName] ?? 0) + 1;

            $outName = $ticket->outlet?->nama_outlet ?? 'Tanpa Outlet';
            $byOutlet[$outName] = ($byOutlet[$outName] ?? 0) + 1;
            $expenseByOutlet[$outName] = ($expenseByOutlet[$outName] ?? 0) + $ticketExpense;

            $divName = $ticket->divisi?->nama_divisi ?? 'Tanpa Divisi';
            $byDivisi[$divName] = ($byDivisi[$divName] ?? 0) + 1;

            if ($ticket->created_at) {
                $dayKey = $ticket->created_at->format('Y-m-d');
                $dailyMap[$dayKey] = ($dailyMap[$dayKey] ?? 0) + 1;
            }

            if ($ticketExpense > 0) {
                $topExpenseTickets[] = [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'title' => $ticket->title,
                    'outlet' => $outName,
                    'divisi' => $divName,
                    'est_expense' => $ticketExpense,
                    'paid_expense' => $ticketPaidExpense,
                    'status' => $ticket->status?->name ?? '-',
                ];
            }

            $this->accumulateDashboardTeamStats($teamStats, $ticket, $slug, $isClosed);
        }

        $completionByTeam = $this->buildDashboardCompletionByTeam($teamStats);

        $total = $tickets->count();
        $completionRate = $total > 0 ? round(($closed / $total) * 100, 1) : 0.0;
        $openRate = $total > 0 ? round(($open / $total) * 100, 1) : 0.0;
        $inProgressRate = $total > 0 ? round(($inProgress / $total) * 100, 1) : 0.0;
        $closedRate = $completionRate;
        $overdueRate = $total > 0 ? round(($overdue / $total) * 100, 1) : 0.0;
        $prCoverageRate = $total > 0 ? round(($withPr / $total) * 100, 1) : 0.0;
        $paidExpenseRate = $estExpense > 0 ? round(($paidExpense / $estExpense) * 100, 1) : 0.0;

        usort($topExpenseTickets, fn ($a, $b) => $b['est_expense'] <=> $a['est_expense']);
        $topExpenseTickets = array_slice($topExpenseTickets, 0, 10);

        $result = [
            'total_tickets' => $total,
            'open' => $open,
            'in_progress' => $inProgress,
            'closed' => $closed,
            'overdue' => $overdue,
            'with_pr' => $withPr,
            'no_pr' => $noPr,
            'paid_pr_count' => $paidPrCount,
            'on_process_pr_count' => $onProcessPrCount,
            'internal_executor' => $internal,
            'external_vendor_executor' => $externalVendor,
            'unset_executor' => $unsetExecutor,
            'avg_close_days' => count($closeDurations) > 0
                ? round(array_sum($closeDurations) / count($closeDurations), 1)
                : 0,
            'progress' => [
                'completion_rate' => $completionRate,
                'open_rate' => $openRate,
                'in_progress_rate' => $inProgressRate,
                'closed_rate' => $closedRate,
                'overdue_rate' => $overdueRate,
                'pr_coverage_rate' => $prCoverageRate,
                'paid_expense_rate' => $paidExpenseRate,
            ],
            'expenses' => [
                'est_expense' => round($estExpense, 2),
                'paid_expense' => round($paidExpense, 2),
                'pending_expense' => round(max(0, $estExpense - $paidExpense), 2),
            ],
            'completion_by_team' => $completionByTeam,
        ];

        if (! $withCharts) {
            return $result;
        }

        $dailyTrend = [];
        if ($periodStart && $periodEnd) {
            $cursor = $periodStart->copy();
            while ($cursor->lte($periodEnd)) {
                $key = $cursor->toDateString();
                $dailyTrend[] = [
                    'date' => $key,
                    'label' => $cursor->format('d M'),
                    'count' => (int) ($dailyMap[$key] ?? 0),
                ];
                $cursor->addDay();
            }
        }

        $result['charts'] = [
            'status_distribution' => $this->dashboardChartPairs($byStatusChart),
            'daily_trend' => $dailyTrend,
            'by_category' => $this->dashboardChartPairs($byCategory),
            'by_priority' => $this->dashboardChartPairs($byPriority),
            'by_outlet' => $this->dashboardChartPairs($byOutlet, 12),
            'by_divisi' => $this->dashboardChartPairs($byDivisi),
            'by_executor' => $this->dashboardChartPairs($byExecutor),
            'expense_by_outlet' => $this->dashboardChartPairs($expenseByOutlet, 12, true),
            'expense_by_category' => $this->dashboardChartPairs($expenseByCategory, 10, true),
            'payment_funnel' => [
                ['label' => 'Tanpa PR', 'value' => $noPr],
                ['label' => 'Dengan PR', 'value' => $withPr],
                ['label' => 'PR On Process', 'value' => $onProcessPrCount],
                ['label' => 'PR Paid', 'value' => $paidPrCount],
            ],
            'team_completion_rate' => array_map(
                fn (array $row) => ['label' => $row['name'], 'value' => $row['completion_rate']],
                $completionByTeam
            ),
            'team_workload' => $completionByTeam,
        ];
        $result['top_expense_tickets'] = $topExpenseTickets;

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $teamStats
     */
    private function accumulateDashboardTeamStats(array &$teamStats, Ticket $ticket, string $slug, bool $isClosed): void
    {
        $assignees = $ticket->relationLoaded('assignedUsers')
            ? $ticket->assignedUsers
            : collect();

        $targets = $assignees->isNotEmpty()
            ? $assignees->map(fn ($user) => ['id' => (int) $user->id, 'name' => $user->nama_lengkap ?? 'User #' . $user->id])
            : collect([['id' => 0, 'name' => 'Belum di-assign']]);

        foreach ($targets as $target) {
            $key = (int) $target['id'];
            if (! isset($teamStats[$key])) {
                $teamStats[$key] = [
                    'user_id' => $key,
                    'name' => (string) $target['name'],
                    'total' => 0,
                    'closed' => 0,
                    'open' => 0,
                    'in_progress' => 0,
                ];
            }

            $teamStats[$key]['total']++;
            if ($isClosed) {
                $teamStats[$key]['closed']++;
            } elseif ($slug === 'in_progress') {
                $teamStats[$key]['in_progress']++;
            } else {
                $teamStats[$key]['open']++;
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $teamStats
     * @return array<int, array<string, mixed>>
     */
    private function buildDashboardCompletionByTeam(array $teamStats): array
    {
        $rows = [];
        foreach ($teamStats as $stat) {
            $total = (int) $stat['total'];
            $closed = (int) $stat['closed'];
            $rows[] = array_merge($stat, [
                'completion_rate' => $total > 0 ? round(($closed / $total) * 100, 1) : 0.0,
            ]);
        }

        usort($rows, function (array $a, array $b) {
            $rateCmp = $b['completion_rate'] <=> $a['completion_rate'];
            if ($rateCmp !== 0) {
                return $rateCmp;
            }

            return $b['total'] <=> $a['total'];
        });

        return array_values($rows);
    }

    /**
     * @param  array<string, int|float>  $map
     * @return array<int, array<string, mixed>>
     */
    private function dashboardChartPairs(array $map, int $limit = 0, bool $sortByValue = false): array
    {
        $pairs = [];
        foreach ($map as $label => $value) {
            $pairs[] = ['label' => (string) $label, 'value' => round((float) $value, 2)];
        }

        usort($pairs, fn ($a, $b) => $b['value'] <=> $a['value']);

        if ($limit > 0) {
            $pairs = array_slice($pairs, 0, $limit);
        }

        return array_values($pairs);
    }

    /**
     * @return array<string, float|int|null>
     */
    private function dashboardDelta(float|int $current, float|int $previous): array
    {
        $diff = $current - $previous;
        $pct = $previous != 0 ? round(($diff / $previous) * 100, 1) : ($current > 0 ? 100.0 : 0.0);

        return [
            'current' => $current,
            'previous' => $previous,
            'diff' => $diff,
            'pct' => $pct,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTicketReportFilters(Request $request): array
    {
        return [
            'search' => (string) $request->get('search', ''),
            'status' => (string) $request->get('status', 'all'),
            'priority' => (string) $request->get('priority', 'all'),
            'category' => (string) $request->get('category', 'all'),
            'division' => (string) $request->get('division', 'all'),
            'outlet' => (string) $request->get('outlet', 'all'),
            'issue_type' => (string) $request->get('issue_type', 'all'),
            'date_from' => (string) $request->get('date_from', ''),
            'date_to' => (string) $request->get('date_to', ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketReportFilterOptions(): array
    {
        return [
            'categories' => TicketCategory::active()->orderBy('name')->get(['id', 'name']),
            'priorities' => TicketPriority::active()->orderBy('level', 'desc')->get(['id', 'name']),
            'statuses' => $this->selectableTicketStatuses(),
            'divisions' => Divisi::orderBy('nama_divisi')->get(['id', 'nama_divisi']),
            'outlets' => Outlet::orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Support\Collection<int, Ticket>
     */
    private function fetchTicketsForReport(Request $request, array $filters)
    {
        $query = Ticket::with([
            'category:id,name',
            'status:id,name,slug,is_final',
            'priority:id,name,level',
            'outlet:id_outlet,nama_outlet',
            'attachments',
            'comments' => function ($q) {
                $q->orderBy('created_at');
            },
            'purchaseRequisitions:id,ticket_id,amount',
        ]);

        $this->applyTicketOutletVisibility($query, $request->user());
        $this->applyTicketIssueTypeFilter($query, (string) ($filters['issue_type'] ?? 'all'));

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if (($filters['status'] ?? 'all') !== 'all') {
            $this->applyTicketStatusFilter($query, (string) $filters['status']);
        }
        if (($filters['priority'] ?? 'all') !== 'all') {
            $query->where('priority_id', $filters['priority']);
        }
        if (($filters['category'] ?? 'all') !== 'all') {
            $query->where('category_id', $filters['category']);
        }
        if (($filters['division'] ?? 'all') !== 'all') {
            $query->where('divisi_id', $filters['division']);
        }
        if (($filters['outlet'] ?? 'all') !== 'all') {
            $query->where('outlet_id', $filters['outlet']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (($filters['work_executor_type'] ?? 'all') !== 'all') {
            $query->where('work_executor_type', $filters['work_executor_type']);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function buildReportPerCategoriesData(Request $request, array $filters): array
    {
        $tickets = $this->fetchTicketsForReport($request, $filters)
            ->sortBy([
                ['category_id', 'asc'],
                ['created_at', 'asc'],
            ])
            ->values();

        $grouped = $tickets->groupBy(fn (Ticket $ticket) => (int) ($ticket->category_id ?? 0));

        $groups = [];
        foreach ($grouped as $categoryId => $categoryTickets) {
            $categoryName = $categoryTickets->first()->category?->name ?? 'Uncategorized';
            $rows = [];
            $no = 1;
            foreach ($categoryTickets as $ticket) {
                $rows[] = $this->mapTicketToCategoryReportRow($ticket, $no++);
            }
            $groups[] = [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'rows' => $rows,
            ];
        }

        usort($groups, fn ($a, $b) => strcasecmp((string) $a['category_name'], (string) $b['category_name']));

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function buildReportPerOutletsData(Request $request, array $filters): array
    {
        $tickets = $this->fetchTicketsForReport($request, $filters)
            ->sortBy([
                fn (Ticket $t) => $t->outlet?->nama_outlet ?? '',
                ['created_at', 'asc'],
            ])
            ->values();

        $grouped = $tickets->groupBy(fn (Ticket $ticket) => (int) ($ticket->outlet_id ?? 0));

        $groups = [];
        foreach ($grouped as $outletId => $outletTickets) {
            $outletName = $outletTickets->first()->outlet?->nama_outlet ?? 'Uncategorized';
            $rows = [];
            $no = 1;
            foreach ($outletTickets as $ticket) {
                $rows[] = $this->mapTicketToOutletReportRow($ticket, $no++);
            }
            $groups[] = [
                'outlet_id' => $outletId,
                'outlet_name' => $outletName,
                'rows' => $rows,
            ];
        }

        usort($groups, fn ($a, $b) => strcasecmp((string) $a['outlet_name'], (string) $b['outlet_name']));

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildReportExternalVendorData(Request $request, array $filters): array
    {
        $filters['work_executor_type'] = Ticket::WORK_EXECUTOR_EXTERNAL_VENDOR;
        $tickets = $this->fetchTicketsForReport($request, $filters)
            ->sortBy([
                fn (Ticket $t) => $t->outlet?->nama_outlet ?? '',
                ['created_at', 'asc'],
            ])
            ->values();

        $rows = [];
        $no = 1;
        foreach ($tickets as $ticket) {
            $rows[] = $this->mapTicketToOutletReportRow($ticket, $no++);
        }

        return [
            'rows' => $rows,
            'total' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTicketToCategoryReportRow(Ticket $ticket, int $no): array
    {
        $complainAttachment = $this->firstTicketReportImageAttachment($ticket, 'complain');
        $resultAttachment = $this->firstTicketReportImageAttachment($ticket, 'result');

        return [
            'no' => $no,
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'tanggal' => optional($ticket->created_at)->format('j/n/Y'),
            'finding_problem' => trim((string) ($ticket->title ?: $ticket->description)),
            'complain_image' => $this->ticketAttachmentPublicUrl($complainAttachment),
            'complain_image_path' => $complainAttachment?->file_path,
            'result_image' => $this->ticketAttachmentPublicUrl($resultAttachment),
            'result_image_path' => $resultAttachment?->file_path,
            'remark' => $this->buildTicketCategoryReportRemark($ticket),
            'status_name' => $ticket->status?->name,
            'status_slug' => $ticket->status?->slug,
            'notes' => $this->extractTicketCloseNote($ticket),
            'closed_at' => $ticket->closed_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTicketToOutletReportRow(Ticket $ticket, int $no): array
    {
        $base = $this->mapTicketToCategoryReportRow($ticket, $no);
        $documentationImages = $this->ticketReportComplainImages($ticket);
        $estExpense = (float) $ticket->purchaseRequisitions->sum('amount');

        return array_merge($base, [
            'documentation_images' => $documentationImages,
            'handled_internal' => $ticket->work_executor_type === Ticket::WORK_EXECUTOR_INTERNAL,
            'handled_vendor' => $ticket->work_executor_type === Ticket::WORK_EXECUTOR_EXTERNAL_VENDOR,
            'work_executor_type' => $ticket->work_executor_type,
            'work_executor_type_label' => $this->workExecutorTypeLabel($ticket->work_executor_type),
            'vendor_name' => $ticket->vendor_name,
            'outlet_name' => $ticket->outlet?->nama_outlet,
            'est_expense' => $estExpense,
            'est_expense_formatted' => $estExpense > 0
                ? 'Rp ' . number_format($estExpense, 0, ',', '.')
                : '-',
            'priority_name' => $ticket->priority?->name,
            'priority_level' => $ticket->priority?->level,
            'category_name' => $ticket->category?->name,
        ]);
    }

    /**
     * @return array<int, array{url: string|null, path: string|null}>
     */
    private function ticketReportComplainImages(Ticket $ticket): array
    {
        $attachments = $ticket->attachments ?? collect();

        return $attachments
            ->filter(function (TicketAttachment $att) {
                if ($att->comment_id !== null) {
                    return false;
                }
                $path = (string) $att->file_path;
                if (str_contains($path, 'ticket-close-evidence')) {
                    return false;
                }

                return $this->isImageTicketAttachment($att);
            })
            ->map(fn (TicketAttachment $att) => [
                'url' => $this->ticketAttachmentPublicUrl($att),
                'path' => $att->file_path,
            ])
            ->values()
            ->all();
    }

    private function buildTicketCategoryReportRemark(Ticket $ticket): string
    {
        $lines = [];
        $statusName = trim((string) ($ticket->status?->name ?? '-'));
        if ($statusName !== '') {
            $lines[] = $statusName;
        }

        $notes = $this->extractTicketCloseNote($ticket);
        if ($notes !== null && $notes !== '') {
            $lines[] = $notes;
        }

        if ($ticket->status?->slug === 'closed' && $ticket->closed_at) {
            $lines[] = $ticket->closed_at->format('d/m/Y H:i');
        }

        return implode("\n", $lines);
    }

    private function extractTicketCloseNote(Ticket $ticket): ?string
    {
        foreach ($ticket->comments as $comment) {
            $text = trim((string) $comment->comment);
            if (str_starts_with($text, 'Ticket ditutup:')) {
                $note = trim(substr($text, strlen('Ticket ditutup:')));
                return $note !== '' ? $note : null;
            }
        }

        return null;
    }

    private function firstTicketReportImageAttachment(Ticket $ticket, string $type): ?TicketAttachment
    {
        $attachments = $ticket->attachments ?? collect();

        if ($type === 'complain') {
            $candidate = $attachments->first(function (TicketAttachment $att) {
                if ($att->comment_id !== null) {
                    return false;
                }
                $path = (string) $att->file_path;
                if (str_contains($path, 'ticket-close-evidence')) {
                    return false;
                }

                return $this->isImageTicketAttachment($att);
            });

            if ($candidate) {
                return $candidate;
            }

            return $attachments->first(function (TicketAttachment $att) {
                $path = (string) $att->file_path;
                if (str_contains($path, 'ticket-close-evidence')) {
                    return false;
                }

                return $this->isImageTicketAttachment($att);
            });
        }

        return $attachments->first(function (TicketAttachment $att) {
            return str_contains((string) $att->file_path, 'ticket-close-evidence')
                && $this->isImageTicketAttachment($att);
        });
    }

    private function isImageTicketAttachment(TicketAttachment $attachment): bool
    {
        $mime = strtolower((string) ($attachment->mime_type ?? ''));
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        $name = strtolower((string) ($attachment->file_name ?: $attachment->file_path));
        return (bool) preg_match('/\.(jpe?g|png|webp|gif|heic)$/i', $name);
    }

    private function ticketAttachmentPublicUrl(?TicketAttachment $attachment): ?string
    {
        if (! $attachment || ! $attachment->file_path) {
            return null;
        }
        $path = ltrim((string) $attachment->file_path, '/');
        if (str_starts_with($path, 'storage/')) {
            return '/' . $path;
        }

        return '/storage/' . $path;
    }

    private function ticketAttachmentFilesystemPath(?string $filePath): ?string
    {
        if (! $filePath) {
            return null;
        }
        $path = ltrim($filePath, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }
        $full = storage_path('app/public/' . $path);

        return is_file($full) ? $full : null;
    }

    private function embedTicketReportImage(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $column,
        int $row,
        ?string $filePath
    ): void {
        $fullPath = $this->ticketAttachmentFilesystemPath($filePath);
        if (! $fullPath) {
            return;
        }

        try {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setPath($fullPath);
            $drawing->setCoordinates($column . $row);
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(4);
            $drawing->setHeight(88);
            $drawing->setWorksheet($sheet);
        } catch (\Throwable $e) {
            // Skip broken/missing image files in export.
        }
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
            ]);
        $this->applyTicketOutletVisibility($tickets, $request->user());
        $tickets = $tickets
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
     * @return array<string, mixed>
     */
    private function closeEvidenceValidationRules(): array
    {
        return [
            'close_note' => 'nullable|string|max:1000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,pdf,doc,docx',
        ];
    }

    /**
     * Optional evidence when closing a ticket (files and/or note).
     */
    private function handleCloseEvidence(Ticket $ticket, Request $request): void
    {
        $closeNote = trim((string) $request->input('close_note', ''));
        $hasFiles = $request->hasFile('attachments');

        if ($closeNote === '' && ! $hasFiles) {
            return;
        }

        $commentText = 'Ticket ditutup';
        if ($closeNote !== '') {
            $commentText .= ': ' . $closeNote;
        }

        $comment = \App\Models\TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'comment' => $commentText,
            'is_internal' => false,
        ]);

        if ($hasFiles) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('ticket-close-evidence', 'public');
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

        $historyDescription = $hasFiles
            ? 'Ticket ditutup dengan evidence (' . count($request->file('attachments')) . ' file)'
            : 'Ticket ditutup dengan catatan penutupan';
        $this->createTicketHistory($ticket, 'closed', null, null, $historyDescription);
    }

    private function selectableTicketStatuses()
    {
        return TicketStatus::active()->selectable()->ordered()->get();
    }

    private function applyTicketStatusFilter($query, string $status): void
    {
        if ($status === 'all') {
            return;
        }

        if ($status === 'resolved') {
            $status = 'closed';
        }

        if ($status === 'closed') {
            $query->whereHas('status', function ($q) {
                $q->whereIn('slug', ['closed', 'resolved']);
            });

            return;
        }

        $query->whereHas('status', function ($q) use ($status) {
            $q->where('slug', $status);
        });
    }

    /**
     * Show the form for creating a new ticket
     */
    public function create()
    {
        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        $divisis = Divisi::active()->orderBy('nama_divisi')->get();
        $outlets = $this->outletsForTicketUser(auth()->user());

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

            // Notification and assignment are now handled by team settings.
            $this->autoAssignTeamFromSetting($ticket);

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
            'ticket' => $this->buildTicketDetailArray((int) $id, auth()->user()),
            'can_manage_tickets' => self::userCanManageTickets(auth()->user()),
        ]);
    }

    /**
     * Landing page publik — tanpa login, via share token.
     */
    public function publicShow(string $token)
    {
        $ticket = $this->loadTicketModelForDetail()
            ->where('share_token', $token)
            ->firstOrFail();

        return Inertia::render('Tickets/PublicShow', [
            'ticket' => $this->enrichTicketDetailPayload($ticket),
        ]);
    }

    /**
     * Generate / kembalikan link share untuk WhatsApp.
     */
    public function generateShareLink(Request $request, $id)
    {
        $ticket = Ticket::with('outlet')->findOrFail($id);

        if (! self::userCanViewTicket($request->user(), $ticket)) {
            abort(404);
        }

        $shareToken = $ticket->ensureShareToken();
        $url = route('tickets.public.show', $shareToken);

        return response()->json([
            'success' => true,
            'url' => $url,
            'message' => $this->buildTicketShareMessage($ticket, $url),
        ]);
    }

    protected function buildTicketShareMessage(Ticket $ticket, string $url): string
    {
        $outletName = trim((string) ($ticket->outlet?->nama_outlet ?? ''));
        $line = 'Ticket ' . $ticket->ticket_number;
        if ($outletName !== '') {
            $line .= ' - ' . $outletName;
        }
        $line .= ': ' . $ticket->title;

        return $line . "\n" . $url;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Ticket>
     */
    protected function loadTicketModelForDetail()
    {
        return Ticket::with([
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
        ]);
    }

    /**
     * Payload detail ticket (web + Approval App API).
     */
    protected function buildTicketDetailArray(int $id, $user): array
    {
        $ticket = $this->loadTicketModelForDetail()->findOrFail($id);

        if (! self::userCanViewTicket($user, $ticket)) {
            abort(404);
        }

        return $this->enrichTicketDetailPayload($ticket, $user);
    }

    protected function enrichTicketDetailPayload(Ticket $ticket, $user = null): array
    {
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

        if ($user) {
            $ticketData['can_update_status'] = self::userCanUpdateTicketStatus($user, $ticket);
            $ticketData['can_set_work_executor_type'] = self::userCanSetWorkExecutorType($user, $ticket);
            $ticketData['can_update_vendor_name'] = self::userCanUpdateVendorName($user, $ticket);
            $ticketData['can_manage_ticket'] = self::userCanManageTicket($user, $ticket);
        }
        $ticketData['work_executor_type_label'] = $this->workExecutorTypeLabel($ticket->work_executor_type);

        return $ticketData;
    }

    /**
     * Detail ticket — Approval App (JSON).
     */
    public function apiShow(Request $request, $id)
    {
        return response()->json([
            'success' => true,
            'can_manage_tickets' => self::userCanManageTickets($request->user()),
            'ticket' => $this->buildTicketDetailArray((int) $id, $request->user()),
        ]);
    }

    /**
     * Opsi form (create/edit) + assign — Approval App.
     */
    public function apiFormOptions(Request $request)
    {
        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        $statuses = $this->selectableTicketStatuses();
        $divisis = Divisi::active()->orderBy('nama_divisi')->get();
        $outletsQuery = Outlet::active()->orderBy('nama_outlet');
        $user = $request->user();
        if (! self::userSeesAllTicketOutlets($user)) {
            $oid = $user?->id_outlet;
            if ($oid !== null && $oid !== '') {
                $outletsQuery->where('id_outlet', (int) $oid);
            }
        }
        $outlets = $outletsQuery->get();
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
        $issueType = $request->get('issue_type', 'all');
        $perPage = (int) $request->get('per_page', 15);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

        $apiUser = $request->user();

        $query = Ticket::with([
            'category',
            'priority',
            'status',
            'divisi',
            'outlet',
            'creator',
            'assignedUsers:id,nama_lengkap,avatar',
        ])->withCount('comments');

        $this->applyTicketOutletVisibility($query, $request->user());
        $this->applyTicketIssueTypeFilter($query, (string) $issueType);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status !== 'all') {
            $this->applyTicketStatusFilter($query, (string) $status);
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

        $tickets->getCollection()->transform(function ($ticket) use ($prsByTicket, $paymentStatsByPr, $apiUser) {
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

            $ticket->can_update_status = self::userCanUpdateTicketStatus($apiUser, $ticket);
            $this->enrichTicketWorkExecutorFields($ticket, $apiUser);

            return $ticket;
        });

        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        $statuses = $this->selectableTicketStatuses();
        $divisis = Divisi::whereHas('tickets', function ($q) use ($apiUser) {
            $this->applyTicketOutletVisibility($q, $apiUser);
        })->active()->orderBy('nama_divisi')->get();
        $outletsQuery = Outlet::active()->orderBy('nama_outlet');
        if (! self::userSeesAllTicketOutlets($apiUser)) {
            $oid = $apiUser?->id_outlet;
            if ($oid !== null && $oid !== '') {
                $outletsQuery->where('id_outlet', (int) $oid);
            }
        }
        $outlets = $outletsQuery->get();
        $assignableUsers = User::where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'avatar', 'division_id']);

        $statsBase = Ticket::query();
        $this->applyTicketOutletVisibility($statsBase, $apiUser);
        $this->applyTicketIssueTypeFilter($statsBase, (string) $issueType);
        $statistics = [
            'total' => (clone $statsBase)->count(),
            'open' => (clone $statsBase)->open()->count(),
            'in_progress' => (clone $statsBase)->inProgress()->count(),
            'closed' => (clone $statsBase)->closed()->count(),
        ];

        return response()->json([
            'success' => true,
            'can_manage_tickets' => self::userCanManageTickets($request->user()),
            'tickets_view_all_outlets' => self::userSeesAllTicketOutlets($request->user()),
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

        if (! self::userCanViewTicket(auth()->user(), $ticket)) {
            abort(404);
        }

        if (! self::userCanManageTicket(auth()->user(), $ticket)) {
            abort(403, 'Anda tidak memiliki izin mengedit ticket.');
        }

        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        $statuses = $this->selectableTicketStatuses();
        $divisis = Divisi::active()->orderBy('nama_divisi')->get();
        $outlets = $this->outletsForTicketUser(auth()->user());

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

        if (! self::userCanViewTicket($request->user(), $ticket)) {
            return $this->ticketViewDeniedJsonResponse();
        }

        if (! self::userCanManageTicket($request->user(), $ticket)) {
            return $this->ticketManageDeniedJsonResponse();
        }

        $validator = Validator::make($request->all(), array_merge([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:ticket_categories,id',
            'priority_id' => 'required|exists:ticket_priorities,id',
            'status_id' => 'required|exists:ticket_statuses,id',
            'divisi_id' => 'required|exists:tbl_data_divisi,id',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
        ], $this->closeEvidenceValidationRules()));

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
            $newStatus = TicketStatus::active()->selectable()->where('id', $request->status_id)->first();
            if (! $newStatus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status tidak valid atau tidak tersedia.',
                ], 422);
            }

            if ((int) $oldData['status_id'] !== (int) $newStatus->id
                && ! self::userCanUpdateTicketStatus($request->user(), $ticket)) {
                return $this->ticketStatusUpdateDeniedJsonResponse();
            }

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
                'status_id' => $newStatus->id,
                'divisi_id' => $request->divisi_id,
                'outlet_id' => $request->outlet_id,
                'due_date' => $dueDate,
            ], $closedAtAttrs));

            if ($oldStatusSlug !== 'closed' && $newStatus->slug === 'closed') {
                $this->handleCloseEvidence($ticket, $request);
            }

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
     * Set dikerjakan oleh: internal atau external vendor (divisi terkait saja).
     */
    public function updateWorkExecutorType(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);

        if (! self::userCanViewTicket($request->user(), $ticket)) {
            return $this->ticketViewDeniedJsonResponse();
        }

        if (! self::userCanSetWorkExecutorType($request->user(), $ticket)) {
            return $this->ticketWorkExecutorDeniedJsonResponse();
        }

        $validator = Validator::make($request->all(), [
            'work_executor_type' => 'nullable|in:internal,external_vendor',
            'vendor_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldType = $ticket->work_executor_type;
        $oldVendorName = $ticket->vendor_name;
        $newType = $request->input('work_executor_type');
        $newType = $newType === '' ? null : $newType;
        $vendorName = trim((string) $request->input('vendor_name', ''));
        $vendorName = $vendorName !== '' ? $vendorName : null;

        $typeChanged = $oldType !== $newType;
        $vendorChanged = false;

        if (! $typeChanged && $newType === Ticket::WORK_EXECUTOR_EXTERNAL_VENDOR) {
            $oldVendor = $ticket->vendor_name;
            $vendorChanged = $oldVendor !== $vendorName;
            if ($vendorChanged && ! self::userCanUpdateVendorName($request->user(), $ticket)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin mengubah nama vendor.',
                ], 403);
            }
        }

        if (! $typeChanged && ! $vendorChanged) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada perubahan',
                'data' => $ticket,
            ]);
        }

        $updateData = [];
        if ($typeChanged) {
            $updateData['work_executor_type'] = $newType;
            if ($newType === Ticket::WORK_EXECUTOR_EXTERNAL_VENDOR) {
                $updateData['vendor_name'] = $vendorName;
            } else {
                $updateData['vendor_name'] = null;
            }
        } elseif ($vendorChanged) {
            $updateData['vendor_name'] = $vendorName;
        }

        $ticket->update($updateData);

        if ($typeChanged) {
            $oldLabel = $this->workExecutorTypeLabel($oldType) ?? '-';
            $newLabel = $this->workExecutorTypeLabel($newType) ?? '-';
            $this->createTicketHistory(
                $ticket,
                'updated',
                'work_executor_type',
                $oldLabel,
                "Dikerjakan oleh: {$oldLabel} → {$newLabel}"
            );
        }
        if ($vendorChanged) {
            $this->createTicketHistory(
                $ticket,
                'updated',
                'vendor_name',
                $oldVendorName ?? '-',
                'Nama vendor: ' . ($vendorName ?? '-')
            );
        }

        $ticket->refresh();
        $this->enrichTicketWorkExecutorFields($ticket, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Dikerjakan oleh berhasil diperbarui',
            'data' => $ticket,
        ]);
    }

    /**
     * Update nama vendor (opsional) — ticket external vendor saja.
     */
    public function updateVendorName(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);

        if (! self::userCanViewTicket($request->user(), $ticket)) {
            return $this->ticketViewDeniedJsonResponse();
        }

        if (! $ticket->isExternalVendorTicket()) {
            return response()->json([
                'success' => false,
                'message' => 'Nama vendor hanya untuk ticket external vendor.',
            ], 422);
        }

        if (! self::userCanUpdateVendorName($request->user(), $ticket)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin mengubah nama vendor.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'vendor_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $vendorName = trim((string) $request->input('vendor_name', ''));
        $vendorName = $vendorName !== '' ? $vendorName : null;
        $oldVendor = $ticket->vendor_name;

        if ($oldVendor === $vendorName) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada perubahan',
                'data' => $ticket,
            ]);
        }

        $ticket->update(['vendor_name' => $vendorName]);
        $this->createTicketHistory(
            $ticket,
            'updated',
            'vendor_name',
            $oldVendor ?? '-',
            'Nama vendor: ' . ($vendorName ?? '-')
        );

        $ticket->refresh();
        $this->enrichTicketWorkExecutorFields($ticket, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Nama vendor berhasil diperbarui',
            'data' => $ticket,
        ]);
    }

    /**
     * Quick status change from ticket list (no full edit form).
     */
    public function updateStatus(Request $request, $id)
    {
        $ticket = Ticket::with('status')->findOrFail($id);

        if (! self::userCanViewTicket($request->user(), $ticket)) {
            return $this->ticketViewDeniedJsonResponse();
        }

        if (! self::userCanUpdateTicketStatus($request->user(), $ticket)) {
            return $this->ticketStatusUpdateDeniedJsonResponse();
        }

        $validator = Validator::make($request->all(), array_merge([
            'status_id' => 'required|exists:ticket_statuses,id',
        ], $this->closeEvidenceValidationRules()));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newStatus = TicketStatus::active()->selectable()->where('id', $request->status_id)->first();
        if (! $newStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Status tidak valid atau tidak tersedia.',
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

            if ($oldStatusSlug !== 'closed' && $newStatus->slug === 'closed') {
                $this->handleCloseEvidence($ticket, $request);
            }

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
        if (!self::userCanManageTickets(auth()->user())) {
            return $this->ticketManageDeniedJsonResponse();
        }

        $ticket = Ticket::findOrFail($id);

        if (! self::userCanViewTicket(auth()->user(), $ticket)) {
            return $this->ticketViewDeniedJsonResponse();
        }

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

            $this->autoAssignTeamFromSetting($ticket);

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

        if (! self::userCanViewTicket($request->user(), $ticket)) {
            return $this->ticketViewDeniedJsonResponse();
        }

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

        if (! self::userCanViewTicket(auth()->user(), $ticket)) {
            return $this->ticketViewDeniedJsonResponse();
        }

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
        $comment = \App\Models\TicketComment::with('ticket')->findOrFail($id);

        if (! self::userCanViewTicket($request->user(), $comment->ticket)) {
            return $this->ticketViewDeniedJsonResponse();
        }

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
        $comment = \App\Models\TicketComment::with('ticket')->findOrFail($id);

        if (! self::userCanViewTicket(auth()->user(), $comment->ticket)) {
            return $this->ticketViewDeniedJsonResponse();
        }

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

        if (! self::userCanViewTicket($request->user(), $ticket)) {
            return $this->ticketViewDeniedJsonResponse();
        }

        if (! self::userCanManageTicket($request->user(), $ticket)) {
            return $this->ticketManageDeniedJsonResponse();
        }

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
     * Auto-assign team from ticketing team settings (category + outlet/region).
     */
    protected function autoAssignTeamFromSetting(Ticket $ticket): void
    {
        try {
            app(TicketTeamAutoAssignService::class)->assignIfMatch($ticket, auth()->id());
        } catch (\Throwable $e) {
            \Log::error('Ticket auto-assign from team setting failed', [
                'ticket_id' => $ticket->id,
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

        if (! self::userSeesAllTicketOutlets($request->user())) {
            $uid = $request->user()?->id_outlet;
            if ($uid === null || $uid === '' || (int) $outletId !== (int) $uid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak',
                ], 403);
            }
        }

        $proposedTitle = $this->normalizeTicketTitle((string) $request->get('title', ''));

        $tickets = Ticket::with(['status', 'category', 'priority', 'divisi', 'outlet', 'creator:id,nama_lengkap,email'])
            ->where('outlet_id', $outletId)
            ->where('title', 'like', '%'.$area->nama_area.'%')
            ->whereHas('status', function ($q) {
                $q->whereIn('slug', ['open', 'in_progress']);
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (Ticket $ticket) use ($proposedTitle) {
                $normalizedTitle = $this->normalizeTicketTitle((string) $ticket->title);
                $ticket->is_same_title = $proposedTitle !== ''
                    && $normalizedTitle === $proposedTitle;

                return $ticket;
            })
            ->values();
        
        return response()->json([
            'success' => true,
            'tickets' => $tickets,
            'duplicate_count' => $tickets->where('is_same_title', true)->count(),
        ]);
    }

    /**
     * Get open tickets by outlet with optional title search.
     */
    public function getTicketsByOutlet(Request $request)
    {
        $outletId = (int) $request->get('outlet_id');
        if (! $outletId) {
            return response()->json([
                'success' => false,
                'message' => 'Outlet ID is required',
            ], 400);
        }

        if (! self::userSeesAllTicketOutlets($request->user())) {
            $uid = $request->user()?->id_outlet;
            if ($uid === null || $uid === '' || $outletId !== (int) $uid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak',
                ], 403);
            }
        }

        $proposedTitle = $this->normalizeTicketTitle((string) $request->get('title', ''));
        $search = trim((string) $request->get('q', ''));

        $query = Ticket::with([
            'status:id,name,slug',
            'category:id,name',
            'priority:id,name',
            'divisi:id,nama_divisi',
            'outlet:id_outlet,nama_outlet',
            'creator:id,nama_lengkap,email',
        ])
            ->where('outlet_id', $outletId)
            ->whereHas('status', function ($q) {
                $q->whereIn('slug', ['open', 'in_progress']);
            });

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', '%' . $search . '%')
                    ->orWhere('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $tickets = $query
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (Ticket $ticket) use ($proposedTitle) {
                $normalizedTitle = $this->normalizeTicketTitle((string) $ticket->title);
                $ticket->is_same_title = $proposedTitle !== '' && $normalizedTitle === $proposedTitle;

                return $ticket;
            })
            ->values();

        return response()->json([
            'success' => true,
            'tickets' => $tickets,
            'duplicate_count' => $tickets->where('is_same_title', true)->count(),
        ]);
    }

    private function normalizeTicketTitle(string $title): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($title));

        return strtolower((string) $normalized);
    }
}
