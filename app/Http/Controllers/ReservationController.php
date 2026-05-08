<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReservationsExport;
use App\Models\Reservation;
use App\Models\Outlet;
use App\Models\User;
use App\Models\PaymentType;

class ReservationController extends Controller
{
    private const DEFAULT_RESERVATION_DURATION_MINUTES = 150;

    /** Same-day reservations (public web/API): earliest slot is this many hours from now. */
    private const SAME_DAY_MIN_LEAD_HOURS = 4;

    /** Bump when public availability / date rules change (check response header on GET availability-layout). */
    private const PUBLIC_RESERVATION_AVAILABILITY_VERSION = 'v2-allow-today';

    public function index(Request $request)
    {
        $selfOrderTable = Schema::hasTable('web_self_orders') ? 'web_self_orders' : 'self_orders';
        $user = auth()->user();
        $userOutletId = $user->id_outlet ? (int) $user->id_outlet : null;
        $isAdminOutlet = ($userOutletId === 1 || $userOutletId === null);
        $allowedPerPages = [10, 25, 50, 100];
        $perPage = (int) $request->integer('per_page', 10);
        if (!in_array($perPage, $allowedPerPages, true)) {
            $perPage = 10;
        }

        $query = Reservation::with(['outlet', 'creator', 'salesUser', 'paymentType'])
            ->when(!$isAdminOutlet && $userOutletId, function ($query) use ($userOutletId) {
                $query->where('outlet_id', $userOutletId);
            })
            ->when($isAdminOutlet && $request->outlet_id, function ($query, $outletId) {
                $query->where('outlet_id', $outletId);
            })
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->dateFrom, function ($query, $dateFrom) {
                $query->whereDate('reservation_date', '>=', $dateFrom);
            })
            ->when($request->dateTo, function ($query, $dateTo) {
                $query->whereDate('reservation_date', '<=', $dateTo);
            })
            ->latest();

        $paginatedReservations = $query
            ->paginate($perPage)
            ->withQueryString();

        $reservationIds = collect($paginatedReservations->items())
            ->pluck('id')
            ->filter()
            ->values();

