<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        // Search functionality
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Filter by status aktif
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        // Filter by status block
        if ($request->filled('block_status')) {
            $query->byBlockStatus($request->block_status);
        }

        // Filter by exclusive member
        if ($request->filled('exclusive')) {
            if ($request->exclusive === 'yes') {
                $query->exclusive();
            }
        }

        // Filter by point balance
        if ($request->filled('point_balance')) {
            $pointFilter = $request->point_balance;
            // We'll apply this filter after calculating point balance
        }

        // Sort
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');
        
        // Handle point balance sorting
        if ($sort === 'point_balance') {
            // For point balance sorting, we need to sort after calculating the balance
            $query->orderBy('created_at', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $members = $query->paginate($perPage)->withQueryString();

        // Calculate point balance for each member
        $members->getCollection()->transform(function ($member) {
            // Calculate point balance: sum(type=1) - sum(type=2)
            $pointBalance = DB::connection('mysql_second')
                ->table('point')
                ->where('costumer_id', $member->id)
                ->selectRaw('
                    COALESCE(SUM(CASE WHEN type = "1" THEN point ELSE 0 END), 0) as total_earned,
                    COALESCE(SUM(CASE WHEN type = "2" THEN point ELSE 0 END), 0) as total_redeemed
                ')
                ->first();

            $member->point_balance = ($pointBalance->total_earned ?? 0) - ($pointBalance->total_redeemed ?? 0);
            $member->point_balance_formatted = number_format($member->point_balance, 0, ',', '.');
            
            return $member;
        });

        // Sort by point balance if requested
        if ($sort === 'point_balance') {
            $members->getCollection()->sortBy(function ($member) use ($direction) {
                return $direction === 'desc' ? -$member->point_balance : $member->point_balance;
            });
        }

        // Filter by point balance after calculation
        if ($request->filled('point_balance')) {
            $pointFilter = $request->point_balance;
            $members->getCollection()->transform(function ($member) use ($pointFilter) {
                $showMember = true;
                
                switch ($pointFilter) {
                    case 'positive':
                        $showMember = $member->point_balance > 0;
                        break;
                    case 'negative':
                        $showMember = $member->point_balance < 0;
                        break;
                    case 'zero':
                        $showMember = $member->point_balance == 0;
                        break;
                    case 'high':
                        $showMember = $member->point_balance >= 1000;
                        break;
                }
                
                $member->show_in_filter = $showMember;
                return $member;
            });
            
            // Filter out members that don't match the criteria
            $members->setCollection($members->getCollection()->filter(function ($member) {
                return $member->show_in_filter;
            }));
        }

        // Get statistics
        $stats = [
            'total_members' => Customer::count(),
            'active_members' => Customer::where('status_aktif', '1')->count(),
            'inactive_members' => Customer::where('status_aktif', '0')->count(),
            'exclusive_members' => Customer::where('exclusive_member', 'Y')->count(),
        ];

        // Calculate total point statistics
        $pointStats = DB::connection('mysql_second')
            ->table('point')
            ->selectRaw('
                COALESCE(SUM(CASE WHEN type = "1" THEN point ELSE 0 END), 0) as total_earned,
                COALESCE(SUM(CASE WHEN type = "2" THEN point ELSE 0 END), 0) as total_redeemed,
                COUNT(DISTINCT costumer_id) as members_with_points
            ')
            ->first();

        $stats['total_point_earned'] = $pointStats->total_earned ?? 0;
        $stats['total_point_redeemed'] = $pointStats->total_redeemed ?? 0;
        $stats['total_point_balance'] = ($pointStats->total_earned ?? 0) - ($pointStats->total_redeemed ?? 0);
        $stats['members_with_points'] = $pointStats->members_with_points ?? 0;

        // Format numbers
        $stats['total_point_earned_formatted'] = number_format($stats['total_point_earned'], 0, ',', '.');
        $stats['total_point_redeemed_formatted'] = number_format($stats['total_point_redeemed'], 0, ',', '.');
        $stats['total_point_balance_formatted'] = number_format($stats['total_point_balance'], 0, ',', '.');

        return Inertia::render('Members/Index', [
            'members' => $members,
            'filters' => $request->only(['search', 'status', 'block_status', 'exclusive', 'sort', 'direction', 'per_page']),
            'stats' => $stats,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Members/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'costumers_id' => 'required|string|max:50|unique:mysql_second.costumers,costumers_id',
            'nik' => 'nullable|string|max:20',
            'name' => 'required|string|max:255',
            'nama_panggilan' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'alamat' => 'nullable|string',
            'telepon' => 'nullable|string|max:20',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'pekerjaan' => 'nullable|string|max:100',
            'valid_until' => 'required|date',
            'password2' => 'nullable|string|max:255',
            'android_password' => 'nullable|string|max:255',
            'pin' => 'nullable|string|max:10',
            'exclusive_member' => 'required|in:Y,N',
        ]);

        try {
            DB::connection('mysql_second')->beginTransaction();

            // Set nilai default untuk field yang dihilangkan
            $memberData = $request->all();
            $memberData['status_aktif'] = '1'; // Otomatis aktif
            $memberData['status_block'] = 'N'; // Otomatis tidak diblokir
            $memberData['tanggal_aktif'] = now()->toDateString(); // Tanggal hari ini
            $memberData['tanggal_register'] = now()->toDateString(); // Tanggal hari ini
            $memberData['hint'] = $request->password2; // Hint = password (tidak di hash)
            $memberData['barcode'] = null; // Kosong
            $memberData['device'] = null; // Kosong

            $member = Customer::create($memberData);

            DB::connection('mysql_second')->commit();

            return redirect()->route('members.index')
                ->with('success', 'Member berhasil ditambahkan!');

        } catch (\Exception $e) {
            DB::connection('mysql_second')->rollBack();
            return back()->withErrors(['error' => 'Gagal menambahkan member: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $member)
    {
        return Inertia::render('Members/Show', [
            'member' => $member
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $member)
    {
        return Inertia::render('Members/Edit', [
            'member' => $member
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $member)
    {
        $request->validate([
            'costumers_id' => 'required|string|max:50|unique:mysql_second.costumers,costumers_id,' . $member->id,
            'nik' => 'nullable|string|max:20',
            'name' => 'required|string|max:255',
            'nama_panggilan' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'alamat' => 'nullable|string',
            'telepon' => 'nullable|string|max:20',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'pekerjaan' => 'nullable|string|max:100',
            'valid_until' => 'required|date',
            'password2' => 'nullable|string|max:255',
            'android_password' => 'nullable|string|max:255',
            'pin' => 'nullable|string|max:10',
            'exclusive_member' => 'required|in:Y,N',
        ]);

        try {
            DB::connection('mysql_second')->beginTransaction();

            // Update data member
            $memberData = $request->all();
            $memberData['hint'] = $request->password2; // Hint = password (tidak di hash)

            $member->update($memberData);

            DB::connection('mysql_second')->commit();

            return redirect()->route('members.index')
                ->with('success', 'Member berhasil diperbarui!');

        } catch (\Exception $e) {
            DB::connection('mysql_second')->rollBack();
            return back()->withErrors(['error' => 'Gagal memperbarui member: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $member)
    {
        try {
            DB::connection('mysql_second')->beginTransaction();

            $member->delete();

            DB::connection('mysql_second')->commit();

            return redirect()->route('members.index')
                ->with('success', 'Member berhasil dihapus!');

        } catch (\Exception $e) {
            DB::connection('mysql_second')->rollBack();
            return back()->withErrors(['error' => 'Gagal menghapus member: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle status aktif member
     */
    public function toggleStatus(Customer $member)
    {
        try {
            DB::connection('mysql_second')->beginTransaction();

            $member->update([
                'status_aktif' => $member->status_aktif === '1' ? '0' : '1'
            ]);

            DB::connection('mysql_second')->commit();

            return back()->with('success', 'Status member berhasil diubah!');

        } catch (\Exception $e) {
            DB::connection('mysql_second')->rollBack();
            return back()->withErrors(['error' => 'Gagal mengubah status member: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle status block member
     */
    public function toggleBlock(Customer $member)
    {
        try {
            DB::connection('mysql_second')->beginTransaction();

            $member->update([
                'status_block' => $member->status_block === 'Y' ? 'N' : 'Y'
            ]);

            DB::connection('mysql_second')->commit();

            return back()->with('success', 'Status block member berhasil diubah!');

        } catch (\Exception $e) {
            DB::connection('mysql_second')->rollBack();
            return back()->withErrors(['error' => 'Gagal mengubah status block member: ' . $e->getMessage()]);
        }
    }

    /**
     * Export members to Excel
     */
    public function export(Request $request)
    {
        $query = Customer::query();

        // Apply filters
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('block_status')) {
            $query->byBlockStatus($request->block_status);
        }

        if ($request->filled('exclusive')) {
            if ($request->exclusive === 'yes') {
                $query->exclusive();
            }
        }

        $members = $query->get();

        // Generate Excel file
        $filename = 'members_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        // You can implement Excel export here using Laravel Excel or similar package
        // For now, we'll return a simple response
        
        return response()->json([
            'success' => true,
            'message' => 'Export berhasil dibuat',
            'filename' => $filename,
            'count' => $members->count()
        ]);
    }

    /**
     * Get member transactions and point statistics
     */
    public function getTransactions($id)
    {
        try {
            $member = Customer::findOrFail($id);
            
            // Get point statistics
            $pointStats = DB::connection('mysql_second')
                ->table('point')
                ->where('costumer_id', $member->id)
                ->selectRaw('
                    COALESCE(SUM(CASE WHEN type = "1" THEN point ELSE 0 END), 0) as total_earned,
                    COALESCE(SUM(CASE WHEN type = "2" THEN point ELSE 0 END), 0) as total_redeemed
                ')
                ->first();

            $totalEarned = $pointStats->total_earned ?? 0;
            $totalRedeemed = $pointStats->total_redeemed ?? 0;
            $balance = $totalEarned - $totalRedeemed;

            // Get transaction history with outlet and bill info
            $transactions = DB::connection('mysql_second')
                ->table('point as p')
                ->select([
                    'p.id',
                    'p.point',
                    'p.jml_trans',
                    'p.type',
                    'p.no_bill',
                    'p.no_bill_2',
                    'p.created_at',
                    'cb.name as outlet_name',
                    'cb.alamat as outlet_alamat'
                ])
                ->leftJoin('cabangs as cb', 'p.cabang_id', '=', 'cb.id')
                ->where('p.costumer_id', $member->id)
                ->orderBy('p.created_at', 'desc')
                ->limit(50) // Limit to last 50 transactions
                ->get();

            // Transform transaction data and get order details for EARNED transactions
            $transactions = $transactions->map(function ($transaction) {
                $orderDetails = [];
                
                // Only get order details for EARNED transactions (type = '1') and if no_bill exists
                if ($transaction->type === '1' && $transaction->no_bill) {
                    try {
                        // First get order ID from orders table using paid_number
                        $order = DB::connection('db_justus')
                            ->table('orders')
                            ->where('paid_number', $transaction->no_bill)
                            ->first();
                        
                        if ($order) {
                            // Then get order items using order_id
                            $orderDetails = DB::connection('db_justus')
                                ->table('order_items')
                                ->select([
                                    'item_name',
                                    'qty',
                                    'price',
                                    'modifiers',
                                    'notes'
                                ])
                                ->where('order_id', $order->id)
                                ->get()
                                ->map(function ($item) {
                                    $modifiers = [];
                                    if ($item->modifiers) {
                                        try {
                                            $modifiers = json_decode($item->modifiers, true) ?: [];
                                        } catch (\Exception $e) {
                                            $modifiers = [];
                                        }
                                    }

                                    return [
                                        'item_name' => $item->item_name,
                                        'qty' => $item->qty,
                                        'price' => $item->price,
                                        'price_formatted' => 'Rp ' . number_format($item->price, 0, ',', '.'),
                                        'total_price' => $item->qty * $item->price,
                                        'total_price_formatted' => 'Rp ' . number_format($item->qty * $item->price, 0, ',', '.'),
                                        'modifiers' => $modifiers,
                                        'modifiers_formatted' => $this->formatModifiers($modifiers),
                                        'notes' => $item->notes
                                    ];
                                });
                        } else {
                            $orderDetails = collect([]);
                        }
                    } catch (\Exception $e) {
                        // If there's an error getting order details, just continue with empty array
                        $orderDetails = collect([]);
                    }
                }

                return [
                    'id' => $transaction->id,
                    'point' => $transaction->point,
                    'jml_trans' => $transaction->jml_trans,
                    'jml_trans_formatted' => $transaction->jml_trans ? 'Rp ' . number_format($transaction->jml_trans, 0, ',', '.') : '-',
                    'type' => $transaction->type,
                    'type_text' => $transaction->type === '1' ? 'EARNED' : 'REDEEMED',
                    'no_bill' => $transaction->type === '1' ? $transaction->no_bill : $transaction->no_bill_2,
                    'outlet_name' => $transaction->outlet_name ?: 'Outlet Tidak Diketahui',
                    'outlet_alamat' => $transaction->outlet_alamat,
                    'created_at' => $transaction->created_at,
                    'description' => $transaction->type === '1' 
                        ? 'Top Up Point dari Transaksi' 
                        : 'Redeem Point untuk Transaksi',
                    'order_details' => $orderDetails
                ];
            });

            return response()->json([
                'status' => 'success',
                'member' => $member,
                'stats' => [
                    'total_earned' => $totalEarned,
                    'total_redeemed' => $totalRedeemed,
                    'balance' => $balance,
                    'total_earned_formatted' => number_format($totalEarned, 0, ',', '.'),
                    'total_redeemed_formatted' => number_format($totalRedeemed, 0, ',', '.'),
                    'balance_formatted' => number_format($balance, 0, ',', '.')
                ],
                'transactions' => $transactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get member preferences (favorite menu items)
     */
    public function getPreferences($id)
    {
        try {
            $member = Customer::findOrFail($id);
            
            // Get member preferences by joining point table with orders and order_items
            // First, get all bill numbers from point table
            $billNumbers = DB::connection('mysql_second')
                ->table('point')
                ->where('costumer_id', $member->id)
                ->where('type', '1') // Only top up transactions (actual orders)
                ->whereNotNull('no_bill')
                ->pluck('no_bill')
                ->toArray();

            if (empty($billNumbers)) {
                return response()->json([
                    'status' => 'success',
                    'member' => $member,
                    'preferences' => [],
                    'summary' => [
                        'total_orders' => 0,
                        'total_items' => 0,
                        'total_spent' => 0,
                        'total_spent_formatted' => 'Rp 0',
                        'favorite_category' => 'Tidak ada data'
                    ]
                ]);
            }

            // Get preferences from db_justus database
            $preferences = DB::connection('db_justus')
                ->table('orders as o')
                ->select([
                    'oi.item_name as menu_name',
                    'oi.price as menu_price',
                    'c.name as menu_category',
                    DB::raw('COUNT(*) as order_count'),
                    DB::raw('SUM(oi.qty) as total_qty'),
                    DB::raw('SUM(oi.qty * oi.price) as total_spent'),
                    DB::raw('MAX(o.created_at) as last_ordered')
                ])
                ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
                ->leftJoin('items as i', 'oi.item_id', '=', 'i.id')
                ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
                ->whereIn('o.paid_number', $billNumbers)
                ->whereNotNull('oi.item_name')
                ->groupBy('oi.item_name', 'oi.price', 'c.name')
                ->orderBy('order_count', 'desc')
                ->orderBy('total_spent', 'desc')
                ->limit(10) // Top 10 favorite items
                ->get();

            // Get modifier details for each preference
            $preferences = $preferences->map(function ($pref) use ($billNumbers) {
                // Get all order items with modifiers for this specific menu
                $orderItems = DB::connection('db_justus')
                    ->table('orders as o')
                    ->select([
                        'oi.modifiers',
                        'oi.notes',
                        'o.created_at'
                    ])
                    ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
                    ->whereIn('o.paid_number', $billNumbers)
                    ->where('oi.item_name', $pref->menu_name)
                    ->where('oi.price', $pref->menu_price)
                    ->get();

                // Process modifiers
                $allModifiers = [];
                $allNotes = [];
                
                foreach ($orderItems as $item) {
                    if ($item->modifiers) {
                        try {
                            $modifiers = json_decode($item->modifiers, true) ?: [];
                            
                            // Handle both format types:
                            // Format 1: {"Tingkat Kematangan":{"Well Done":1},"Potato":{"Mashed Potato":1}}
                            // Format 2: [{"name":"modifier_name","options":[{"name":"option_name","qty":1,"price":0}]}]
                            
                            if (is_array($modifiers)) {
                                // Check if it's the flat key-value format
                                $isFlat = true;
                                foreach ($modifiers as $key => $value) {
                                    if (is_numeric($key) && is_array($value) && isset($value['name'])) {
                                        $isFlat = false;
                                        break;
                                    }
                                }
                                
                                if ($isFlat) {
                                    // Handle flat format: {"Tingkat Kematangan":{"Well Done":1}}
                                    foreach ($modifiers as $modifierName => $options) {
                                        if (is_array($options)) {
                                            $modifierKey = $modifierName;
                                            if (!isset($allModifiers[$modifierKey])) {
                                                $allModifiers[$modifierKey] = [
                                                    'name' => $modifierName,
                                                    'count' => 0,
                                                    'options' => []
                                                ];
                                            }
                                            $allModifiers[$modifierKey]['count']++;
                                            
                                            // Process options
                                            foreach ($options as $optionName => $optionQty) {
                                                $optionKey = $optionName;
                                                if (!isset($allModifiers[$modifierKey]['options'][$optionKey])) {
                                                    $allModifiers[$modifierKey]['options'][$optionKey] = [
                                                        'name' => $optionName,
                                                        'count' => 0,
                                                        'price' => 0 // Price not available in this format
                                                    ];
                                                }
                                                $allModifiers[$modifierKey]['options'][$optionKey]['count'] += (int)$optionQty;
                                            }
                                        }
                                    }
                                } else {
                                    // Handle structured format: [{"name":"modifier_name","options":[...]}]
                                    foreach ($modifiers as $modifier) {
                                        if (is_array($modifier) && isset($modifier['name'])) {
                                            $modifierKey = $modifier['name'];
                                            if (!isset($allModifiers[$modifierKey])) {
                                                $allModifiers[$modifierKey] = [
                                                    'name' => $modifier['name'],
                                                    'count' => 0,
                                                    'options' => []
                                                ];
                                            }
                                            $allModifiers[$modifierKey]['count']++;
                                            
                                            // Collect options
                                            foreach ($modifier['options'] ?? [] as $option) {
                                                $optionKey = $option['name'] ?? 'Unknown';
                                                if (!isset($allModifiers[$modifierKey]['options'][$optionKey])) {
                                                    $allModifiers[$modifierKey]['options'][$optionKey] = [
                                                        'name' => $option['name'] ?? 'Unknown',
                                                        'count' => 0,
                                                        'price' => $option['price'] ?? 0
                                                    ];
                                                }
                                                $allModifiers[$modifierKey]['options'][$optionKey]['count'] += ($option['qty'] ?? 1);
                                            }
                                        }
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // Skip invalid modifiers
                        }
                    }
                    
                    if ($item->notes) {
                        $allNotes[] = $item->notes;
                    }
                }

                return [
                    'menu_name' => $pref->menu_name,
                    'menu_price' => $pref->menu_price,
                    'menu_price_formatted' => 'Rp ' . number_format($pref->menu_price, 0, ',', '.'),
                    'menu_category' => $pref->menu_category,
                    'order_count' => $pref->order_count,
                    'total_qty' => $pref->total_qty,
                    'total_spent' => $pref->total_spent,
                    'total_spent_formatted' => 'Rp ' . number_format($pref->total_spent, 0, ',', '.'),
                    'last_ordered' => $pref->last_ordered,
                    'last_ordered_formatted' => $pref->last_ordered ? date('d/m/Y H:i', strtotime($pref->last_ordered)) : '-',
                    'modifiers' => array_values($allModifiers),
                    'notes' => array_unique(array_filter($allNotes))
                ];
            });



            // Get summary statistics
            $summary = [
                'total_orders' => $preferences->sum('order_count'),
                'total_items' => $preferences->sum('total_qty'),
                'total_spent' => $preferences->sum('total_spent'),
                'total_spent_formatted' => 'Rp ' . number_format($preferences->sum('total_spent'), 0, ',', '.'),
                'favorite_category' => $preferences->groupBy('menu_category')
                    ->map(function($items) { return $items->sum('order_count'); })
                    ->sortDesc()
                    ->keys()
                    ->first() ?: 'Tidak ada data'
            ];

            return response()->json([
                'status' => 'success',
                'member' => $member,
                'preferences' => $preferences,
                'summary' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data preferensi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format modifiers for display
     */
    private function formatModifiers($modifiers)
    {
        if (empty($modifiers)) {
            return '-';
        }

        $formatted = [];
        foreach ($modifiers as $modifier) {
            if (is_array($modifier) && isset($modifier['name'])) {
                $formatted[] = $modifier['name'];
            } elseif (is_string($modifier)) {
                $formatted[] = $modifier;
            }
        }

        return empty($formatted) ? '-' : implode(', ', $formatted);
    }
} 