<?php

namespace App\Http\Controllers;

use App\Models\MemberAppsMember;
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
        $query = MemberAppsMember::query();

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile_phone', 'like', "%{$search}%")
                  ->orWhere('member_id', 'like', "%{$search}%");
            });
        }

        // Filter by status aktif
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by exclusive member
        if ($request->filled('exclusive')) {
            if ($request->exclusive === 'yes') {
                $query->where('is_exclusive_member', true);
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
        
        // Map field names to database columns
        $sortMap = [
            'member_id' => 'member_id',
            'name' => 'nama_lengkap',
            'email' => 'email',
            'mobile_phone' => 'mobile_phone',
            'telepon' => 'mobile_phone',
            'status_aktif' => 'is_active',
            'tier' => 'member_level',
        ];
        
        // Handle special sorting cases
        if ($sort === 'point_balance' || $sort === 'spending_last_year') {
            // For calculated fields, we'll sort after calculation
            $query->orderBy('created_at', $direction);
        } else {
            $sortField = $sortMap[$sort] ?? $sort;
            $query->orderBy($sortField, $direction);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $members = $query->paginate($perPage)->withQueryString();

        // Optimize: Calculate spending setahun terakhir directly from orders
        $memberIds = $members->getCollection()->pluck('member_id')->filter()->toArray();
        $oneYearAgo = now()->subYear();
        
        // Get all spending directly from orders using member_id
        $spendingByMemberId = [];
        if (!empty($memberIds)) {
            $orders = DB::connection('db_justus')
                ->table('orders')
                ->whereIn('member_id', $memberIds)
                ->where('created_at', '>=', $oneYearAgo)
                ->where('status', 'paid') // Only paid orders
                ->select('member_id', 'grand_total')
                ->get();
            
            foreach ($orders as $order) {
                if (!isset($spendingByMemberId[$order->member_id])) {
                    $spendingByMemberId[$order->member_id] = 0;
                }
                $spendingByMemberId[$order->member_id] += $order->grand_total;
            }
        }
        
        // Calculate point balance and spending for each member
        $members->getCollection()->transform(function ($member) use ($spendingByMemberId) {
            // Use just_points from member_apps_members table
            $member->point_balance = $member->just_points ?? 0;
            $member->point_balance_formatted = number_format($member->point_balance, 0, ',', '.');
            
            // Calculate spending setahun terakhir directly from orders
            $spending = $spendingByMemberId[$member->member_id] ?? 0;
            
            $member->spending_last_year = $spending;
            $member->spending_last_year_formatted = 'Rp ' . number_format($spending, 0, ',', '.');
            
            // Get tier/member_level
            $member->tier = $member->member_level ?? 'silver';
            $member->tier_formatted = ucfirst($member->tier);
            
            // Map fields for backward compatibility with frontend
            $member->name = $member->nama_lengkap;
            $member->telepon = $member->mobile_phone;
            $member->costumers_id = $member->member_id;
            $member->status_aktif = $member->is_active ? '1' : '0';
            $member->exclusive_member = $member->is_exclusive_member ? 'Y' : 'N';
            $member->status_block = 'N'; // Not available in new table, default to 'N'
            
            return $member;
        });

        // Sort by calculated fields if requested
        if ($sort === 'point_balance') {
            $members->setCollection($members->getCollection()->sortBy(function ($member) use ($direction) {
                return $direction === 'desc' ? -$member->point_balance : $member->point_balance;
            })->values());
        } elseif ($sort === 'spending_last_year') {
            $members->setCollection($members->getCollection()->sortBy(function ($member) use ($direction) {
                return $direction === 'desc' ? -$member->spending_last_year : $member->spending_last_year;
            })->values());
        } elseif ($sort === 'tier') {
            $tierOrder = ['silver' => 1, 'gold' => 2, 'platinum' => 3];
            $members->setCollection($members->getCollection()->sortBy(function ($member) use ($direction, $tierOrder) {
                $tierValue = $tierOrder[strtolower($member->tier ?? 'silver')] ?? 1;
                return $direction === 'desc' ? -$tierValue : $tierValue;
            })->values());
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
            'total_members' => MemberAppsMember::count(),
            'active_members' => MemberAppsMember::where('is_active', true)->count(),
            'inactive_members' => MemberAppsMember::where('is_active', false)->count(),
            'exclusive_members' => MemberAppsMember::where('is_exclusive_member', true)->count(),
        ];

        // Calculate total point statistics from member_apps_members
        $pointStats = MemberAppsMember::selectRaw('
                COALESCE(SUM(just_points), 0) as total_point_balance,
                COUNT(CASE WHEN just_points > 0 THEN 1 END) as members_with_points
            ')
            ->first();

        $stats['total_point_balance'] = $pointStats->total_point_balance ?? 0;
        $stats['members_with_points'] = $pointStats->members_with_points ?? 0;
        
        // Statistics calculated from member_apps_members and orders
        // But for now, we'll use just_points as the balance
        $stats['total_point_earned'] = 0; // Can be calculated separately if needed
        $stats['total_point_redeemed'] = 0; // Can be calculated separately if needed

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
        $occupations = \App\Models\MemberAppsOccupation::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
        
        return Inertia::render('Members/Create', [
            'occupations' => $occupations
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Support both old and new field names for backward compatibility
        $memberId = $request->member_id ?? $request->costumers_id;
        $namaLengkap = $request->nama_lengkap ?? $request->name;
        $mobilePhone = $request->mobile_phone ?? $request->telepon;
        $exclusiveMember = $request->is_exclusive_member ?? ($request->exclusive_member === 'Y' ? true : false);
        $password = $request->password ?? $request->password2;
        
        $request->validate([
            'member_id' => 'nullable|string|max:50|unique:member_apps_members,member_id',
            'costumers_id' => 'nullable|string|max:50|unique:member_apps_members,member_id',
            'nama_lengkap' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:member_apps_members,email',
            'mobile_phone' => 'nullable|string|max:20|unique:member_apps_members,mobile_phone',
            'telepon' => 'nullable|string|max:20|unique:member_apps_members,mobile_phone',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P,1,2',
            'pekerjaan_id' => 'nullable|integer|exists:member_apps_occupations,id',
            'pin' => 'nullable|string|max:10',
            'password' => 'nullable|string|max:255',
            'password2' => 'nullable|string|max:255',
            'is_exclusive_member' => 'nullable|boolean',
            'exclusive_member' => 'nullable|in:Y,N',
        ]);

        try {
            DB::beginTransaction();

            // Map jenis_kelamin from old format (1,2) to new format (L,P)
            $jenisKelamin = $request->jenis_kelamin;
            if ($jenisKelamin === '1') {
                $jenisKelamin = 'L';
            } elseif ($jenisKelamin === '2') {
                $jenisKelamin = 'P';
            }

            // Map and prepare data
            $memberData = [
                'member_id' => $memberId,
                'nama_lengkap' => $namaLengkap,
                'email' => $request->email,
                'mobile_phone' => $mobilePhone,
                'tanggal_lahir' => $request->tanggal_lahir,
                'jenis_kelamin' => $jenisKelamin,
                'pekerjaan_id' => $request->pekerjaan_id,
                'pin' => $request->pin,
                'is_exclusive_member' => $exclusiveMember,
                'is_active' => true, // Otomatis aktif
                'just_points' => 0, // Default 0 points
                'point_remainder' => 0,
                'total_spending' => 0,
            ];

            // Hash password if provided
            if ($password) {
                $memberData['password'] = bcrypt($password);
            }

            $member = MemberAppsMember::create($memberData);

            DB::commit();

            return redirect()->route('members.index')
                ->with('success', 'Member berhasil ditambahkan!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal menambahkan member: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(MemberAppsMember $member)
    {
        // Load occupation relationship
        $member->load('occupation');
        
        // Map fields for backward compatibility (for old views that might still use old field names)
        $member->name = $member->nama_lengkap;
        $member->telepon = $member->mobile_phone;
        $member->costumers_id = $member->member_id;
        $member->status_aktif = $member->is_active ? '1' : '0';
        $member->point_balance = $member->just_points ?? 0;
        
        // Add formatted fields
        $member->jenis_kelamin_text = $member->jenis_kelamin === 'L' ? 'Laki-laki' : ($member->jenis_kelamin === 'P' ? 'Perempuan' : '-');
        $member->pekerjaan_name = $member->occupation ? $member->occupation->name : null;
        
        return Inertia::render('Members/Show', [
            'member' => $member
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MemberAppsMember $member)
    {
        // Map fields for backward compatibility
        $member->name = $member->nama_lengkap;
        $member->telepon = $member->mobile_phone;
        $member->costumers_id = $member->member_id;
        
        $occupations = \App\Models\MemberAppsOccupation::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
        
        return Inertia::render('Members/Edit', [
            'member' => $member,
            'occupations' => $occupations
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MemberAppsMember $member)
    {
        // Support both old and new field names for backward compatibility
        $memberId = $request->member_id ?? $request->costumers_id ?? $member->member_id;
        $namaLengkap = $request->nama_lengkap ?? $request->name ?? $member->nama_lengkap;
        $mobilePhone = $request->mobile_phone ?? $request->telepon ?? $member->mobile_phone;
        $exclusiveMember = $request->is_exclusive_member ?? ($request->exclusive_member === 'Y' ? true : ($request->exclusive_member === 'N' ? false : $member->is_exclusive_member));
        $password = $request->password ?? $request->password2;
        
        $request->validate([
            'member_id' => 'nullable|string|max:50|unique:member_apps_members,member_id,' . $member->id,
            'costumers_id' => 'nullable|string|max:50|unique:member_apps_members,member_id,' . $member->id,
            'nama_lengkap' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:member_apps_members,email,' . $member->id,
            'mobile_phone' => 'nullable|string|max:20|unique:member_apps_members,mobile_phone,' . $member->id,
            'telepon' => 'nullable|string|max:20|unique:member_apps_members,mobile_phone,' . $member->id,
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P,1,2',
            'pekerjaan_id' => 'nullable|integer|exists:member_apps_occupations,id',
            'pin' => 'nullable|string|max:10',
            'password' => 'nullable|string|max:255',
            'password2' => 'nullable|string|max:255',
            'is_exclusive_member' => 'nullable|boolean',
            'exclusive_member' => 'nullable|in:Y,N',
        ]);

        try {
            DB::beginTransaction();

            // Map jenis_kelamin from old format (1,2) to new format (L,P)
            $jenisKelamin = $request->jenis_kelamin ?? $member->jenis_kelamin;
            if ($jenisKelamin === '1') {
                $jenisKelamin = 'L';
            } elseif ($jenisKelamin === '2') {
                $jenisKelamin = 'P';
            }

            // Update data member
            $memberData = [
                'member_id' => $memberId,
                'nama_lengkap' => $namaLengkap,
                'email' => $request->email ?? $member->email,
                'mobile_phone' => $mobilePhone,
                'tanggal_lahir' => $request->tanggal_lahir ?? $member->tanggal_lahir,
                'jenis_kelamin' => $jenisKelamin,
                'pekerjaan_id' => $request->pekerjaan_id ?? $member->pekerjaan_id,
                'pin' => $request->pin ?? $member->pin,
                'is_exclusive_member' => $exclusiveMember,
            ];

            // Hash password if provided
            if ($password) {
                $memberData['password'] = bcrypt($password);
            }

            $member->update($memberData);

            DB::commit();

            return redirect()->route('members.index')
                ->with('success', 'Member berhasil diperbarui!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal memperbarui member: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MemberAppsMember $member)
    {
        try {
            DB::beginTransaction();

            $member->delete();

            DB::commit();

            return redirect()->route('members.index')
                ->with('success', 'Member berhasil dihapus!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal menghapus member: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle status aktif member
     */
    public function toggleStatus(MemberAppsMember $member)
    {
        try {
            DB::beginTransaction();

            $member->update([
                'is_active' => !$member->is_active
            ]);

            DB::commit();

            return back()->with('success', 'Status member berhasil diubah!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal mengubah status member: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle status block member
     * Note: status_block tidak ada di member_apps_members, jadi kita bisa skip atau set is_active = false
     */
    public function toggleBlock(MemberAppsMember $member)
    {
        try {
            DB::beginTransaction();

            // Since status_block doesn't exist in new table, we'll use is_active as alternative
            // Or you can add a new field if needed
            $member->update([
                'is_active' => !$member->is_active
            ]);

            DB::commit();

            return back()->with('success', 'Status block member berhasil diubah!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal mengubah status block member: ' . $e->getMessage()]);
        }
    }

    /**
     * Export members to Excel
     */
    public function export(Request $request)
    {
        $query = MemberAppsMember::query();

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile_phone', 'like', "%{$search}%")
                  ->orWhere('member_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('exclusive')) {
            if ($request->exclusive === 'yes') {
                $query->where('is_exclusive_member', true);
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
            $member = MemberAppsMember::findOrFail($id);
            
            // Use just_points as balance
            $balance = $member->just_points ?? 0;
            
            // Get point transactions from member_apps_point_transactions
            // Note: member_apps_point_transactions uses member_id (integer), not member_id (string)
            $pointTransactions = DB::table('member_apps_point_transactions')
                ->where('member_id', $member->id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
            
            // Calculate total earned and redeemed from point transactions
            $totalEarned = $pointTransactions->where('point_amount', '>', 0)->sum('point_amount');
            $totalRedeemed = abs($pointTransactions->where('point_amount', '<', 0)->sum('point_amount'));
            
            // Transform point transactions to display format
            $transactions = $pointTransactions->map(function ($pt) {
                $orderDetails = collect([]);
                
                // Get order details if transaction is related to order
                // Check metadata for order_id or try to match by paid_number/reference_number
                $orderId = null;
                $paidNumber = $pt->reference_number ?? $pt->serial_code ?? null;
                
                if ($pt->metadata) {
                    $metadata = json_decode($pt->metadata ?? '{}', true);
                    $orderId = $metadata['order_id'] ?? null;
                }
                
                // If no order_id in metadata, try to find by paid_number
                if (!$orderId && $paidNumber) {
                    // Get member_id (string) from member_apps_members using member.id
                    $memberData = DB::table('member_apps_members')
                        ->where('id', $pt->member_id)
                        ->select('member_id')
                        ->first();
                    
                    if ($memberData && $memberData->member_id) {
                        $order = DB::connection('db_justus')
                            ->table('orders')
                            ->where('paid_number', $paidNumber)
                            ->where('member_id', $memberData->member_id)
                            ->first();
                        
                        if ($order) {
                            $orderId = $order->id;
                        }
                    }
                }
                
                // Get order items if order_id found
                if ($orderId) {
                    $orderItems = DB::connection('db_justus')
                        ->table('order_items')
                        ->select([
                            'item_name',
                            'qty',
                            'price',
                            'modifiers',
                            'notes'
                        ])
                        ->where('order_id', $orderId)
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
                    
                    $orderDetails = $orderItems;
                }
                
                // Get outlet info
                $outletName = 'Outlet Tidak Diketahui';
                $outletAlamat = '';
                if ($pt->outlet_id) {
                    $outlet = DB::table('tbl_data_outlet')
                        ->where('id_outlet', $pt->outlet_id)
                        ->first();
                    
                    if ($outlet) {
                        $outletName = $outlet->nama_outlet ?? 'Outlet Tidak Diketahui';
                        $outletAlamat = $outlet->alamat_outlet ?? '';
                    }
                }
                
                // Determine transaction type
                $isEarned = $pt->point_amount > 0;
                $type = $isEarned ? '1' : '2';
                $typeText = $isEarned ? 'EARNED' : 'REDEEMED';
                
                // Get description based on transaction type
                $description = $isEarned ? 'Top Up Point dari Transaksi' : 'Redeem Point untuk Transaksi';
                if ($pt->transaction_type === 'manual') {
                    $description = 'Point Manual';
                } elseif ($pt->transaction_type === 'reward_redemption') {
                    $description = 'Redeem Reward';
                } elseif ($pt->transaction_type === 'voucher_purchase') {
                    $description = 'Beli Voucher';
                } elseif ($pt->transaction_type === 'order') {
                    $description = 'Top Up Point dari Transaksi';
                }
                
                return [
                    'id' => $pt->id,
                    'point' => abs($pt->point_amount),
                    'jml_trans' => $pt->transaction_amount ?? 0,
                    'jml_trans_formatted' => $pt->transaction_amount ? 'Rp ' . number_format($pt->transaction_amount, 0, ',', '.') : '-',
                    'type' => $type,
                    'type_text' => $typeText,
                    'no_bill' => $paidNumber,
                    'outlet_name' => $outletName,
                    'outlet_alamat' => $outletAlamat,
                    'created_at' => $pt->created_at,
                    'description' => $description,
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
            $member = MemberAppsMember::findOrFail($id);
            
            // Get member preferences directly from orders using member_id
            // No need to use mysql_second anymore

            // Get preferences from db_justus database directly using member_id
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
                ->where('o.member_id', $member->member_id)
                ->where('o.status', 'paid')
                ->whereNotNull('oi.item_name')
                ->groupBy('oi.item_name', 'oi.price', 'c.name')
                ->orderBy('order_count', 'desc')
                ->orderBy('total_spent', 'desc')
                ->limit(10) // Top 10 favorite items
                ->get();

            // Get modifier details for each preference
            $preferences = $preferences->map(function ($pref) use ($member) {
                // Get all order items with modifiers for this specific menu
                $orderItems = DB::connection('db_justus')
                    ->table('orders as o')
                    ->select([
                        'oi.modifiers',
                        'oi.notes',
                        'o.created_at'
                    ])
                    ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
                    ->where('o.member_id', $member->member_id)
                    ->where('o.status', 'paid')
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

            // Get summary statistics from all orders (not just top 10 preferences)
            $allOrdersSummary = DB::connection('db_justus')
                ->table('orders as o')
                ->select([
                    DB::raw('COUNT(DISTINCT o.id) as total_orders'),
                    DB::raw('SUM(oi.qty) as total_items'),
                    DB::raw('SUM(oi.qty * oi.price) as total_spent')
                ])
                ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
                ->where('o.member_id', $member->member_id)
                ->where('o.status', 'paid')
                ->whereNotNull('oi.item_name')
                ->first();

            // Get favorite category from all orders
            $favoriteCategory = DB::connection('db_justus')
                ->table('orders as o')
                ->select([
                    'c.name as menu_category',
                    DB::raw('COUNT(*) as order_count')
                ])
                ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
                ->leftJoin('items as i', 'oi.item_id', '=', 'i.id')
                ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
                ->where('o.member_id', $member->member_id)
                ->where('o.status', 'paid')
                ->whereNotNull('oi.item_name')
                ->whereNotNull('c.name')
                ->groupBy('c.name')
                ->orderBy('order_count', 'desc')
                ->first();

            // Get summary statistics
            $summary = [
                'total_orders' => $allOrdersSummary->total_orders ?? 0,
                'total_items' => $allOrdersSummary->total_items ?? 0,
                'total_spent' => $allOrdersSummary->total_spent ?? 0,
                'total_spent_formatted' => 'Rp ' . number_format($allOrdersSummary->total_spent ?? 0, 0, ',', '.'),
                'favorite_category' => $favoriteCategory->menu_category ?? 'Tidak ada data'
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
     * Get member voucher timeline (owned, purchased, redeemed)
     */
    public function getVoucherTimeline($id)
    {
        try {
            $member = MemberAppsMember::findOrFail($id);
            
            $timeline = [];
            
            // Get all member vouchers (owned vouchers)
            $memberVouchers = DB::table('member_apps_member_vouchers as mav')
                ->join('member_apps_vouchers as av', 'mav.voucher_id', '=', 'av.id')
                ->leftJoin('tbl_data_outlet as o', 'mav.used_in_outlet_id', '=', 'o.id_outlet')
                ->where('mav.member_id', $member->member_id)
                ->select([
                    'mav.*',
                    'av.name as voucher_name',
                    'av.voucher_type',
                    'av.discount_percentage',
                    'av.discount_amount',
                    'av.min_purchase',
                    'av.max_discount',
                    'av.description',
                    'av.image',
                    'o.nama_outlet as used_outlet_name'
                ])
                ->orderBy('mav.created_at', 'desc')
                ->get();
            
            foreach ($memberVouchers as $mv) {
                // Event: Voucher didapat (owned)
                $timeline[] = [
                    'type' => 'owned',
                    'title' => 'Mendapat Voucher',
                    'voucher_name' => $mv->voucher_name,
                    'voucher_type' => $mv->voucher_type,
                    'status' => $mv->status,
                    'serial_code' => $mv->serial_code,
                    'voucher_code' => $mv->voucher_code,
                    'description' => $mv->description,
                    'image' => $mv->image,
                    'discount_info' => $this->getDiscountInfo($mv),
                    'date' => $mv->created_at,
                    'date_formatted' => \Carbon\Carbon::parse($mv->created_at)->format('d F Y, H:i'),
                ];
                
                // Event: Voucher digunakan (redeemed)
                if ($mv->used_at) {
                    $timeline[] = [
                        'type' => 'redeemed',
                        'title' => 'Menggunakan Voucher',
                        'voucher_name' => $mv->voucher_name,
                        'voucher_type' => $mv->voucher_type,
                        'status' => 'used',
                        'serial_code' => $mv->serial_code,
                        'voucher_code' => $mv->voucher_code,
                        'description' => $mv->description,
                        'image' => $mv->image,
                        'discount_info' => $this->getDiscountInfo($mv),
                        'used_outlet' => $mv->used_outlet_name,
                        'used_transaction_id' => $mv->used_in_transaction_id,
                        'date' => $mv->used_at,
                        'date_formatted' => \Carbon\Carbon::parse($mv->used_at)->format('d F Y, H:i'),
                    ];
                }
            }
            
            // Get purchased vouchers from point transactions
            // Note: member_apps_point_transactions uses member_id (integer), not member_id (string)
            $purchasedTransactions = DB::table('member_apps_point_transactions')
                ->where('member_id', $member->id)
                ->where('transaction_type', 'voucher_purchase')
                ->where('point_amount', '<', 0) // Negative means point spent
                ->orderBy('created_at', 'desc')
                ->get();
            
            $purchasedVouchers = collect();
            foreach ($purchasedTransactions as $pt) {
                $metadata = json_decode($pt->metadata ?? '{}', true);
                $voucherId = $metadata['voucher_id'] ?? null;
                
                if ($voucherId) {
                    $voucher = DB::table('member_apps_vouchers')
                        ->where('id', $voucherId)
                        ->first();
                    
                    if ($voucher) {
                        $pv = (object) [
                            'voucher_name' => $voucher->name,
                            'voucher_type' => $voucher->voucher_type,
                            'discount_percentage' => $voucher->discount_percentage,
                            'discount_amount' => $voucher->discount_amount,
                            'description' => $voucher->description,
                            'image' => $voucher->image,
                            'points_spent' => abs($pt->point_amount),
                            'created_at' => $pt->created_at,
                        ];
                        $purchasedVouchers->push($pv);
                    }
                }
            }
            
            foreach ($purchasedVouchers as $pv) {
                $timeline[] = [
                    'type' => 'purchased',
                    'title' => 'Membeli Voucher',
                    'voucher_name' => $pv->voucher_name,
                    'voucher_type' => $pv->voucher_type,
                    'status' => 'purchased',
                    'description' => $pv->description,
                    'image' => $pv->image,
                    'points_spent' => $pv->points_spent,
                    'points_spent_formatted' => number_format($pv->points_spent, 0, ',', '.'),
                    'date' => $pv->created_at,
                    'date_formatted' => \Carbon\Carbon::parse($pv->created_at)->format('d F Y, H:i'),
                ];
            }
            
            // Get challenge progress timeline
            // Note: member_apps_challenge_progress uses member_id (integer), not member_id (string)
            $challengeProgresses = DB::table('member_apps_challenge_progress as cp')
                ->join('member_apps_challenges as c', 'cp.challenge_id', '=', 'c.id')
                ->leftJoin('tbl_data_outlet as o', 'cp.redeemed_outlet_id', '=', 'o.id_outlet')
                ->where('cp.member_id', $member->id)
                ->select([
                    'cp.*',
                    'c.title as challenge_name',
                    'c.description as challenge_description',
                    'c.challenge_type_id',
                    'c.points_reward',
                    'o.nama_outlet as redeemed_outlet_name'
                ])
                ->orderBy('cp.started_at', 'desc')
                ->get();
            
            foreach ($challengeProgresses as $cp) {
                // Event: Start challenge
                if ($cp->started_at) {
                    $timeline[] = [
                        'type' => 'challenge_start',
                        'title' => 'Memulai Challenge',
                        'challenge_name' => $cp->challenge_name,
                        'challenge_description' => $cp->challenge_description,
                        'challenge_type' => $cp->challenge_type_id,
                        'status' => 'started',
                        'date' => $cp->started_at,
                        'date_formatted' => \Carbon\Carbon::parse($cp->started_at)->format('d F Y, H:i'),
                    ];
                }
                
                // Event: Complete challenge
                if ($cp->completed_at) {
                    $rewardInfo = $this->getChallengeRewardInfo($cp);
                    $timeline[] = [
                        'type' => 'challenge_complete',
                        'title' => 'Menyelesaikan Challenge',
                        'challenge_name' => $cp->challenge_name,
                        'challenge_description' => $cp->challenge_description,
                        'challenge_type' => $cp->challenge_type_id,
                        'status' => 'completed',
                        'reward_info' => $rewardInfo,
                        'date' => $cp->completed_at,
                        'date_formatted' => \Carbon\Carbon::parse($cp->completed_at)->format('d F Y, H:i'),
                    ];
                }
                
                // Event: Claim reward
                if ($cp->reward_claimed_at) {
                    $rewardInfo = $this->getChallengeRewardInfo($cp);
                    $timeline[] = [
                        'type' => 'challenge_claim',
                        'title' => 'Claim Reward Challenge',
                        'challenge_name' => $cp->challenge_name,
                        'challenge_description' => $cp->challenge_description,
                        'status' => 'claimed',
                        'reward_info' => $rewardInfo,
                        'serial_code' => $cp->serial_code,
                        'date' => $cp->reward_claimed_at,
                        'date_formatted' => \Carbon\Carbon::parse($cp->reward_claimed_at)->format('d F Y, H:i'),
                    ];
                }
                
                // Event: Redeem reward
                if ($cp->reward_redeemed_at) {
                    $rewardInfo = $this->getChallengeRewardInfo($cp);
                    $timeline[] = [
                        'type' => 'challenge_redeem',
                        'title' => 'Redeem Reward Challenge',
                        'challenge_name' => $cp->challenge_name,
                        'challenge_description' => $cp->challenge_description,
                        'status' => 'redeemed',
                        'reward_info' => $rewardInfo,
                        'serial_code' => $cp->serial_code,
                        'redeemed_outlet' => $cp->redeemed_outlet_name,
                        'date' => $cp->reward_redeemed_at,
                        'date_formatted' => \Carbon\Carbon::parse($cp->reward_redeemed_at)->format('d F Y, H:i'),
                    ];
                }
            }
            
            // Get reward redemptions (point rewards)
            $rewardTransactions = DB::table('member_apps_point_transactions')
                ->where('member_id', $member->id)
                ->where('transaction_type', 'reward_redemption')
                ->where('point_amount', '<', 0) // Negative means point spent
                ->orderBy('created_at', 'desc')
                ->get();
            
            $rewardRedemptions = collect();
            foreach ($rewardTransactions as $rt) {
                $metadata = json_decode($rt->metadata ?? '{}', true);
                $rewardId = $metadata['reward_id'] ?? null;
                $outletId = $metadata['outlet_id'] ?? null;
                
                if ($rewardId) {
                    $reward = DB::table('member_apps_rewards')
                        ->where('id', $rewardId)
                        ->first();
                    
                    if ($reward && $reward->item_id) {
                        $item = DB::table('items')
                            ->where('id', $reward->item_id)
                            ->first();
                        
                        $outletName = null;
                        if ($outletId) {
                            $outlet = DB::table('tbl_data_outlet')
                                ->where('id_outlet', $outletId)
                                ->first();
                            if ($outlet) {
                                $outletName = $outlet->nama_outlet;
                            }
                        }
                        
                        if ($item) {
                            $rr = (object) [
                                'item_name' => $item->name,
                                'item_description' => $item->description,
                                'points_spent' => abs($rt->point_amount),
                                'points_required' => $reward->points_required,
                                'redeemed_outlet_name' => $outletName,
                                'serial_code' => $rt->serial_code ?? null,
                                'created_at' => $rt->created_at,
                            ];
                            $rewardRedemptions->push($rr);
                        }
                    }
                }
            }
            
            foreach ($rewardRedemptions as $rr) {
                $timeline[] = [
                    'type' => 'reward_redeem',
                    'title' => 'Redeem Reward',
                    'reward_name' => $rr->item_name,
                    'reward_description' => $rr->item_description,
                    'points_spent' => $rr->points_spent,
                    'points_spent_formatted' => number_format($rr->points_spent, 0, ',', '.'),
                    'points_required' => $rr->points_required,
                    'redeemed_outlet' => $rr->redeemed_outlet_name,
                    'serial_code' => $rr->serial_code,
                    'date' => $rr->created_at,
                    'date_formatted' => \Carbon\Carbon::parse($rr->created_at)->format('d F Y, H:i'),
                ];
            }
            
            // Get tier change history
            $tierHistories = DB::table('member_apps_tier_history')
                ->where('member_id', $member->id)
                ->orderBy('changed_at', 'desc')
                ->get();
            
            foreach ($tierHistories as $th) {
                $isUpgrade = $this->isTierUpgrade($th->old_tier, $th->new_tier);
                $timeline[] = [
                    'type' => $isUpgrade ? 'tier_upgrade' : 'tier_downgrade',
                    'title' => $isUpgrade ? 'Naik Tier' : 'Turun Tier',
                    'old_tier' => ucfirst($th->old_tier ?? 'Silver'),
                    'new_tier' => ucfirst($th->new_tier ?? 'Silver'),
                    'tier_change' => ucfirst($th->old_tier ?? 'Silver') . ' → ' . ucfirst($th->new_tier ?? 'Silver'),
                    'total_spending' => $th->total_spending ?? 0,
                    'total_spending_formatted' => 'Rp ' . number_format($th->total_spending ?? 0, 0, ',', '.'),
                    'spending_period_start' => $th->spending_period_start,
                    'spending_period_end' => $th->spending_period_end,
                    'change_reason' => $th->change_reason,
                    'date' => $th->changed_at,
                    'date_formatted' => \Carbon\Carbon::parse($th->changed_at)->format('d F Y, H:i'),
                ];
            }
            
            // Sort timeline by date (newest first)
            usort($timeline, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
            
            return response()->json([
                'status' => 'success',
                'member' => $member,
                'timeline' => $timeline,
                'summary' => [
                    'total_owned' => $memberVouchers->count(),
                    'total_active' => $memberVouchers->where('status', 'active')->count(),
                    'total_used' => $memberVouchers->where('status', 'used')->count(),
                    'total_purchased' => $purchasedVouchers->count(),
                    'total_challenges_started' => $challengeProgresses->whereNotNull('started_at')->count(),
                    'total_challenges_completed' => $challengeProgresses->where('is_completed', true)->count(),
                    'total_rewards_redeemed' => $rewardRedemptions->count(),
                    'total_tier_changes' => isset($tierHistories) ? $tierHistories->count() : 0,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data voucher timeline: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check if tier change is upgrade or downgrade
     */
    private function isTierUpgrade($oldTier, $newTier)
    {
        $tierOrder = ['silver' => 1, 'gold' => 2, 'platinum' => 3];
        $oldOrder = $tierOrder[strtolower($oldTier ?? 'silver')] ?? 1;
        $newOrder = $tierOrder[strtolower($newTier ?? 'silver')] ?? 1;
        
        return $newOrder > $oldOrder;
    }
    
    /**
     * Get challenge reward info
     */
    private function getChallengeRewardInfo($challengeProgress)
    {
        $info = [];
        
        // Check if challenge has points_reward
        if (isset($challengeProgress->points_reward) && $challengeProgress->points_reward > 0) {
            $info['type'] = 'Point';
            $info['value'] = number_format($challengeProgress->points_reward, 0, ',', '.') . ' point';
        } else {
            // Check challenge rewards table if exists
            $challengeRewards = DB::table('member_apps_challenge_rewards')
                ->where('challenge_id', $challengeProgress->challenge_id)
                ->get();
            
            if ($challengeRewards->isNotEmpty()) {
                $rewardTypes = [];
                foreach ($challengeRewards as $cr) {
                    $rewardType = $cr->reward_type ?? 'points';
                    switch ($rewardType) {
                        case 'points':
                            $rewardTypes[] = number_format($cr->reward_value ?? 0, 0, ',', '.') . ' point';
                            break;
                        case 'voucher':
                            if (isset($cr->reward_voucher_id) && $cr->reward_voucher_id) {
                                $voucher = DB::table('member_apps_vouchers')
                                    ->where('id', $cr->reward_voucher_id)
                                    ->first();
                                if ($voucher) {
                                    $rewardTypes[] = $voucher->name ?? 'Voucher';
                                }
                            }
                            break;
                        case 'item':
                            if (isset($cr->reward_item_id) && $cr->reward_item_id) {
                                $item = DB::table('items')
                                    ->where('id', $cr->reward_item_id)
                                    ->first();
                                if ($item) {
                                    $rewardTypes[] = $item->name ?? 'Item';
                                }
                            }
                            break;
                    }
                }
                
                if (!empty($rewardTypes)) {
                    $info['type'] = 'Reward';
                    $info['value'] = implode(', ', $rewardTypes);
                }
            }
        }
        
        // If no reward info found, set default
        if (empty($info)) {
            $info['type'] = 'Reward';
            $info['value'] = 'Reward Challenge';
        }
        
        return $info;
    }
    
    /**
     * Get discount info for voucher
     */
    private function getDiscountInfo($voucher)
    {
        $info = [];
        
        switch ($voucher->voucher_type) {
            case 'discount-percentage':
                $info['type'] = 'Diskon';
                $info['value'] = $voucher->discount_percentage . '%';
                if ($voucher->max_discount) {
                    $info['max'] = 'Maks. Rp ' . number_format($voucher->max_discount, 0, ',', '.');
                }
                break;
            case 'discount-amount':
                $info['type'] = 'Diskon';
                $info['value'] = 'Rp ' . number_format($voucher->discount_amount, 0, ',', '.');
                break;
            case 'cashback':
                $info['type'] = 'Cashback';
                $info['value'] = 'Rp ' . number_format($voucher->discount_amount, 0, ',', '.');
                break;
            case 'free-item':
                $info['type'] = 'Gratis Item';
                $info['value'] = 'Item Gratis';
                break;
        }
        
        if ($voucher->min_purchase) {
            $info['min_purchase'] = 'Min. belanja Rp ' . number_format($voucher->min_purchase, 0, ',', '.');
        }
        
        return $info;
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