        $orderByReservation = collect();
        if ($reservationIds->isNotEmpty() && Schema::hasTable('orders') && Schema::hasColumn('orders', 'reservation_id')) {
            $orderByReservation = DB::table('orders')
                ->whereIn('reservation_id', $reservationIds)
                ->whereNotNull('reservation_id')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['reservation_id', 'paid_number', 'status', 'updated_at'])
                ->groupBy('reservation_id')
                ->map(function ($rows) {
                    $picked = $rows->first(function ($row) {
                        return $row->status === 'paid' && !empty($row->paid_number);
                    });

                    if (!$picked) {
                        $picked = $rows->first(function ($row) {
                            return !empty($row->paid_number);
                        });
                    }

                    if (!$picked) {
                        return [
                            'paid_number' => null,
                            'used_order_at' => null,
                        ];
                    }

                    return [
                        'paid_number' => $picked->paid_number,
                        'used_order_at' => $picked->updated_at ? Carbon::parse($picked->updated_at)->toIso8601String() : null,
                    ];
                });
        }

        $selfOrderByReservationNumber = collect();
        $selfOrderByReservationIdFallback = collect();
        $reservationNumbers = collect($paginatedReservations->items())
            ->pluck('reservation_number')
            ->filter(fn ($number) => is_string($number) && trim($number) !== '')
            ->map(fn ($number) => strtoupper(trim((string) $number)))
            ->unique()
            ->values();

        if (
            $reservationNumbers->isNotEmpty() &&
            Schema::hasTable($selfOrderTable) &&
            Schema::hasColumn($selfOrderTable, 'reservation_number')
        ) {
            $selfOrderByReservationNumber = DB::table($selfOrderTable)
                ->whereIn('reservation_number', $reservationNumbers)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['reservation_number', 'order_no', 'status', 'updated_at'])
                ->groupBy(function ($row) {
                    return strtoupper(trim((string) $row->reservation_number));
                })
                ->map(function ($rows) {
                    $latest = $rows->first();
                    return [
                        'count' => $rows->count(),
                        'latest_order_no' => $latest?->order_no,
                        'latest_status' => $latest?->status,
                        'latest_at' => $latest?->updated_at ? Carbon::parse($latest->updated_at)->toIso8601String() : null,
                    ];
                });
        }

        if (Schema::hasTable($selfOrderTable)) {
            $reservationMeta = collect($paginatedReservations->items())
                ->map(function ($reservation) {
                    return [
                        'id' => $reservation->id,
                        'name_norm' => $this->normalizeName((string) ($reservation->name ?? '')),
                        'outlet_id' => $reservation->outlet_id,
                        'created_at' => $reservation->created_at,
                        'phone_norm' => $this->normalizePhone((string) ($reservation->phone ?? '')),
                        'reservation_number' => strtoupper(trim((string) ($reservation->reservation_number ?? ''))),
                    ];
                })
                ->values();

            $outletIds = $reservationMeta->pluck('outlet_id')->filter()->unique()->values();
            $createdAtMin = $reservationMeta->pluck('created_at')->filter()->min();
            $createdAtMax = $reservationMeta->pluck('created_at')->filter()->max();

            if ($outletIds->isNotEmpty() && $createdAtMin && $createdAtMax) {
                $fallbackRows = DB::table($selfOrderTable)
                    ->whereIn('outlet_id', $outletIds)
                    ->whereBetween('created_at', [
                        Carbon::parse($createdAtMin)->subDays(7),
                        Carbon::parse($createdAtMax)->addDays(7),
                    ])
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->get([
                        'id',
                        'reservation_number',
                        'order_no',
                        'customer_name',
                        'status',
                        'updated_at',
                        'created_at',
                        'outlet_id',
                        'customer_phone',
                    ]);

                $selfOrderByReservationIdFallback = $reservationMeta->mapWithKeys(function ($meta) use ($fallbackRows, $selfOrderByReservationNumber) {
                    if (empty($meta['id']) || empty($meta['outlet_id'])) {
                        return [$meta['id'] => null];
                    }

                    if (!empty($meta['reservation_number']) && $selfOrderByReservationNumber->has($meta['reservation_number'])) {
                        return [$meta['id'] => null];
                    }

                    $createdAt = $meta['created_at'] ? Carbon::parse($meta['created_at']) : null;

                    $matched = $fallbackRows
                        ->filter(function ($row) use ($meta, $createdAt) {
                            if ((int) $row->outlet_id !== (int) $meta['outlet_id']) {
                                return false;
                            }

                            $phoneMatched = $this->isLikelySamePhone(
                                (string) ($row->customer_phone ?? ''),
                                (string) ($meta['phone_norm'] ?? '')
                            );
                            $nameMatched = !empty($meta['name_norm'])
                                && $this->normalizeName((string) ($row->customer_name ?? '')) === $meta['name_norm'];

                            if (!$phoneMatched && !$nameMatched) {
                                return false;
                            }

                            if (!$createdAt || empty($row->created_at)) {
                                return true;
                            }

                            $rowCreatedAt = Carbon::parse($row->created_at);
                            return $rowCreatedAt->between($createdAt->copy()->subDays(7), $createdAt->copy()->addDays(7));
                        })
                        ->values();

                    if ($matched->isEmpty()) {
                        return [$meta['id'] => null];
                    }

                    $latest = $matched->first();
                    return [
                        $meta['id'] => [
                            'count' => $matched->count(),
                            'latest_order_no' => $latest?->order_no,
                            'latest_status' => $latest?->status,
                            'latest_at' => $latest?->updated_at ? Carbon::parse($latest->updated_at)->toIso8601String() : null,
                        ],
                    ];
                });
            }
        }

        $reservations = $paginatedReservations->through(function ($reservation) use ($orderByReservation, $selfOrderByReservationNumber, $selfOrderByReservationIdFallback) {
                $orderRef = $orderByReservation->get($reservation->id, ['paid_number' => null, 'used_order_at' => null]);
                $reservationNumberKey = strtoupper(trim((string) ($reservation->reservation_number ?? '')));
                $selfOrderRef = $reservationNumberKey !== ''
                    ? $selfOrderByReservationNumber->get($reservationNumberKey)
                    : null;
                if (!$selfOrderRef) {
                    $selfOrderRef = $selfOrderByReservationIdFallback->get($reservation->id);
                }
                $orderMode = $selfOrderRef ? 'self_order' : 'manual_whatsapp';
                return [
                'id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
                'name' => $reservation->name,
                'phone' => $reservation->phone ?? '–',
                'outlet' => $reservation->outlet?->nama_outlet ?? '–',
                'reservation_date' => $reservation->reservation_date,
                'reservation_time' => $reservation->reservation_time,
                'number_of_guests' => $reservation->number_of_guests,
                'smoking_preference' => $reservation->smoking_preference,
                'dp' => $reservation->dp,
                'payment_type_id' => $reservation->payment_type_id,
                'payment_type_name' => $reservation->paymentType?->name,
                'dp_code' => $reservation->dp_code,
                'from_sales' => $reservation->from_sales,
                'sales_user_id' => $reservation->sales_user_id,
                'sales_user_name' => $reservation->salesUser ? $reservation->salesUser->nama_lengkap : null,
                'menu' => $reservation->menu,
                'order_mode' => $orderMode,
                'self_order_count' => $selfOrderRef['count'] ?? 0,
                'self_order_latest_no' => $selfOrderRef['latest_order_no'] ?? null,
                'status' => $reservation->status,
                'dp_used_at' => $reservation->dp_used_at?->toIso8601String(),
                'dp_used_paid_number' => $orderRef['paid_number'] ?? null,
                'dp_used_order_at' => $orderRef['used_order_at'] ?? null,
                'created_by' => $reservation->creator ? ($reservation->creator->nama_lengkap ?? $reservation->creator->name) : '–',
                'created_at' => $reservation->created_at?->toIso8601String(),
            ];
            });

        $outletsQuery = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '');
        if (!$isAdminOutlet && $userOutletId) {
            $outletsQuery->where('id_outlet', $userOutletId);
        } else {
            $outletsQuery->orderBy('nama_outlet');
        }
        $outlets = $outletsQuery->get(['id_outlet', 'nama_outlet'])
            ->map(fn($o) => ['id' => $o->id_outlet, 'name' => $o->nama_outlet])
            ->values();

        $effectiveOutletId = $isAdminOutlet ? $request->outlet_id : $userOutletId;

        return Inertia::render('Reservations/Index', [
            'reservations' => $reservations,
            'outlets' => $outlets,
            'can_choose_outlet' => $isAdminOutlet,
            'search' => $request->search,
            'outlet_id' => $effectiveOutletId,
            'status' => $request->status,
            'dateFrom' => $request->dateFrom,
            'dateTo' => $request->dateTo,
            'per_page' => $perPage,
        ]);
    }

    public function create()
    {
        $outlets = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get(['id_outlet', 'nama_outlet', 'region_id'])
            ->map(function($o) {
                return [
                    'id' => $o->id_outlet,
                    'name' => $o->nama_outlet,
                    'region_id' => $o->region_id,
                ];
            })
            ->values();

        $salesUsers = User::where('division_id', 17)
            ->where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->nama_lengkap])
            ->values();

        $paymentTypes = $this->getPaymentTypesForReservationForm();

        return Inertia::render('Reservations/Form', [
            'outlets' => $outlets,
            'salesUsers' => $salesUsers,
            'paymentTypes' => $paymentTypes,
            'isEdit' => false
        ]);
    }

    public function export(Request $request)
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet ? (int) $user->id_outlet : null;
        $isAdminOutlet = ($userOutletId === 1 || $userOutletId === null);

        $query = Reservation::with(['outlet', 'creator', 'salesUser', 'paymentType'])
            ->when(!$isAdminOutlet && $userOutletId, function ($q) use ($userOutletId) {
                $q->where('outlet_id', $userOutletId);
            })
            ->when($isAdminOutlet && $request->outlet_id, function ($q, $outletId) {
                $q->where('outlet_id', $outletId);
            })
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->when($request->status, function ($q, $status) {
                $q->where('status', $status);
            })
            ->when($request->dateFrom, function ($q, $dateFrom) {
                $q->whereDate('reservation_date', '>=', $dateFrom);
            })
            ->when($request->dateTo, function ($q, $dateTo) {
                $q->whereDate('reservation_date', '<=', $dateTo);
            })
            ->latest();

        $rows = $query->get();
        $timestamp = now()->format('Ymd_His');

        return Excel::download(
            new ReservationsExport($rows),
            "reservasi_{$timestamp}.xlsx"
        );
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:100',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'reservation_date' => 'required|date',
                'reservation_time' => 'required',
                'number_of_guests' => 'required|integer|min:1',
                'special_requests' => 'nullable|string',
                'dp' => 'nullable|numeric|min:0',
                'payment_type_id' => 'nullable|exists:payment_types,id',
                'from_sales' => 'nullable|boolean',
                'sales_user_id' => 'nullable|exists:users,id',
                'menu' => 'nullable|string',
                'menu_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,xls,xlsx|max:10240',
                'status' => 'required|in:pending,confirmed,cancelled',
            ]);

            // Add created_by with authenticated user ID
            $validated['created_by'] = auth()->id();
            $validated['from_sales'] = filter_var($request->input('from_sales'), FILTER_VALIDATE_BOOLEAN);
            if (empty($validated['from_sales'])) {
                $validated['sales_user_id'] = null;
            }
            if ($request->hasFile('menu_file')) {
                $validated['menu_file'] = $request->file('menu_file')->storeAs(
                    'reservations/menu',
                    Str::uuid() . '.' . $request->file('menu_file')->getClientOriginalExtension(),
                    'public'
                );
            } else {
                unset($validated['menu_file']);
            }

            $reservation = Reservation::create($validated);
            $this->assignReservationNumberIfMissing($reservation);
            $this->syncDpCode($reservation, (float) ($request->input('dp') ?? 0));

            return redirect()->route('reservations.index')
                ->with('success', 'Reservasi berhasil ditambahkan!');
        } catch (\Throwable $e) {
            \Log::error('Gagal menyimpan reservasi: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return redirect()->back()->with('error', 'Terjadi kesalahan saat menyimpan reservasi.');
        }
    }

    public function show(Reservation $reservation)
    {
        $selfOrderTable = Schema::hasTable('web_self_orders') ? 'web_self_orders' : 'self_orders';
        $selfOrderItemTable = Schema::hasTable('web_self_order_items') ? 'web_self_order_items' : 'self_order_items';
        $selfOrderItemFk = $selfOrderItemTable === 'web_self_order_items' ? 'web_self_order_id' : 'self_order_id';
        $selfOrderColumns = [
            'id',
            'order_no',
            'customer_name',
            'customer_phone',
            'order_type',
            'notes',
            'status',
            'subtotal',
            'grand_total',
            'created_at',
            'updated_at',
        ];
        if (Schema::hasColumn($selfOrderTable, 'total_item')) {
            $selfOrderColumns[] = 'total_item';
        }
        if (Schema::hasColumn($selfOrderTable, 'service')) {
            $selfOrderColumns[] = 'service';
        }
        if (Schema::hasColumn($selfOrderTable, 'pb1')) {
            $selfOrderColumns[] = 'pb1';
        }
        $reservation->load(['outlet', 'creator', 'salesUser', 'paymentType']);

        // Transaksi POS yang ter-link ke reservasi ini (order sync dari POS ke pusat)
        $linkedOrders = DB::table('orders')
            ->where('reservation_id', $reservation->id)
            ->orderByDesc('created_at')
            ->get(['id', 'paid_number', 'grand_total', 'status', 'table', 'created_at', 'kode_outlet'])
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'paid_number' => $row->paid_number,
                    'grand_total' => $row->grand_total,
                    'status' => $row->status,
                    'table' => $row->table,
                    'created_at' => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->toIso8601String() : null,
                    'kode_outlet' => $row->kode_outlet,
                ];
            })
            ->values()
            ->all();

        $selfOrders = [];
        $reservationNumber = strtoupper(trim((string) ($reservation->reservation_number ?? '')));
        if (
            $reservationNumber !== '' &&
            Schema::hasTable($selfOrderTable) &&
            Schema::hasColumn($selfOrderTable, 'reservation_number')
        ) {
            $selfOrderRows = DB::table($selfOrderTable)
                ->where('reservation_number', $reservationNumber)
                ->orderByDesc('created_at')
                ->get($selfOrderColumns);

            $selfOrderIds = $selfOrderRows->pluck('id')->filter()->values();
            $itemsBySelfOrder = collect();
            $modifierOptionNames = [];
            if ($selfOrderIds->isNotEmpty() && Schema::hasTable($selfOrderItemTable)) {
                $selfOrderItems = DB::table($selfOrderItemTable)
                    ->whereIn($selfOrderItemFk, $selfOrderIds)
                    ->orderBy('id')
                    ->get([
                        'id',
                        DB::raw($selfOrderItemFk . ' as self_order_id'),
                        'item_id',
                        'item_name',
                        'qty',
                        'price',
                        'subtotal',
                        'notes',
                        'modifiers',
                    ]);

                $itemsBySelfOrder = $selfOrderItems->groupBy('self_order_id');

                $modifierOptionIds = $selfOrderItems
                    ->flatMap(fn ($item) => $this->extractModifierOptionIds($item->modifiers))
                    ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($modifierOptionIds->isNotEmpty() && Schema::hasTable('modifier_options')) {
                    $modifierOptionNames = DB::table('modifier_options')
                        ->whereIn('id', $modifierOptionIds->all())
                        ->pluck('name', 'id')
                        ->toArray();
                }
            }

            $selfOrders = $selfOrderRows->map(function ($row) use ($itemsBySelfOrder, $modifierOptionNames) {
                $items = ($itemsBySelfOrder->get($row->id) ?? collect())->map(function ($item) use ($modifierOptionNames) {
                    return [
                        'id' => $item->id,
                        'item_id' => $item->item_id,
                        'item_name' => $item->item_name,
                        'qty' => (int) $item->qty,
                        'price' => (float) $item->price,
                        'subtotal' => (float) $item->subtotal,
                        'notes' => $item->notes,
                        'modifiers' => $item->modifiers,
                        'modifier_labels' => $this->buildModifierLabels($item->modifiers, $modifierOptionNames),
                    ];
                })->values()->all();

                return [
                    'id' => $row->id,
                    'order_no' => $row->order_no,
                    'customer_name' => $row->customer_name,
                    'customer_phone' => $row->customer_phone,
                    'order_type' => $row->order_type,
                    'notes' => $row->notes,
                    'status' => $row->status,
                    'total_item' => (int) ($row->total_item ?? count($items)),
                    'subtotal' => (float) $row->subtotal,
                    'service' => (float) ($row->service ?? 0),
                    'pb1' => (float) ($row->pb1 ?? 0),
                    'grand_total' => (float) $row->grand_total,
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : null,
                    'updated_at' => $row->updated_at ? Carbon::parse($row->updated_at)->toIso8601String() : null,
                    'items' => $items,
                ];
            })->values()->all();
        }

        if (empty($selfOrders) && Schema::hasTable($selfOrderTable)) {
            $phoneNorm = $this->normalizePhone((string) ($reservation->phone ?? ''));
            $nameNorm = $this->normalizeName((string) ($reservation->name ?? ''));
            if (($phoneNorm !== '' || $nameNorm !== '') && !empty($reservation->outlet_id)) {
                $candidateRows = DB::table($selfOrderTable)
                    ->where('outlet_id', $reservation->outlet_id)
                    ->orderByDesc('created_at')
                    ->get($selfOrderColumns);

                $reservationCreatedAt = $reservation->created_at ? Carbon::parse($reservation->created_at) : null;
                $selfOrderRows = $candidateRows
                    ->filter(function ($row) use ($phoneNorm, $nameNorm, $reservationCreatedAt) {
                        $phoneMatched = $this->isLikelySamePhone((string) ($row->customer_phone ?? ''), $phoneNorm);
                        $nameMatched = !empty($nameNorm)
                            && $this->normalizeName((string) ($row->customer_name ?? '')) === $nameNorm;

                        if (!$phoneMatched && !$nameMatched) {
                            return false;
                        }

                        if (!$reservationCreatedAt || empty($row->created_at)) {
                            return true;
                        }

                        $rowCreatedAt = Carbon::parse($row->created_at);
                        return $rowCreatedAt->between($reservationCreatedAt->copy()->subDays(7), $reservationCreatedAt->copy()->addDays(7));
                    })
                    ->values();

                if ($selfOrderRows->isNotEmpty()) {
                    $selfOrderIds = $selfOrderRows->pluck('id')->filter()->values();
                    $itemsBySelfOrder = collect();
                    $modifierOptionNames = [];
                    if ($selfOrderIds->isNotEmpty() && Schema::hasTable($selfOrderItemTable)) {
                        $selfOrderItems = DB::table($selfOrderItemTable)
                            ->whereIn($selfOrderItemFk, $selfOrderIds)
                            ->orderBy('id')
                            ->get([
                                'id',
                                DB::raw($selfOrderItemFk . ' as self_order_id'),
                                'item_id',
                                'item_name',
                                'qty',
                                'price',
                                'subtotal',
                                'notes',
                                'modifiers',
                            ]);

                        $itemsBySelfOrder = $selfOrderItems->groupBy('self_order_id');

                        $modifierOptionIds = $selfOrderItems
                            ->flatMap(fn ($item) => $this->extractModifierOptionIds($item->modifiers))
                            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
                            ->map(fn ($id) => (int) $id)
                            ->unique()
                            ->values();

                        if ($modifierOptionIds->isNotEmpty() && Schema::hasTable('modifier_options')) {
                            $modifierOptionNames = DB::table('modifier_options')
                                ->whereIn('id', $modifierOptionIds->all())
                                ->pluck('name', 'id')
                                ->toArray();
                        }
                    }

                    $selfOrders = $selfOrderRows->map(function ($row) use ($itemsBySelfOrder, $modifierOptionNames) {
                        $items = ($itemsBySelfOrder->get($row->id) ?? collect())->map(function ($item) use ($modifierOptionNames) {
                            return [
                                'id' => $item->id,
                                'item_id' => $item->item_id,
                                'item_name' => $item->item_name,
                                'qty' => (int) $item->qty,
                                'price' => (float) $item->price,
                                'subtotal' => (float) $item->subtotal,
                                'notes' => $item->notes,
                                'modifiers' => $item->modifiers,
                                'modifier_labels' => $this->buildModifierLabels($item->modifiers, $modifierOptionNames),
                            ];
                        })->values()->all();

                        return [
                            'id' => $row->id,
                            'order_no' => $row->order_no,
                            'customer_name' => $row->customer_name,
                            'customer_phone' => $row->customer_phone,
                            'order_type' => $row->order_type,
                            'notes' => $row->notes,
                            'status' => $row->status,
                            'total_item' => (int) ($row->total_item ?? count($items)),
                            'subtotal' => (float) $row->subtotal,
                            'service' => (float) ($row->service ?? 0),
                            'pb1' => (float) ($row->pb1 ?? 0),
                            'grand_total' => (float) $row->grand_total,
                            'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : null,
                            'updated_at' => $row->updated_at ? Carbon::parse($row->updated_at)->toIso8601String() : null,
                            'items' => $items,
                        ];
                    })->values()->all();
                }
            }
        }

        $orderMode = !empty($selfOrders) ? 'self_order' : 'manual_whatsapp';
        $reservation->setAttribute('order_mode', $orderMode);

        return Inertia::render('Reservations/Show', [
            'reservation' => $reservation,
            'linked_orders' => $linkedOrders,
            'self_orders' => $selfOrders,
        ]);
    }

    /**
     * Download file menu reservasi (agar bisa dibuka/unduh meski tanpa symlink storage).
     * Nama file pakai ekstensi asli (xlsx, pdf, jpg, dll) supaya tidak tersimpan sebagai .htm.
     */
    public function downloadMenuFile(Reservation $reservation)
    {
        if (empty($reservation->menu_file)) {
            abort(404, 'File menu tidak ada.');
        }
        $path = Storage::disk('public')->path($reservation->menu_file);
        if (!file_exists($path)) {
            abort(404, 'File menu tidak ditemukan.');
        }
        $mime = \Illuminate\Support\Facades\File::mimeType($path);
        $basename = basename($reservation->menu_file);
        $ext = pathinfo($basename, PATHINFO_EXTENSION);
        if ($ext === '') {
            $ext = $this->mimeToExtension($mime);
            $basename = 'menu.' . $ext;
        }
        return response()->download($path, $basename, [
            'Content-Type' => $mime,
        ]);
    }

    /** Map MIME ke ekstensi untuk file menu (image, pdf, excel). */
    private function mimeToExtension(string $mime): string
    {
        $map = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-excel' => 'xls',
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        return $map[$mime] ?? 'bin';
    }

    public function edit(Reservation $reservation)
    {
        $outlets = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get(['id_outlet', 'nama_outlet', 'region_id'])
            ->map(function($o) {
                return [
                    'id' => $o->id_outlet,
                    'name' => $o->nama_outlet,
                    'region_id' => $o->region_id,
                ];
            })
            ->values();

        $salesUsers = User::where('division_id', 17)
            ->where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->nama_lengkap])
            ->values();

        $paymentTypes = $this->getPaymentTypesForReservationForm();

        $reservationPayload = $reservation->toArray();
        $reservationPayload['reservation_date'] = $reservation->reservation_date
            ? Carbon::parse($reservation->reservation_date)->format('Y-m-d')
            : null;
        $reservationPayload['reservation_time'] = $reservation->reservation_time
            ? Carbon::parse($reservation->reservation_time)->format('H:i')
            : null;

        return Inertia::render('Reservations/Form', [
            'reservation' => $reservationPayload,
            'outlets' => $outlets,
            'salesUsers' => $salesUsers,
            'paymentTypes' => $paymentTypes,
            'isEdit' => true
        ]);
    }

    public function update(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:100',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'reservation_date' => 'required|date',
            'reservation_time' => 'required',
            'number_of_guests' => 'required|integer|min:1',
            'special_requests' => 'nullable|string',
            'dp' => 'nullable|numeric|min:0',
            'payment_type_id' => 'nullable|exists:payment_types,id',
            'from_sales' => 'nullable|boolean',
            'sales_user_id' => 'nullable|exists:users,id',
            'menu' => 'nullable|string',
            'menu_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,xls,xlsx|max:10240',
            'status' => 'required|in:pending,confirmed,cancelled',
        ]);
        $validated['from_sales'] = filter_var($request->input('from_sales'), FILTER_VALIDATE_BOOLEAN);
        if (empty($validated['from_sales'])) {
            $validated['sales_user_id'] = null;
        }
        if ($request->hasFile('menu_file')) {
            if ($reservation->menu_file && Storage::disk('public')->exists($reservation->menu_file)) {
                Storage::disk('public')->delete($reservation->menu_file);
            }
            $validated['menu_file'] = $request->file('menu_file')->storeAs(
                'reservations/menu',
                Str::uuid() . '.' . $request->file('menu_file')->getClientOriginalExtension(),
                'public'
            );
        } else {
            unset($validated['menu_file']);
        }

        $reservation->update($validated);
        $this->syncDpCode($reservation, (float) ($request->input('dp') ?? 0));

        return redirect()->route('reservations.index')
            ->with('success', 'Reservasi berhasil diupdate!');
    }

    public function updateStatus(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,arrived,cancelled,no_show',
        ]);

        $reservation->status = $validated['status'];
        $reservation->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Status reservasi berhasil diupdate.',
                'status' => $reservation->status,
            ]);
        }

        return back()->with('success', 'Status reservasi berhasil diupdate.');
    }

    /**
     * Set atau clear dp_code & dp_used_at berdasarkan nilai DP.
     * Jika dp > 0: generate kode unik 8 karakter (angka+huruf). Jika dp 0/null: clear kode.
     */
    private function syncDpCode(Reservation $reservation, float $dp): void
    {
        if ($dp > 0) {
            if (empty($reservation->dp_code)) {
                $reservation->dp_code = $this->generateUniqueDpCode();
                $reservation->dp_used_at = null;
                $reservation->saveQuietly();
            }
        } else {
            if ($reservation->dp_code !== null || $reservation->dp_used_at !== null) {
                $reservation->dp_code = null;
                $reservation->dp_used_at = null;
                $reservation->saveQuietly();
            }
        }
    }

    private function generateUniqueDpCode(): string
    {
        $chars = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // tanpa I,O agar tidak bingung
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (Reservation::where('dp_code', $code)->exists());
        return $code;
    }

    private function assignReservationNumberIfMissing(Reservation $reservation): void
    {
        if (!empty($reservation->reservation_number)) {
            return;
        }

        $createdAt = $reservation->created_at
            ? Carbon::parse($reservation->created_at)->timezone('Asia/Jakarta')
            : now('Asia/Jakarta');
        $dateKey = $createdAt->format('Ymd');
        $prefix = 'RSV-' . $dateKey . '-';

        $latestForDay = Reservation::whereNotNull('reservation_number')
            ->where('reservation_number', 'like', $prefix . '%')
            ->orderByDesc('reservation_number')
            ->value('reservation_number');

        $nextSeq = 1;
        if (is_string($latestForDay) && preg_match('/-(\d{4})$/', $latestForDay, $matches)) {
            $nextSeq = ((int) $matches[1]) + 1;
        }

        for ($attempt = 0; $attempt < 2000; $attempt++) {
            $candidate = sprintf('%s%04d', $prefix, $nextSeq + $attempt);
            if (!Reservation::where('reservation_number', $candidate)->exists()) {
                $reservation->reservation_number = $candidate;
                $reservation->saveQuietly();
                return;
            }
        }

        throw new \RuntimeException('Gagal membuat reservation_number unik.');
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function normalizeName(string $name): string
    {
        $lower = mb_strtolower(trim($name));
        return preg_replace('/\s+/', ' ', $lower) ?? '';
    }

    private function isLikelySamePhone(string $leftPhone, string $rightPhone): bool
    {
        $left = $this->normalizePhone($leftPhone);
        $right = $this->normalizePhone($rightPhone);

        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        $leftAlt = preg_replace('/^(62|0)/', '', $left) ?? $left;
        $rightAlt = preg_replace('/^(62|0)/', '', $right) ?? $right;
        if ($leftAlt !== '' && $leftAlt === $rightAlt) {
            return true;
        }

        $minCommon = 9;
        if (strlen($leftAlt) >= $minCommon && strlen($rightAlt) >= $minCommon) {
            if (substr($leftAlt, -$minCommon) === substr($rightAlt, -$minCommon)) {
                return true;
            }
        }

        if (strlen($leftAlt) >= $minCommon && str_ends_with($leftAlt, $rightAlt)) {
            return true;
        }

        if (strlen($rightAlt) >= $minCommon && str_ends_with($rightAlt, $leftAlt)) {
            return true;
        }

        return false;
    }

    private function decodeModifiersPayload($modifiers)
    {
        if (empty($modifiers)) {
            return null;
        }

        if (is_string($modifiers)) {
            $decoded = json_decode($modifiers, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if (is_array($modifiers)) {
            return $modifiers;
        }

        return null;
    }

    private function extractModifierOptionIds($modifiers): array
    {
        $decoded = $this->decodeModifiersPayload($modifiers);
        if (!is_array($decoded)) {
            return [];
        }

        $ids = [];
        foreach ($decoded as $groupVal) {
            if (!is_array($groupVal)) {
                continue;
            }
            foreach ($groupVal as $optionKey => $qtyVal) {
                if (is_numeric($optionKey) && (int) $qtyVal > 0) {
                    $ids[] = (int) $optionKey;
                    continue;
                }

                if (is_array($qtyVal)) {
                    foreach ($qtyVal as $innerKey => $innerQty) {
                        if (is_numeric($innerKey) && (int) $innerQty > 0) {
                            $ids[] = (int) $innerKey;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function buildModifierLabels($modifiers, array $modifierOptionNames = []): array
    {
        $decoded = $this->decodeModifiersPayload($modifiers);
        if (!is_array($decoded)) {
            return [];
        }

        $labels = [];
        foreach ($decoded as $groupVal) {
            if (!is_array($groupVal)) {
                continue;
            }

            foreach ($groupVal as $optionKey => $qtyVal) {
                if (!is_numeric($optionKey)) {
                    continue;
                }

                $qty = (int) $qtyVal;
                if ($qty <= 0) {
                    continue;
                }

                $optionId = (int) $optionKey;
                $optionName = $modifierOptionNames[$optionId] ?? null;
                $baseLabel = is_string($optionName) && trim($optionName) !== ''
                    ? trim($optionName)
                    : ('Option #' . $optionId);
                $labels[] = $qty > 1 ? ($baseLabel . ' x' . $qty) : $baseLabel;
            }
        }

        return array_values(array_unique($labels));
    }

    public function destroy(Reservation $reservation)
    {
        DB::beginTransaction();
        try {
            $reservationNumber = strtoupper(trim((string) ($reservation->reservation_number ?? '')));
            $orderTablePairs = [
                ['orders' => 'web_self_orders', 'items' => 'web_self_order_items'],
                ['orders' => 'self_orders', 'items' => 'self_order_items'],
            ];

            foreach ($orderTablePairs as $pair) {
                $orderTable = $pair['orders'];
                $itemTable = $pair['items'];

                if (!Schema::hasTable($orderTable)) {
                    continue;
                }

                $orderCols = collect(Schema::getColumnListing($orderTable))
                    ->map(fn ($c) => strtolower((string) $c));

                $orderIds = DB::table($orderTable)
                    ->when($orderCols->contains('reservation_id') || ($reservationNumber !== '' && $orderCols->contains('reservation_number')), function ($q) use ($reservation, $reservationNumber, $orderCols) {
                        $q->where(function ($q2) use ($reservation, $reservationNumber, $orderCols) {
                            if ($orderCols->contains('reservation_id')) {
                                $q2->where('reservation_id', $reservation->id);
                            }
                            if ($reservationNumber !== '' && $orderCols->contains('reservation_number')) {
                                if ($orderCols->contains('reservation_id')) {
                                    $q2->orWhereRaw('UPPER(TRIM(reservation_number)) = ?', [$reservationNumber]);
                                } else {
                                    $q2->whereRaw('UPPER(TRIM(reservation_number)) = ?', [$reservationNumber]);
                                }
                            }
                        });
                    }, function ($q) {
                        $q->whereRaw('1=0');
                    })
                    ->pluck('id')
                    ->filter()
                    ->values();

                if ($orderIds->isEmpty()) {
                    continue;
                }

                if (Schema::hasTable($itemTable)) {
                    $itemCols = collect(Schema::getColumnListing($itemTable))
                        ->map(fn ($c) => strtolower((string) $c));
                    $itemFk = $itemCols->contains('web_self_order_id')
                        ? 'web_self_order_id'
                        : ($itemCols->contains('self_order_id') ? 'self_order_id' : null);

                    if ($itemFk) {
                        DB::table($itemTable)
                            ->whereIn($itemFk, $orderIds->all())
                            ->delete();
                    }
                }

                DB::table($orderTable)
                    ->whereIn('id', $orderIds->all())
                    ->delete();
            }

            $reservation->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('reservations.index')
            ->with('success', 'Reservasi berhasil dihapus!');
    }

    // ---------- API for Approval App (mobile) ----------

    public function apiIndex(Request $request)
    {
        $query = Reservation::with(['outlet', 'creator', 'salesUser', 'paymentType'])
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->when($request->status, function ($q, $status) {
                $q->where('status', $status);
            })
            ->when($request->date_from, function ($q, $dateFrom) {
                $q->whereDate('reservation_date', '>=', $dateFrom);
            })
            ->when($request->date_to, function ($q, $dateTo) {
                $q->whereDate('reservation_date', '<=', $dateTo);
            })
            ->latest();

        $reservations = $query->get()->map(function ($reservation) {
            return [
                'id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
                'name' => $reservation->name,
                'phone' => $reservation->phone,
                'email' => $reservation->email,
                'outlet_id' => $reservation->outlet_id,
                'outlet' => $reservation->outlet ? $reservation->outlet->nama_outlet : null,
                'reservation_date' => $reservation->reservation_date?->format('Y-m-d'),
                'reservation_time' => $reservation->reservation_time ? \Carbon\Carbon::parse($reservation->reservation_time)->format('H:i') : null,
                'number_of_guests' => $reservation->number_of_guests,
                'smoking_preference' => $reservation->smoking_preference,
                'special_requests' => $reservation->special_requests,
                'dp' => $reservation->dp ? (float) $reservation->dp : null,
                'payment_type_id' => $reservation->payment_type_id,
                'payment_type_name' => $reservation->paymentType?->name,
                'from_sales' => (bool) $reservation->from_sales,
                'sales_user_id' => $reservation->sales_user_id,
                'sales_user_name' => $reservation->salesUser ? $reservation->salesUser->nama_lengkap : null,
                'menu' => $reservation->menu,
                'menu_file' => $reservation->menu_file,
                'menu_file_url' => $reservation->menu_file ? Storage::disk('public')->url($reservation->menu_file) : null,
                'status' => $reservation->status,
                'created_by' => $reservation->creator ? ($reservation->creator->nama_lengkap ?? $reservation->creator->name ?? null) : null,
                'created_at' => $reservation->created_at?->toIso8601String(),
            ];
        });

        return response()->json(['data' => $reservations]);
    }

    public function apiCreateData()
    {
        $outlets = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get()
            ->map(function ($o) {
                return ['id' => $o->id_outlet, 'name' => $o->nama_outlet];
            })
            ->values();

        $salesUsers = User::where('division_id', 17)
            ->where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->nama_lengkap])
            ->values();

        return response()->json([
            'outlets' => $outlets,
            'sales_users' => $salesUsers,
        ]);
    }

    /**
     * Ringkasan DP reservasi per tanggal & outlet (untuk Revenue Report).
     * GET /api/reservations/dp-summary?date=YYYY-MM-DD&outlet_id=123
     *
     * Return:
     * - total_dp, breakdown: DP untuk reservasi yang jadwalnya di tanggal report.
     * - dp_future_total, dp_future_breakdown: DP diterima hari ini untuk reservasi tanggal mendatang (created_at = date, reservation_date > date).
     * - orders_using_dp: transaksi hari tersebut yang menggunakan DP (order paid + reservation dengan DP), plus tanggal DP.
     */
    public function apiDpSummary(Request $request)
    {
        Log::info('apiDpSummary called', [
            'date' => $request->date,
            'outlet_id' => $request->outlet_id,
            'kode_outlet' => $request->kode_outlet,
        ]);
        $request->validate([
            'date' => 'required|date',
            'outlet_id' => 'nullable',
            'kode_outlet' => 'nullable|string',
        ]);
        $date = $request->date;
        $outletId = $request->outlet_id;
        $debugOutlet = null;
        if (empty($outletId) && $request->filled('kode_outlet')) {
            $kode = $request->kode_outlet;
            $outlet = Outlet::where('qr_code', $kode)->first();
            if (! $outlet && is_numeric($kode)) {
                $outlet = Outlet::where('id_outlet', $kode)->first();
            }
            if (! $outlet) {
                $outlet = Outlet::where('nama_outlet', $kode)->first();
            }
            $outletId = $outlet ? $outlet->id_outlet : null;
            if ($request->boolean('debug')) {
                $debugOutlet = [
                    'kode_dari_request' => $kode,
                    'outlet_ditemukan' => $outlet ? ['id_outlet' => $outlet->id_outlet, 'qr_code' => $outlet->qr_code, 'nama_outlet' => $outlet->nama_outlet] : null,
                ];
            }
        }
        if (empty($outletId)) {
            $debug = $request->boolean('debug') ? [
                'debug' => array_merge([
                    'message' => 'outlet_id kosong setelah resolusi',
                    'request_outlet_id' => $request->outlet_id,
                    'request_kode_outlet' => $request->kode_outlet,
                ], $debugOutlet ? ['outlet_resolution' => $debugOutlet] : []),
            ] : [];
            return response()->json(array_merge([
                'total_dp' => 0,
                'breakdown' => [],
                'dp_reservations' => [],
                'dp_future_total' => 0,
                'dp_future_breakdown' => [],
                'dp_future_reservations' => [],
                'orders_using_dp' => [],
            ], $debug));
        }

        $debug = [];
        if ($request->boolean('debug')) {
            $debug['debug'] = array_filter([
                'resolved_outlet_id' => $outletId,
                'request_date' => $date,
                'request_kode_outlet' => $request->kode_outlet,
                'request_outlet_id' => $request->outlet_id,
                'outlet_resolution' => $debugOutlet,
            ]);
        }

        // 1) DP untuk reservasi yang jadwalnya di tanggal report (existing)
        $reservations = Reservation::with('paymentType')
            ->whereDate('reservation_date', $date)
            ->where('outlet_id', $outletId)
            ->whereNotNull('dp')
            ->where('dp', '>', 0)
            ->get();

        $totalDp = $reservations->sum(fn ($r) => (float) $r->dp);
        $breakdown = [];
        $dpReservationsList = [];
        foreach ($reservations as $r) {
            $name = $r->paymentType ? $r->paymentType->name : 'Lainnya';
            if (!isset($breakdown[$name])) {
                $breakdown[$name] = 0;
            }
            $breakdown[$name] += (float) $r->dp;
            $dpReservationsList[] = [
                'id' => $r->id,
                'name' => $r->name,
                'reservation_date' => $r->reservation_date?->format('Y-m-d'),
                'dp' => (float) $r->dp,
                'payment_type_name' => $name,
            ];
        }

        // 2) DP diterima di tanggal yang dipilih untuk reservasi tanggal mendatang (created_at = date, reservation_date > date)
        $queryFuture = Reservation::with('paymentType')
            ->whereDate('created_at', $date)
            ->where('reservation_date', '>', $date)
            ->where('outlet_id', $outletId)
            ->whereNotNull('dp')
            ->where('dp', '>', 0);
        if ($request->boolean('debug')) {
            $debug['debug']['query_dp_future_sql'] = $queryFuture->toSql();
            $debug['debug']['query_dp_future_bindings'] = $queryFuture->getBindings();
        }
        $reservationsFuture = $queryFuture->get();

        $dpFutureTotal = $reservationsFuture->sum(fn ($r) => (float) $r->dp);
        $dpFutureBreakdown = [];
        $dpFutureReservationsList = [];
        foreach ($reservationsFuture as $r) {
            $name = $r->paymentType ? $r->paymentType->name : 'Lainnya';
            if (!isset($dpFutureBreakdown[$name])) {
                $dpFutureBreakdown[$name] = 0;
            }
            $dpFutureBreakdown[$name] += (float) $r->dp;
            $dpFutureReservationsList[] = [
                'id' => $r->id,
                'name' => $r->name,
                'reservation_date' => $r->reservation_date?->format('Y-m-d'),
                'dp' => (float) $r->dp,
                'payment_type_name' => $name,
            ];
        }

        // 3) Transaksi hari tersebut yang menggunakan DP (order paid on date, punya reservation_id dengan DP)
        $kodeOutlet = Outlet::where('id_outlet', $outletId)->value('qr_code');
        $ordersUsingDp = [];
        if ($kodeOutlet && Schema::hasTable('orders') && Schema::hasColumn('orders', 'reservation_id')) {
            $orderRows = DB::table('orders')
                ->whereDate('updated_at', $date)
                ->where('kode_outlet', $kodeOutlet)
                ->where('status', 'paid')
                ->whereNotNull('reservation_id')
                ->get(['id', 'paid_number', 'grand_total', 'reservation_id']);

            $reservationIds = $orderRows->pluck('reservation_id')->unique()->filter()->values()->all();
            if (!empty($reservationIds)) {
                $reservationsWithDp = Reservation::with('paymentType')
                    ->whereIn('id', $reservationIds)
                    ->whereNotNull('dp')
                    ->where('dp', '>', 0)
                    ->get()
                    ->keyBy('id');

                foreach ($orderRows as $row) {
                    $res = $reservationsWithDp->get($row->reservation_id);
                    if (!$res) {
                        continue;
                    }
                    $ordersUsingDp[] = [
                        'paid_number' => $row->paid_number,
                        'grand_total' => (float) $row->grand_total,
                        'reservation_name' => $res->name,
                        'dp_amount' => (float) $res->dp,
                        'dp_paid_at' => $res->created_at?->format('Y-m-d'),
                    ];
                }
            }
        }

        return response()->json(array_merge([
            'total_dp' => $totalDp,
            'breakdown' => array_values(array_map(fn ($name) => ['payment_type_name' => $name, 'total' => $breakdown[$name]], array_keys($breakdown))),
            'dp_reservations' => $dpReservationsList,
            'dp_future_total' => $dpFutureTotal,
            'dp_future_breakdown' => array_values(array_map(fn ($name) => ['payment_type_name' => $name, 'total' => $dpFutureBreakdown[$name]], array_keys($dpFutureBreakdown))),
            'dp_future_reservations' => $dpFutureReservationsList,
            'orders_using_dp' => $ordersUsingDp,
        ], $debug));
    }

    /**
     * Validasi kode DP untuk transaksi POS.
     * GET ?code=XXX&outlet_id=Y  atau  ?code=XXX&kode_outlet=ZZZ (kode outlet dari setup.json POS).
     * Return { valid, amount, reservation_id, message }.
     */
    public function apiValidateDpCode(Request $request)
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        $outletId = $request->input('outlet_id');
        $kodeOutlet = $request->input('kode_outlet');
        if ($code === '') {
            return response()->json(['valid' => false, 'message' => 'Kode wajib'], 400);
        }
        if (!$outletId && $kodeOutlet !== null && $kodeOutlet !== '') {
            $outlet = Outlet::where('qr_code', $kodeOutlet)->first();
            $outletId = $outlet ? $outlet->id_outlet : null;
        }
        if (!$outletId) {
            return response()->json(['valid' => false, 'message' => 'Outlet wajib (outlet_id atau kode_outlet)'], 400);
        }
        $reservation = Reservation::where('dp_code', $code)
            ->where('outlet_id', $outletId)
            ->whereNull('dp_used_at')
            ->whereNotNull('dp')
            ->where('dp', '>', 0)
            ->first();
        if (!$reservation) {
            return response()->json([
                'valid' => false,
                'message' => 'Kode DP tidak valid atau sudah dipakai',
            ]);
        }
        return response()->json([
            'valid' => true,
            'amount' => (float) $reservation->dp,
            'reservation_id' => $reservation->id,
        ]);
    }

    /**
     * Tandai kode DP sudah dipakai di transaksi (dipanggil POS setelah bayar).
     * POST { "dp_code": "XXXXXXXX" }
     */
    public function apiMarkDpUsed(Request $request)
    {
        $request->validate(['dp_code' => 'required|string|size:8']);
        $code = strtoupper(trim($request->dp_code));
        $updated = Reservation::where('dp_code', $code)
            ->whereNull('dp_used_at')
            ->where('dp', '>', 0)
            ->update(['dp_used_at' => now()]);
        if ($updated === 0) {
            return response()->json(['success' => false, 'message' => 'Kode tidak ditemukan atau sudah dipakai'], 404);
        }
        return response()->json(['success' => true]);
    }

    /**
     * Update status reservasi dari POS (mis. Set Datang -> arrived, Cancel -> cancelled).
     * PATCH /api/reservations/{id}/status  body: { "status": "arrived" }
     */
    public function apiUpdateStatus(Request $request, $id)
    {
        $status = $request->input('status');
        $allowed = ['pending', 'confirmed', 'arrived', 'cancelled', 'no_show'];
        if (!$status || !in_array($status, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Status tidak valid. Gunakan: ' . implode(', ', $allowed)], 400);
        }
        $reservation = Reservation::find($id);
        if (!$reservation) {
            return response()->json(['success' => false, 'message' => 'Reservasi tidak ditemukan'], 404);
        }
        $reservation->status = $status;
        $reservation->save();
        return response()->json(['success' => true, 'status' => $reservation->status]);
    }

    public function apiShow($id)
    {
        $reservation = Reservation::with(['outlet', 'creator', 'salesUser', 'paymentType'])->find($id);
        if (!$reservation) {
            return response()->json(['message' => 'Reservasi tidak ditemukan'], 404);
        }
        return response()->json([
            'id' => $reservation->id,
            'reservation_number' => $reservation->reservation_number,
            'name' => $reservation->name,
            'phone' => $reservation->phone,
            'email' => $reservation->email,
            'outlet_id' => $reservation->outlet_id,
            'outlet' => $reservation->outlet ? $reservation->outlet->nama_outlet : null,
            'reservation_date' => $reservation->reservation_date?->format('Y-m-d'),
            'reservation_time' => $reservation->reservation_time ? \Carbon\Carbon::parse($reservation->reservation_time)->format('H:i') : null,
            'number_of_guests' => $reservation->number_of_guests,
            'smoking_preference' => $reservation->smoking_preference,
            'special_requests' => $reservation->special_requests,
            'dp' => $reservation->dp ? (float) $reservation->dp : null,
            'payment_type_id' => $reservation->payment_type_id,
            'payment_type_name' => $reservation->paymentType?->name,
            'dp_code' => $reservation->dp_code,
            'dp_used_at' => $reservation->dp_used_at?->toIso8601String(),
            'from_sales' => (bool) $reservation->from_sales,
            'sales_user_id' => $reservation->sales_user_id,
            'sales_user_name' => $reservation->salesUser ? $reservation->salesUser->nama_lengkap : null,
            'menu' => $reservation->menu,
            'menu_file' => $reservation->menu_file,
            'menu_file_url' => $reservation->menu_file ? Storage::disk('public')->url($reservation->menu_file) : null,
            'status' => $reservation->status,
            'created_by' => $reservation->creator ? ($reservation->creator->nama_lengkap ?? $reservation->creator->name ?? null) : null,
            'created_at' => $reservation->created_at?->toIso8601String(),
        ]);
    }

    public function apiStatusByNumber(Request $request)
    {
        $request->validate([
            'reservation_number' => 'required|string|max:32',
        ]);

        $reservationNumber = strtoupper(trim((string) $request->input('reservation_number')));
        $reservation = Reservation::with(['outlet'])
            ->where('reservation_number', $reservationNumber)
            ->first();

        if (!$reservation) {
            return response()->json([
                'message' => 'Nomor reservasi tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'reservation_number' => $reservation->reservation_number,
            'name' => $reservation->name,
            'phone' => $reservation->phone,
            'outlet' => $reservation->outlet ? $reservation->outlet->nama_outlet : null,
            'reservation_date' => $reservation->reservation_date?->format('Y-m-d'),
            'reservation_time' => $reservation->reservation_time ? \Carbon\Carbon::parse($reservation->reservation_time)->format('H:i') : null,
            'number_of_guests' => $reservation->number_of_guests,
            'smoking_preference' => $reservation->smoking_preference,
            'status' => $reservation->status,
            'created_at' => $reservation->created_at?->toIso8601String(),
        ]);
    }

    public function apiStore(Request $request)
    {
        try {
            $request->merge([
                'reservation_date' => trim((string) $request->input('reservation_date', '')),
                'reservation_time' => trim((string) $request->input('reservation_time', '')),
            ]);
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:100',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'reservation_date' => 'required|date_format:Y-m-d',
                'reservation_time' => 'required',
                'number_of_guests' => 'required|integer|min:1',
                'selected_table_ids' => 'nullable|array|min:1',
                'selected_table_ids.*' => 'integer|min:1',
                'smoking_preference' => 'nullable|in:smoking,non_smoking',
                'special_requests' => 'nullable|string',
                'dp' => 'nullable|numeric|min:0',
                'payment_type_id' => 'nullable|exists:payment_types,id',
                'from_sales' => 'nullable|boolean',
                'sales_user_id' => 'nullable|exists:users,id',
                'menu' => 'nullable|string',
                'menu_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,xls,xlsx|max:10240',
                'status' => 'required|in:pending,confirmed,cancelled',
            ]);

            $dpProvided = array_key_exists('dp', $validated) && $validated['dp'] !== null;
            if (!$dpProvided) {
                $validated['dp'] = (float) ($validated['number_of_guests'] ?? 0) * 100000;
            }

            if (empty($validated['payment_type_id'])) {
                $validated['payment_type_id'] = $this->resolveDefaultQrisPaymentTypeId();
            }

            $validated['created_by'] = auth()->check() ? auth()->id() : null;
            $validated['email'] = $request->filled('email') ? trim((string) $request->input('email')) : null;
            $validated['from_sales'] = filter_var($request->input('from_sales'), FILTER_VALIDATE_BOOLEAN);
            if (empty($validated['from_sales'])) {
                $validated['sales_user_id'] = null;
            }
            if ($request->hasFile('menu_file')) {
                $validated['menu_file'] = $request->file('menu_file')->storeAs(
                    'reservations/menu',
                    Str::uuid() . '.' . $request->file('menu_file')->getClientOriginalExtension(),
                    'public'
                );
            } else {
                unset($validated['menu_file']);
            }
            $this->ensureReservationDateNotInPast((string) $validated['reservation_date']);
            if ($reject = $this->rejectIfSameDayReservationTooSoon(
                (string) $validated['reservation_date'],
                (string) $validated['reservation_time']
            )) {
                return $reject;
            }
            $reservation = Reservation::create($validated);
            $this->assignReservationNumberIfMissing($reservation);

            if (!$dpProvided && (float) $reservation->dp > 0) {
                $uniqueDp = $this->buildUniqueDpAmount((float) $reservation->dp, (string) $reservation->reservation_number);
                if ($uniqueDp > 0 && (float) $reservation->dp !== (float) $uniqueDp) {
                    $reservation->dp = $uniqueDp;
                    $reservation->saveQuietly();
                }
            }

            $this->syncDpCode($reservation, (float) ($reservation->dp ?? 0));
            $reservation->load('paymentType');

            return response()->json([
                'message' => 'Reservasi berhasil ditambahkan',
                'id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
                'dp' => $reservation->dp ? (float) $reservation->dp : null,
                'dp_code' => $reservation->dp_code,
                'payment_type_id' => $reservation->payment_type_id,
                'payment_type_name' => $reservation->paymentType?->name,
                'payment_verification_status' => $reservation->dp_used_at ? 'verified' : 'pending',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            \Log::error('Reservation apiStore: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal menyimpan reservasi'], 500);
        }
    }

    /**
     * Check availability + return table layout snapshot for reservation flow (public website).
     */
    public function apiAvailabilityLayout(Request $request)
    {
        try {
            $request->merge([
                'reservation_date' => trim((string) $request->input('reservation_date', '')),
                'reservation_time' => trim((string) $request->input('reservation_time', '')),
            ]);
            $validated = $request->validate([
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'reservation_date' => 'required|date_format:Y-m-d',
                'reservation_time' => 'required',
                'number_of_guests' => 'required|integer|min:1',
                'smoking_preference' => 'nullable|in:smoking,non_smoking',
                'reservation_duration_minutes' => 'nullable|integer|min:30|max:360',
            ]);
            $this->ensureReservationDateNotInPast((string) $validated['reservation_date']);

            if ($reject = $this->rejectIfSameDayReservationTooSoon(
                (string) $validated['reservation_date'],
                (string) $validated['reservation_time']
            )) {
                return $reject;
            }

            $outlet = Outlet::where('id_outlet', $validated['outlet_id'])->first();
            if (!$outlet) {
                return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
            }

            $kodeOutlet = trim((string) ($outlet->qr_code ?? ''));
            if ($kodeOutlet === '') {
                return response()->json(['message' => 'Outlet belum memiliki kode outlet (qr_code)'], 422);
            }

            $guestCount = (int) $validated['number_of_guests'];
            $smokingPreference = $validated['smoking_preference'] ?? null;
            $durationMinutes = (int) ($validated['reservation_duration_minutes'] ?? self::DEFAULT_RESERVATION_DURATION_MINUTES);
            $hasSettingTable = Schema::hasTable('pos_design_table_reservation_settings');
            $hasSmokingTypeColumn = $hasSettingTable && Schema::hasColumn('pos_design_table_reservation_settings', 'smoking_type');
            $occupiedTableIds = $this->getOccupiedTableIdsForWindow(
                (int) $validated['outlet_id'],
                (string) $validated['reservation_date'],
                (string) $validated['reservation_time'],
                $durationMinutes
            );

            $sections = DB::table('pos_design_sections_sync')
                ->where('kode_outlet', $kodeOutlet)
                ->orderBy('source_section_id')
                ->get(['source_section_id', 'nama'])
                ->values();

            $accessoriesBySection = DB::table('pos_design_accessories_sync')
                ->where('kode_outlet', $kodeOutlet)
                ->orderBy('source_accessory_id')
                ->get([
                    'source_accessory_id',
                    'source_section_id',
                    'type',
                    'x',
                    'y',
                    'panjang',
                    'orientasi',
                ])
                ->groupBy('source_section_id')
                ->map(function ($items) {
                    return $items->values();
                });

            $reservationSettings = collect();
            if ($hasSettingTable) {
                $settingColumns = ['source_table_id', 'allow_reservation'];
                if ($hasSmokingTypeColumn) {
                    $settingColumns[] = 'smoking_type';
                }
                $reservationSettings = DB::table('pos_design_table_reservation_settings')
                    ->where('kode_outlet', $kodeOutlet)
                    ->get($settingColumns)
                    ->keyBy('source_table_id');
            }

            $allTables = DB::table('pos_design_tables_sync')
                ->where('kode_outlet', $kodeOutlet)
                ->orderBy('source_table_id')
                ->get([
                    'source_table_id',
                    'source_section_id',
                    'nama',
                    'tipe',
                    'bentuk',
                    'orientasi',
                    'jumlah_kursi',
                    'warna',
                    'x',
                    'y',
                ]);

            $availableCount = 0;
            $eligibleTablesForCombination = collect();
            $tablesBySection = $allTables
                ->map(function ($table) use ($reservationSettings, $guestCount, &$availableCount) {
                    $setting = $reservationSettings[(int) $table->source_table_id] ?? $reservationSettings[(string) $table->source_table_id] ?? null;
                    $allowReservation = $setting ? ((int) $setting->allow_reservation === 1) : true;
                    $tableSmokingType = ($setting && !empty($setting->smoking_type)) ? $setting->smoking_type : 'non_smoking';
                    $seatingCapacity = max(0, (int) ($table->jumlah_kursi ?? 0));
                    $isReservableType = (($table->tipe ?? 'biasa') === 'biasa');
                    // Smoking vs non-smoking is indicated per table (S/NS on the website); do not hide tables by step-2 preference.
                    $capacityMatch = $seatingCapacity >= $guestCount;
                    $isSelectable = $isReservableType && $allowReservation && $seatingCapacity > 0;
                    $isAvailable = $isSelectable && $capacityMatch;

                    if ($isAvailable) {
                        $availableCount++;
                    }

                    $table->allow_reservation = $allowReservation;
                    $table->smoking_type = $tableSmokingType;
                    $table->seating_capacity = $seatingCapacity;
                    $table->selectable = $isSelectable;
                    $table->available = $isAvailable;

                    return $table;
                })
                ->groupBy('source_section_id')
                ->map(function ($items) {
                    return $items->values();
                });

            $eligibleTablesForCombination = $allTables
                ->map(function ($table) use ($reservationSettings) {
                    $setting = $reservationSettings[(int) $table->source_table_id] ?? $reservationSettings[(string) $table->source_table_id] ?? null;
                    $allowReservation = $setting ? ((int) $setting->allow_reservation === 1) : true;
                    $seatingCapacity = max(0, (int) ($table->jumlah_kursi ?? 0));
                    $isReservableType = (($table->tipe ?? 'biasa') === 'biasa');

                    return (object) [
                        'source_table_id' => (int) $table->source_table_id,
                        'source_section_id' => (int) $table->source_section_id,
                        'nama' => $table->nama,
                        'seating_capacity' => $seatingCapacity,
                        'is_candidate' => $isReservableType && $allowReservation && $seatingCapacity > 0,
                    ];
                })
                ->filter(function ($table) {
                    return $table->is_candidate;
                })
                ->sortByDesc('seating_capacity')
                ->values();

            $tableCombinations = [];
            $maxCandidates = min(24, $eligibleTablesForCombination->count());
            $candidate = $eligibleTablesForCombination->take($maxCandidates)->values();

            $pushCombination = function (array $tables) use (&$tableCombinations, $guestCount) {
                $totalSeats = array_sum(array_map(function ($table) {
                    return (int) $table->seating_capacity;
                }, $tables));
                if ($totalSeats < $guestCount) {
                    return;
                }

                $tableIds = array_map(function ($table) {
                    return (int) $table->source_table_id;
                }, $tables);
                sort($tableIds);
                $key = implode('-', $tableIds);
                if (isset($tableCombinations[$key])) {
                    return;
                }

                $tableCombinations[$key] = [
                    'table_ids' => $tableIds,
                    'table_names' => array_values(array_map(function ($table) {
                        return (string) ($table->nama ?: ('T-' . $table->source_table_id));
                    }, $tables)),
                    'total_seats' => $totalSeats,
                    'table_count' => count($tableIds),
                    'seat_excess' => $totalSeats - $guestCount,
                ];
            };

            for ($i = 0; $i < $maxCandidates; $i++) {
                $t1 = $candidate[$i];
                $pushCombination([$t1]);
                for ($j = $i + 1; $j < $maxCandidates; $j++) {
                    $t2 = $candidate[$j];
                    $pushCombination([$t1, $t2]);
                    for ($k = $j + 1; $k < $maxCandidates; $k++) {
                        $t3 = $candidate[$k];
                        $pushCombination([$t1, $t2, $t3]);
                    }
                }
            }

            $tableCombinations = collect($tableCombinations)
                ->values()
                ->sort(function ($a, $b) {
                    if ($a['seat_excess'] !== $b['seat_excess']) {
                        return $a['seat_excess'] <=> $b['seat_excess'];
                    }
                    if ($a['table_count'] !== $b['table_count']) {
                        return $a['table_count'] <=> $b['table_count'];
                    }
                    return $a['total_seats'] <=> $b['total_seats'];
                })
                ->take(8)
                ->values();

            $tablesBySection = $tablesBySection->map(function ($items) use ($occupiedTableIds) {
                return $items->map(function ($table) use ($occupiedTableIds) {
                    $tableId = (int) $table->source_table_id;
                    $isOccupied = in_array($tableId, $occupiedTableIds, true);
                    if ($isOccupied) {
                        $table->available = false;
                        $table->selectable = false;
                    }
                    $table->occupied = $isOccupied;
                    return $table;
                })->values();
            });

            $tableCombinations = $tableCombinations
                ->filter(function ($combo) use ($occupiedTableIds) {
                    foreach ($combo['table_ids'] as $tableId) {
                        if (in_array((int) $tableId, $occupiedTableIds, true)) {
                            return false;
                        }
                    }
                    return true;
                })
                ->values();

            $availableCount = $tablesBySection
                ->flatten(1)
                ->filter(function ($table) {
                    return (bool) ($table->available ?? false);
                })
                ->count();

            return response()->json([
                'message' => $availableCount > 0 ? 'Table tersedia' : 'Tidak ada meja yang cocok',
                'data' => [
                    'outlet' => [
                        'id' => (int) $outlet->id_outlet,
                        'kode_outlet' => $kodeOutlet,
                        'name' => $outlet->nama_outlet,
                    ],
                    'filters' => [
                        'reservation_date' => $validated['reservation_date'],
                        'reservation_time' => $validated['reservation_time'],
                        'number_of_guests' => $guestCount,
                        'smoking_preference' => $smokingPreference,
                        'reservation_duration_minutes' => $durationMinutes,
                    ],
                    'sections' => $sections,
                    'tables_by_section' => $tablesBySection,
                    'accessories_by_section' => $accessoriesBySection,
                    'available_table_count' => $availableCount,
                    'table_combinations' => $tableCombinations,
                ],
            ], 200, [
                'X-Ymsoft-Reservation-Availability' => self::PUBLIC_RESERVATION_AVAILABILITY_VERSION,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $e->errors()], 422, [
                'X-Ymsoft-Reservation-Availability' => self::PUBLIC_RESERVATION_AVAILABILITY_VERSION,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Reservation apiAvailabilityLayout: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal cek ketersediaan meja'], 500);
        }
    }

    /**
     * Calendar date (Y-m-d) must not be before today in app timezone (avoids same-day false rejects from loose date rules).
     */
    private function ensureReservationDateNotInPast(string $dateYmd): void
    {
        $tz = config('app.timezone');
        $parsed = Carbon::createFromFormat('Y-m-d', $dateYmd, $tz);
        if ($parsed === false) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reservation_date' => ['Format tanggal reservasi tidak valid (gunakan YYYY-MM-DD).'],
            ]);
        }
        $chosen = $parsed->copy()->startOfDay();
        $today = Carbon::now($tz)->startOfDay();
        if ($chosen->lt($today)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reservation_date' => ['Tanggal reservasi tidak boleh sebelum hari ini.'],
            ]);
        }
    }

    /**
     * If reservation is on today's date (app timezone), start time must be at least SAME_DAY_MIN_LEAD_HOURS from now.
     */
    private function rejectIfSameDayReservationTooSoon(string $reservationDate, string $reservationTime): ?\Illuminate\Http\JsonResponse
    {
        $tz = config('app.timezone');
        try {
            $dateOnly = Carbon::parse($reservationDate, $tz)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
        if ($dateOnly !== Carbon::now($tz)->format('Y-m-d')) {
            return null;
        }
        $timePart = trim($reservationTime);
        if ($timePart === '') {
            return null;
        }
        try {
            $requested = Carbon::parse($dateOnly . ' ' . $timePart, $tz);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Format waktu reservasi tidak valid.',
            ], 422);
        }
        $minStart = Carbon::now($tz)->addHours(self::SAME_DAY_MIN_LEAD_HOURS);
        if ($requested->lt($minStart)) {
            return response()->json([
                'message' => 'Untuk reservasi hari ini, pilih waktu mulai minimal ' . self::SAME_DAY_MIN_LEAD_HOURS . ' jam dari sekarang.',
            ], 422);
        }

        return null;
    }

    private function getOccupiedTableIdsForWindow(
        int $outletId,
        string $reservationDate,
        string $reservationTime,
        int $durationMinutes
    ): array {
        if (!Schema::hasTable('reservations') || !Schema::hasColumn('reservations', 'selected_table_ids')) {
            return [];
        }

        $windowStart = Carbon::parse($reservationDate . ' ' . $reservationTime);
        $windowEnd = (clone $windowStart)->addMinutes($durationMinutes);

        $rows = DB::table('reservations')
            ->where('outlet_id', $outletId)
            ->whereDate('reservation_date', $reservationDate)
            ->whereIn('status', ['pending', 'confirmed', 'arrived'])
            ->whereNotNull('selected_table_ids')
            ->get(['reservation_time', 'selected_table_ids']);

        $occupied = [];
        foreach ($rows as $row) {
            $rowStart = Carbon::parse($reservationDate . ' ' . (string) $row->reservation_time);
            $rowEnd = (clone $rowStart)->addMinutes(self::DEFAULT_RESERVATION_DURATION_MINUTES);

            $isOverlap = $windowStart < $rowEnd && $rowStart < $windowEnd;
            if (!$isOverlap) {
                continue;
            }

            $tableIds = json_decode((string) $row->selected_table_ids, true);
            if (!is_array($tableIds)) {
                continue;
            }
            foreach ($tableIds as $tableId) {
                $id = (int) $tableId;
                if ($id > 0) {
                    $occupied[$id] = true;
                }
            }
        }

        return array_map('intval', array_keys($occupied));
    }

    public function apiUpdate(Request $request, $id)
    {
        $reservation = Reservation::find($id);
        if (!$reservation) {
            return response()->json(['message' => 'Reservasi tidak ditemukan'], 404);
        }
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:100',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'reservation_date' => 'required|date',
                'reservation_time' => 'required',
                'number_of_guests' => 'required|integer|min:1',
                'smoking_preference' => 'nullable|in:smoking,non_smoking',
                'special_requests' => 'nullable|string',
                'dp' => 'nullable|numeric|min:0',
                'payment_type_id' => 'nullable|exists:payment_types,id',
                'from_sales' => 'nullable|boolean',
                'sales_user_id' => 'nullable|exists:users,id',
                'menu' => 'nullable|string',
                'menu_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,xls,xlsx|max:10240',
                'status' => 'required|in:pending,confirmed,cancelled',
            ]);
            $validated['email'] = $request->filled('email') ? trim((string) $request->input('email')) : null;
            $validated['from_sales'] = filter_var($request->input('from_sales'), FILTER_VALIDATE_BOOLEAN);
            if (empty($validated['from_sales'])) {
                $validated['sales_user_id'] = null;
            }
            if ($request->hasFile('menu_file')) {
                if ($reservation->menu_file && Storage::disk('public')->exists($reservation->menu_file)) {
                    Storage::disk('public')->delete($reservation->menu_file);
                }
                $validated['menu_file'] = $request->file('menu_file')->storeAs(
                    'reservations/menu',
                    Str::uuid() . '.' . $request->file('menu_file')->getClientOriginalExtension(),
                    'public'
                );
            } else {
                unset($validated['menu_file']);
            }
            if ($reject = $this->rejectIfSameDayReservationTooSoon(
                (string) $validated['reservation_date'],
                (string) $validated['reservation_time']
            )) {
                return $reject;
            }
            $reservation->update($validated);
            return response()->json(['message' => 'Reservasi berhasil diupdate']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            \Log::error('Reservation apiUpdate: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal mengupdate reservasi'], 500);
        }
    }

    /**
     * Payment types untuk form reservasi: dipakai per outlet/region.
     * Return array dengan id, name, code, outlet_ids, region_ids agar frontend bisa filter by outlet.
     */
    private function getPaymentTypesForReservationForm(): array
    {
        $paymentTypes = PaymentType::where('status', 'active')
            ->with(['outlets:id_outlet', 'regions:id'])
            ->orderBy('name')
            ->get();

        return $paymentTypes->map(function ($pt) {
            return [
                'id' => $pt->id,
                'name' => $pt->name,
                'code' => $pt->code,
                'outlet_ids' => $pt->outlets->pluck('id_outlet')->values()->all(),
                'region_ids' => $pt->regions->pluck('id')->values()->all(),
            ];
        })->values()->all();
    }

    private function resolveDefaultQrisPaymentTypeId(): ?int
    {
        $qris = PaymentType::query()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereRaw('LOWER(COALESCE(code, "")) like ?', ['%qris%'])
                    ->orWhereRaw('LOWER(COALESCE(name, "")) like ?', ['%qris%']);
            })
            ->orderBy('id')
            ->first(['id']);

        return $qris?->id;
    }

    private function buildUniqueDpAmount(float $baseAmount, string $reservationNumber): float
    {
        $base = (int) round($baseAmount);
        if ($base <= 0) {
            return 0;
        }

        // Unique suffix untuk transfer DP: random 1..100 sesuai kebutuhan bisnis.
        $uniqueCode = random_int(1, 100);
        return (float) ($base + $uniqueCode);
    }
} 