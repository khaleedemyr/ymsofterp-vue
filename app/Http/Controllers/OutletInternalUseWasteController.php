<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\NotificationService;
use Maatwebsite\Excel\Facades\Excel;

class OutletInternalUseWasteController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = DB::table('outlet_internal_use_waste_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->select(
                'h.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name'
            );
        
        // Filter by outlet (if user is not admin)
        if ($user->id_outlet != 1) {
            $query->where('h.outlet_id', $user->id_outlet);
        }
        
        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('h.number', 'like', "%{$search}%")
                  ->orWhere('o.nama_outlet', 'like', "%{$search}%")
                  ->orWhere('wo.name', 'like', "%{$search}%")
                  ->orWhere('u.nama_lengkap', 'like', "%{$search}%");
            });
        }
        
        // Filter by outlet (only if user is admin)
        if ($user->id_outlet == 1 && $request->filled('outlet_id')) {
            $query->where('h.outlet_id', $request->outlet_id);
        }
        
        // Filter by type
        if ($request->filled('type')) {
            $query->where('h.type', $request->type);
        }
        
        // Filter by date from
        if ($request->filled('date_from')) {
            $query->whereDate('h.date', '>=', $request->date_from);
        }
        
        // Filter by date to
        if ($request->filled('date_to')) {
            $query->whereDate('h.date', '<=', $request->date_to);
        }
        
        // Per page
        $perPage = $request->input('per_page', 10);
        
        // Order and paginate
        $data = $query->orderByDesc('h.date')
            ->orderByDesc('h.id')
            ->paginate($perPage)
            ->withQueryString();
        
        // Get approval flows for each header
        $headerIds = collect($data->items())->pluck('id')->toArray();
        $approvalFlows = [];
        if (!empty($headerIds)) {
            $approvalFlowsData = DB::table('outlet_internal_use_waste_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->whereIn('af.header_id', $headerIds)
                ->select(
                    'af.header_id',
                    'af.approval_level',
                    'af.status',
                    'af.approved_at',
                    'af.rejected_at',
                    'af.comments',
                    'u.nama_lengkap as approver_name',
                    'j.nama_jabatan as approver_jabatan'
                )
                ->orderBy('af.header_id')
                ->orderBy('af.approval_level')
                ->get();
            
            // Group by header_id
            foreach ($approvalFlowsData as $flow) {
                $headerId = $flow->header_id;
                if (!isset($approvalFlows[$headerId])) {
                    $approvalFlows[$headerId] = [];
                }
                $approvalFlows[$headerId][] = $flow;
            }
        }
        
        // Attach approval flows to each data item
        $data->getCollection()->transform(function ($item) use ($approvalFlows) {
            $item->approval_flows = $approvalFlows[$item->id] ?? [];
            return $item;
        });
        
        // Get outlets for filter dropdown (only if user is admin)
        $outlets = [];
        if ($user->id_outlet == 1) {
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet as id', 'nama_outlet as name')
                ->orderBy('nama_outlet')
                ->get();
        }
        
        // Return JSON if API request
        if ($request->wantsJson() || $request->header('Accept') == 'application/json') {
            return response()->json([
                'data' => $data,
                'outlets' => $outlets,
                'filters' => $request->only(['search', 'outlet_id', 'type', 'date_from', 'date_to', 'per_page'])
            ]);
        }
        return inertia('OutletInternalUseWaste/Index', [
            'data' => $data,
            'outlets' => $outlets,
            'filters' => $request->only(['search', 'outlet_id', 'type', 'date_from', 'date_to', 'per_page'])
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();
        $items = DB::table('items')->where('status', 'active')->get();
        $units = DB::table('units')->get();
        $rukos = DB::table('tbl_data_ruko')->get();
        if ($user->id_outlet == 1) {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        } else {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('outlet_id', $user->id_outlet)
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        }
        return inertia('OutletInternalUseWaste/Create', [
            'outlets' => $outlets,
            'items' => $items,
            'units' => $units,
            'rukos' => $rukos,
            'warehouse_outlets' => $warehouse_outlets,
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('OutletInternalUseWaste store method called with data:', $request->all());
        try {
            // For autosave, allow empty items array (user might be filling form)
            // For manual save, require at least 1 item
            $itemsRequired = $request->input('autosave') ? 'nullable' : 'required';
            $itemsMin = $request->input('autosave') ? 'min:0' : 'min:1';
            
            $request->validate([
                'type' => 'required|in:internal_use,spoil,waste,r_and_d,marketing,non_commodity,guest_supplies,wrong_maker,training',
                'date' => 'required|date',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'warehouse_outlet_id' => 'required|exists:warehouse_outlets,id',
                'notes' => 'nullable|string',
                'items' => $itemsRequired . '|array|' . $itemsMin,
                'items.*.item_id' => 'required_with:items|exists:items,id',
                'items.*.qty' => 'required_with:items|numeric|min:0',
                'items.*.unit_id' => 'required_with:items|exists:units,id',
                'items.*.note' => 'nullable|string'
            ]);

            // Get user ID with multiple fallback methods
            $userId = Auth::id() ?? auth()->id();
            if (!$userId) {
                $user = auth()->user();
                $userId = $user ? $user->id : null;
            }
            
            \Log::info('OutletInternalUseWaste store - User info:', [
                'auth_id' => Auth::id(),
                'auth_user_id' => auth()->id(),
                'user_id' => $userId,
                'user_exists' => $userId ? 'yes' : 'no',
                'request_user' => $request->user() ? $request->user()->id : null
            ]);
            
            if (!$userId) {
                \Log::error('OutletInternalUseWaste store - No user ID found!', [
                    'all_request' => $request->all(),
                    'session' => session()->all()
                ]);
                throw new \Exception('User tidak terautentikasi. Silakan login ulang.');
            }
            
            DB::beginTransaction();
            
            // Always save as DRAFT first - no stock processing, no approval sending
            $status = 'DRAFT';
            
            // Cek apakah sudah ada draft untuk outlet, warehouse, type, dan user yang sama
            // Mirip dengan Food Floor Order: cari draft berdasarkan kombinasi outlet_id, warehouse_outlet_id, type, dan user
            // Gunakan lockForUpdate untuk mencegah race condition saat multiple requests datang bersamaan
            $existingHeader = DB::table('outlet_internal_use_waste_headers')
                ->where('outlet_id', $request->outlet_id)
                ->where('warehouse_outlet_id', $request->warehouse_outlet_id)
                ->where('type', $request->type)
                ->where('created_by', $userId)
                ->where('status', 'DRAFT')
                ->lockForUpdate()
                ->first();
            
            \Log::info('OutletInternalUseWaste store - Checking for existing draft', [
                'outlet_id' => $request->outlet_id,
                'warehouse_outlet_id' => $request->warehouse_outlet_id,
                'type' => $request->type,
                'user_id' => $userId,
                'existing_header_id' => $existingHeader->id ?? null
            ]);
            
            if ($existingHeader) {
                // Update existing draft
                $headerId = $existingHeader->id;
                
                \Log::info('OutletInternalUseWaste store - Updating existing draft', [
                    'header_id' => $headerId
                ]);
                
                // Update header
                DB::table('outlet_internal_use_waste_headers')
                    ->where('id', $headerId)
                    ->update([
                        'date' => $request->date,
                        'notes' => $request->notes,
                        'status' => $status,
                        'updated_at' => now()
                    ]);
                
                // Delete existing details
                DB::table('outlet_internal_use_waste_details')->where('header_id', $headerId)->delete();
                
                // Delete existing approval flows (if any - shouldn't exist for draft, but clean up just in case)
                DB::table('outlet_internal_use_waste_approval_flows')->where('header_id', $headerId)->delete();
            } else {
                // Double check again after lock to prevent race condition
                $doubleCheck = DB::table('outlet_internal_use_waste_headers')
                    ->where('outlet_id', $request->outlet_id)
                    ->where('warehouse_outlet_id', $request->warehouse_outlet_id)
                    ->where('type', $request->type)
                    ->where('created_by', $userId)
                    ->where('status', 'DRAFT')
                    ->first();
                
                if ($doubleCheck) {
                    // Another request already created it, use that one
                    $headerId = $doubleCheck->id;
                    \Log::info('OutletInternalUseWaste store - Found draft created by another request', [
                        'header_id' => $headerId
                    ]);
                    
                    // Update it instead (jangan update nomor jika sudah bukan DRAFT)
                    $updateData = [
                        'date' => $request->date,
                        'notes' => $request->notes,
                        'status' => $status,
                        'updated_at' => now()
                    ];
                    
                    // Jika nomor masih DRAFT, pastikan tetap DRAFT
                    if (strpos($doubleCheck->number ?? '', 'DRAFT-') === 0) {
                        // Nomor tetap DRAFT, tidak perlu update
                    }
                    
                    DB::table('outlet_internal_use_waste_headers')
                        ->where('id', $headerId)
                        ->update($updateData);
                    
                    // Delete existing details
                    DB::table('outlet_internal_use_waste_details')->where('header_id', $headerId)->delete();
                    DB::table('outlet_internal_use_waste_approval_flows')->where('header_id', $headerId)->delete();
                } else {
                    // Create new draft dengan nomor DRAFT
                    $draftNumber = 'DRAFT-' . $userId . '-' . time();
                    
                    $headerId = DB::table('outlet_internal_use_waste_headers')->insertGetId([
                        'type' => $request->type,
                        'date' => $request->date,
                        'outlet_id' => $request->outlet_id,
                        'warehouse_outlet_id' => $request->warehouse_outlet_id,
                        'notes' => $request->notes,
                        'status' => $status,
                        'number' => $draftNumber, // Set nomor DRAFT saat create
                        'created_by' => $userId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    \Log::info('OutletInternalUseWaste store - Created new draft', [
                        'header_id' => $headerId,
                        'draft_number' => $draftNumber
                    ]);
                }
            }
            
            \Log::info('OutletInternalUseWaste store - Header created:', [
                'header_id' => $headerId,
                'created_by' => $userId
            ]);

            // Process items only if provided (for autosave, items might be empty)
            $itemsToProcess = $request->items ?? [];
            
            // For autosave, allow empty items array
            // For manual save, require at least 1 item (already validated above)
            if (empty($itemsToProcess) && !$request->input('autosave')) {
                throw new \Exception('Items tidak boleh kosong');
            }
            
            foreach ($itemsToProcess as $itemIndex => $item) {
                try {
                    // Validasi item data
                    if (empty($item['item_id'])) {
                        throw new \Exception("Item ID tidak boleh kosong untuk item ke-" . ($itemIndex + 1));
                    }
                    if (empty($item['qty']) || $item['qty'] <= 0) {
                        throw new \Exception("Quantity harus lebih dari 0 untuk item ke-" . ($itemIndex + 1));
                    }
                    if (empty($item['unit_id'])) {
                        throw new \Exception("Unit ID tidak boleh kosong untuk item ke-" . ($itemIndex + 1));
                    }
                    
                    // Cek inventory item (table outlet_food_inventory_items tidak punya warehouse_outlet_id)
                    $inventoryItem = DB::table('outlet_food_inventory_items')
                        ->where('item_id', $item['item_id'])
                        ->first();
                    if (!$inventoryItem) {
                        $itemName = DB::table('items')->where('id', $item['item_id'])->value('name') ?? 'Unknown';
                        throw new \Exception("Item '{$itemName}' tidak ditemukan di inventory untuk item ke-" . ($itemIndex + 1));
                    }
                    
                    // Cek item master
                    $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
                    if (!$itemMaster) {
                        throw new \Exception("Item master tidak ditemukan untuk item ke-" . ($itemIndex + 1));
                    }
                    
                    // Cek unit
                    $unit = DB::table('units')->where('id', $item['unit_id'])->value('name');
                    if (!$unit) {
                        throw new \Exception("Unit tidak ditemukan untuk item ke-" . ($itemIndex + 1));
                    }
                    
                    $qty_input = floatval($item['qty']);
                    $qty_small = 0;
                    $qty_medium = 0;
                    $qty_large = 0;
                    $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
                    $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
                    $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
                    $smallConv = floatval($itemMaster->small_conversion_qty ?: 1);
                    $mediumConv = floatval($itemMaster->medium_conversion_qty ?: 1);
                    
                    if ($unit === $unitSmall) {
                        $qty_small = $qty_input;
                        $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                        $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                    } elseif ($unit === $unitMedium) {
                        $qty_medium = $qty_input;
                        $qty_small = $qty_medium * $smallConv;
                        $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                    } elseif ($unit === $unitLarge) {
                        $qty_large = $qty_input;
                        $qty_medium = $qty_large * $mediumConv;
                        $qty_small = $qty_medium * $smallConv;
                    } else {
                        $qty_small = $qty_input;
                    }
                    
                    // Cek stock
                    $stock = DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('id_outlet', $request->outlet_id)
                        ->where('warehouse_outlet_id', $request->warehouse_outlet_id)
                        ->first();
                    if (!$stock) {
                        $itemName = $itemMaster->name ?? 'Unknown';
                        throw new \Exception("Stok item '{$itemName}' tidak ditemukan di outlet dan warehouse outlet yang dipilih untuk item ke-" . ($itemIndex + 1));
                    }
                    
                    // Validasi stock tidak boleh 0 untuk draft
                    if ($stock->qty_small <= 0) {
                        $itemName = $itemMaster->name ?? 'Unknown';
                        throw new \Exception("Stok item '{$itemName}' tidak tersedia (stock: 0). Tidak dapat menyimpan draft untuk item ke-" . ($itemIndex + 1));
                    }
                    
                    // Validasi stock cukup (hanya untuk informasi, tidak dipotong saat draft)
                    // Stock validation will be done again on submit
                    
                    // Insert detail
                    $detailInserted = DB::table('outlet_internal_use_waste_details')->insert([
                        'header_id' => $headerId,
                        'item_id' => $item['item_id'],
                        'qty' => $item['qty'],
                        'unit_id' => $item['unit_id'],
                        'note' => $item['note'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    if (!$detailInserted) {
                        throw new \Exception("Gagal menyimpan detail item ke-" . ($itemIndex + 1));
                    }
                    
                    // NO STOCK PROCESSING FOR DRAFT - will be done on submit
                } catch (\Exception $itemError) {
                    // Re-throw dengan informasi item index
                    throw new \Exception("Error pada item ke-" . ($itemIndex + 1) . ": " . $itemError->getMessage());
                }
            }
            
            // NO APPROVAL FLOW CREATION FOR DRAFT - will be done on submit
            // Store approvers in a temporary field or separate table if needed
            // For now, we'll store approvers in a JSON field in notes or create them on submit
            
            // Verifikasi data sebelum commit
            $headerExists = DB::table('outlet_internal_use_waste_headers')->where('id', $headerId)->exists();
            if (!$headerExists) {
                DB::rollBack();
                throw new \Exception("Header tidak ditemukan setelah insert. Kemungkinan terjadi error saat insert.");
            }
            
            $detailsCount = DB::table('outlet_internal_use_waste_details')->where('header_id', $headerId)->count();
            if ($detailsCount !== count($request->items)) {
                DB::rollBack();
                throw new \Exception("Jumlah detail yang tersimpan ({$detailsCount}) tidak sesuai dengan jumlah item yang dikirim (" . count($request->items) . ").");
            }
            
            // Commit hanya jika semua verifikasi berhasil
            DB::commit();
            
            \Log::info('OutletInternalUseWaste store - Successfully saved:', [
                'header_id' => $headerId,
                'type' => $request->type,
                'outlet_id' => $request->outlet_id,
                'date' => $request->date,
                'status' => $status
            ]);
            
            // Activity log CREATE
            try {
                $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $request->outlet_id)->value('nama_outlet') ?? 'Unknown';
                $warehouseName = DB::table('warehouse_outlets')->where('id', $request->warehouse_outlet_id)->value('name') ?? 'Unknown';
                $typeLabel = ucfirst(str_replace('_', ' ', $request->type));
                
                DB::table('activity_logs')->insert([
                    'user_id' => $userId,
                    'activity_type' => 'create',
                    'module' => 'outlet_internal_use_waste',
                    'description' => "Membuat Category Cost Outlet: {$typeLabel} - {$outletName} ({$warehouseName})",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => null,
                    'new_data' => json_encode([
                        'header_id' => $headerId,
                        'type' => $request->type,
                        'outlet_id' => $request->outlet_id,
                        'warehouse_outlet_id' => $request->warehouse_outlet_id,
                        'date' => $request->date,
                        'status' => $status,
                        'item_count' => count($request->items)
                    ]),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $logError) {
                // Tidak throw error karena activity log bukan critical
                \Log::warning('OutletInternalUseWaste store - Activity log failed (but data saved):', [
                    'header_id' => $headerId,
                    'error' => $logError->getMessage()
                ]);
            }
            
            // NO NOTIFICATION FOR DRAFT - will be sent on submit
            
            // Always return header_id in response for both autosave and manual save
            // Return JSON response for autosave, or redirect for manual save
            if ($request->expectsJson() || $request->input('autosave')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Draft berhasil disimpan',
                    'header_id' => $headerId
                ]);
            }
            
            // For manual save, also return header_id in redirect (via flash or session)
            return redirect()->route('outlet-internal-use-waste.index')
                ->with('success', 'Draft berhasil disimpan')
                ->with('header_id', $headerId);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error in OutletInternalUseWaste store method:', [
                'errors' => $e->errors(),
                'request_data' => $request->except(['items'])
            ]);
            DB::rollBack();
            $errorMessage = 'Validasi gagal: ';
            foreach ($e->errors() as $field => $messages) {
                $errorMessage .= implode(', ', $messages) . ' ';
            }
            return redirect()->back()->withInput()->with('error', trim($errorMessage));
        } catch (\Exception $e) {
            \Log::error('Error in OutletInternalUseWaste store method:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['items']) // Exclude items to avoid log spam
            ]);
            
            // Rollback transaction jika masih aktif
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            // Buat pesan error yang lebih informatif
            $errorMessage = $e->getMessage();
            
            // Jika error terkait database constraint
            if (strpos($e->getMessage(), 'SQLSTATE') !== false) {
                if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                    $errorMessage = 'Data yang dipilih tidak valid atau tidak ditemukan. Silakan refresh halaman dan coba lagi.';
                } elseif (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $errorMessage = 'Data duplikat terdeteksi. Silakan cek data yang sudah ada.';
                } else {
                    $errorMessage = 'Terjadi kesalahan database. Silakan hubungi administrator jika masalah berlanjut.';
                }
            }
            
            return redirect()->back()->withInput()->with('error', $errorMessage);
        }
    }

    /**
     * Submit draft - change status from DRAFT to SUBMITTED/PROCESSED and trigger stock processing/approval
     */
    public function submit(Request $request, $id)
    {
        \Log::info('OutletInternalUseWaste submit method called', [
            'header_id' => $id,
            'user_id' => auth()->id(),
            'request_data' => $request->all()
        ]);
        
        try {
            $header = DB::table('outlet_internal_use_waste_headers')->where('id', $id)->first();
            
            if (!$header) {
                \Log::error('OutletInternalUseWaste submit - Header not found', ['header_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }
            
            \Log::info('OutletInternalUseWaste submit - Header found', [
                'header_id' => $id,
                'current_status' => $header->status,
                'type' => $header->type
            ]);
            
            if ($header->status !== 'DRAFT') {
                \Log::warning('OutletInternalUseWaste submit - Status is not DRAFT', [
                    'header_id' => $id,
                    'current_status' => $header->status
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya draft yang dapat di-submit. Status saat ini: ' . $header->status,
                    'current_status' => $header->status
                ], 400);
            }
            
            // Double check: if status changed between first check and transaction start, reject
            // This prevents race condition where multiple submits happen simultaneously
            $headerCheck = DB::table('outlet_internal_use_waste_headers')
                ->where('id', $id)
                ->where('status', 'DRAFT')
                ->lockForUpdate()
                ->first();
            
            if (!$headerCheck) {
                \Log::warning('OutletInternalUseWaste submit - Header status changed before transaction', [
                    'header_id' => $id,
                    'original_status' => $header->status
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Status header sudah berubah. Tidak dapat melanjutkan submit.',
                    'current_status' => $header->status
                ], 400);
            }
            
            $userId = Auth::id() ?? auth()->id();
            if (!$userId) {
                throw new \Exception('User tidak terautentikasi. Silakan login ulang.');
            }
            
            DB::beginTransaction();
            
            // Determine if approval is required based on type
            $typesRequiringApproval = ['r_and_d', 'marketing', 'wrong_maker', 'training'];
            $requiresApproval = in_array($header->type, $typesRequiringApproval);
            
            // Determine new status
            $newStatus = 'PROCESSED'; // Default for types that don't need approval
            if ($requiresApproval) {
                $newStatus = 'SUBMITTED'; // Needs approval first
            }
            
            // Get all details
            $details = DB::table('outlet_internal_use_waste_details')->where('header_id', $id)->get();
            
            // Check if there are any details
            if ($details->isEmpty()) {
                throw new \Exception('Tidak ada item yang dapat di-submit. Silakan tambahkan item terlebih dahulu.');
            }
            
            // Process stock for each detail
            foreach ($details as $item) {
                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $item->item_id)
                    ->first();
                if (!$inventoryItem) {
                    $itemName = DB::table('items')->where('id', $item->item_id)->value('name') ?? 'Unknown';
                    throw new \Exception("Item '{$itemName}' tidak ditemukan di inventory");
                }
                
                $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
                if (!$itemMaster) {
                    throw new \Exception("Item master tidak ditemukan");
                }
                
                $unit = DB::table('units')->where('id', $item->unit_id)->value('name');
                $qty_input = floatval($item->qty);
                $qty_small = 0;
                $qty_medium = 0;
                $qty_large = 0;
                $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
                $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
                $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
                $smallConv = floatval($itemMaster->small_conversion_qty ?: 1);
                $mediumConv = floatval($itemMaster->medium_conversion_qty ?: 1);
                
                if ($unit === $unitSmall) {
                    $qty_small = $qty_input;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                } elseif ($unit === $unitMedium) {
                    $qty_medium = $qty_input;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                } elseif ($unit === $unitLarge) {
                    $qty_large = $qty_input;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                } else {
                    $qty_small = $qty_input;
                }
                
                // Check stock
                $stock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where('id_outlet', $header->outlet_id)
                    ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                    ->first();
                if (!$stock) {
                    $itemName = $itemMaster->name ?? 'Unknown';
                    throw new \Exception("Stok item '{$itemName}' tidak ditemukan di outlet dan warehouse outlet yang dipilih");
                }
                
                // Validate stock availability
                if ($qty_small > $stock->qty_small) {
                    $itemName = $itemMaster->name ?? 'Unknown';
                    throw new \Exception("Quantity item '{$itemName}' melebihi stok yang tersedia. Stok tersedia: " . number_format($stock->qty_small, 2) . " {$unitSmall}");
                }
                
                // Only process stock if status will be PROCESSED (no approval needed)
                if ($newStatus === 'PROCESSED') {
                    // Update stok di outlet (kurangi)
                    $stockUpdated = DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('id_outlet', $header->outlet_id)
                        ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                        ->update([
                            'qty_small' => $stock->qty_small - $qty_small,
                            'qty_medium' => $stock->qty_medium - $qty_medium,
                            'qty_large' => $stock->qty_large - $qty_large,
                            'updated_at' => now(),
                        ]);
                    
                    if ($stockUpdated === false) {
                        throw new \Exception("Gagal mengupdate stok untuk item '{$itemMaster->name}'");
                    }
                    
                    // Insert kartu stok OUT
                    $cardInserted = DB::table('outlet_food_inventory_cards')->insert([
                        'inventory_item_id' => $inventoryItem->id,
                        'id_outlet' => $header->outlet_id,
                        'warehouse_outlet_id' => $header->warehouse_outlet_id,
                        'date' => $header->date,
                        'reference_type' => 'outlet_internal_use_waste',
                        'reference_id' => $id,
                        'out_qty_small' => $qty_small,
                        'out_qty_medium' => $qty_medium,
                        'out_qty_large' => $qty_large,
                        'cost_per_small' => $stock->last_cost_small ?? 0,
                        'cost_per_medium' => $stock->last_cost_medium ?? 0,
                        'cost_per_large' => $stock->last_cost_large ?? 0,
                        'value_out' => $qty_small * ($stock->last_cost_small ?? 0),
                        'saldo_qty_small' => $stock->qty_small - $qty_small,
                        'saldo_qty_medium' => $stock->qty_medium - $qty_medium,
                        'saldo_qty_large' => $stock->qty_large - $qty_large,
                        'saldo_value' => ($stock->qty_small - $qty_small) * ($stock->last_cost_small ?? 0),
                        'description' => 'Stock Out - ' . $header->type,
                        'created_at' => now(),
                    ]);
                    
                    if (!$cardInserted) {
                        throw new \Exception("Gagal menyimpan kartu stok untuk item '{$itemMaster->name}'");
                    }
                }
            }
            
            // Create approval flows if approvers provided and approval is required
            if ($requiresApproval && !empty($request->approvers)) {
                if (!is_array($request->approvers)) {
                    throw new \Exception("Format approvers tidak valid. Harus berupa array.");
                }
                
                foreach ($request->approvers as $index => $approverId) {
                    if (empty($approverId)) {
                        throw new \Exception("Approver ID tidak boleh kosong untuk approver ke-" . ($index + 1));
                    }
                    
                    // Validasi approver exists
                    $approverExists = DB::table('users')->where('id', $approverId)->exists();
                    if (!$approverExists) {
                        throw new \Exception("Approver dengan ID {$approverId} tidak ditemukan untuk approver ke-" . ($index + 1));
                    }
                    
                    $flowInserted = DB::table('outlet_internal_use_waste_approval_flows')->insert([
                        'header_id' => $id,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1, // Level 1 = terendah, level terakhir = tertinggi
                        'status' => 'PENDING',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    if (!$flowInserted) {
                        throw new \Exception("Gagal menyimpan approval flow untuk approver ke-" . ($index + 1));
                    }
                }
            } elseif ($requiresApproval && empty($request->approvers)) {
                throw new \Exception("Tipe ini wajib memiliki minimal 1 approver");
            }
            
            // Generate nomor baru saat submit (jika masih DRAFT)
            $newNumber = null;
            if ($header->status === 'DRAFT' && (strpos($header->number ?? '', 'DRAFT-') === 0)) {
                // Generate nomor baru dengan format: CIU-YYYYMMDD-XXXX (Category Internal Use)
                $date = now()->format('Ymd');
                $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                $newNumber = 'CIU-' . $date . '-' . $random;
                
                \Log::info('OutletInternalUseWaste submit - Generating new number', [
                    'header_id' => $id,
                    'old_number' => $header->number,
                    'new_number' => $newNumber
                ]);
            }
            
            // Update header status LAST, after all operations succeed
            \Log::info('OutletInternalUseWaste submit - Updating status (final step)', [
                'header_id' => $id,
                'old_status' => $header->status,
                'new_status' => $newStatus,
                'stock_processed' => $newStatus === 'PROCESSED' ? 'yes' : 'no',
                'approval_flows_created' => $requiresApproval ? 'yes' : 'no',
                'new_number' => $newNumber
            ]);
            
            // Use lockForUpdate to prevent race conditions
            $headerToUpdate = DB::table('outlet_internal_use_waste_headers')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();
            
            if (!$headerToUpdate) {
                \Log::error('OutletInternalUseWaste submit - Header not found for update', ['header_id' => $id]);
                throw new \Exception('Header tidak ditemukan saat akan update status');
            }
            
            // Double check status is still DRAFT before updating
            if ($headerToUpdate->status !== 'DRAFT') {
                \Log::warning('OutletInternalUseWaste submit - Status changed before update', [
                    'header_id' => $id,
                    'expected_status' => 'DRAFT',
                    'actual_status' => $headerToUpdate->status
                ]);
                throw new \Exception('Status header sudah berubah menjadi: ' . $headerToUpdate->status . '. Tidak dapat melanjutkan submit.');
            }
            
            // Prepare update data
            $updateData = [
                'status' => $newStatus,
                'updated_at' => now()
            ];
            
            // Update nomor jika masih DRAFT
            if ($newNumber) {
                $updateData['number'] = $newNumber;
            }
            
            $statusUpdated = DB::table('outlet_internal_use_waste_headers')
                ->where('id', $id)
                ->where('status', 'DRAFT') // Only update if still DRAFT
                ->update($updateData);
            
            if ($statusUpdated === false || $statusUpdated === 0) {
                \Log::error('OutletInternalUseWaste submit - Failed to update status', [
                    'header_id' => $id,
                    'rows_affected' => $statusUpdated
                ]);
                throw new \Exception('Gagal mengupdate status header. Kemungkinan status sudah berubah.');
            }
            
            \Log::info('OutletInternalUseWaste submit - Status update query executed successfully', [
                'header_id' => $id,
                'rows_affected' => $statusUpdated,
                'new_status' => $newStatus
            ]);
            
            // Verify status was updated (before commit, still in transaction)
            $updatedHeader = DB::table('outlet_internal_use_waste_headers')->where('id', $id)->first();
            if (!$updatedHeader) {
                \Log::error('OutletInternalUseWaste submit - Header not found after update', ['header_id' => $id]);
                throw new \Exception('Header tidak ditemukan setelah update');
            }
            
            if ($updatedHeader->status !== $newStatus) {
                \Log::error('OutletInternalUseWaste submit - Status mismatch after update', [
                    'header_id' => $id,
                    'expected_status' => $newStatus,
                    'actual_status' => $updatedHeader->status
                ]);
                throw new \Exception('Status tidak berhasil diupdate. Status saat ini: ' . $updatedHeader->status . ', seharusnya: ' . $newStatus);
            }
            
            \Log::info('OutletInternalUseWaste submit - Status verified successfully, committing transaction...', [
                'header_id' => $id,
                'new_status' => $newStatus
            ]);
            
            DB::commit();
            
            // Verify again after commit
            $finalHeader = DB::table('outlet_internal_use_waste_headers')->where('id', $id)->first();
            \Log::info('OutletInternalUseWaste submit - Transaction committed', [
                'header_id' => $id,
                'final_status' => $finalHeader->status ?? 'null',
                'expected_status' => $newStatus
            ]);
            
            if ($finalHeader && $finalHeader->status !== $newStatus) {
                \Log::error('OutletInternalUseWaste submit - Status changed after commit!', [
                    'header_id' => $id,
                    'expected_status' => $newStatus,
                    'actual_status' => $finalHeader->status
                ]);
            }
            
            // Activity log SUBMIT
            try {
                $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $header->outlet_id)->value('nama_outlet') ?? 'Unknown';
                $warehouseName = DB::table('warehouse_outlets')->where('id', $header->warehouse_outlet_id)->value('name') ?? 'Unknown';
                $typeLabel = ucfirst(str_replace('_', ' ', $header->type));
                
                DB::table('activity_logs')->insert([
                    'user_id' => $userId,
                    'activity_type' => 'submit',
                    'module' => 'outlet_internal_use_waste',
                    'description' => "Submit Category Cost Outlet: {$typeLabel} - {$outletName} ({$warehouseName})",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => json_encode(['status' => 'DRAFT']),
                    'new_data' => json_encode(['status' => $newStatus]),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $logError) {
                \Log::warning('OutletInternalUseWaste submit - Activity log failed:', ['error' => $logError->getMessage()]);
            }
            
            // Send notification after commit (so it doesn't cause rollback if it fails)
            if ($requiresApproval && !empty($request->approvers)) {
                try {
                    $this->sendNotificationToNextApprover($id);
                } catch (\Exception $notifError) {
                    \Log::warning('OutletInternalUseWaste submit - Notification failed (but data saved):', [
                        'header_id' => $id,
                        'error' => $notifError->getMessage()
                    ]);
                }
            }
            
            // Get final status after commit to ensure it's correct
            $finalStatusCheck = DB::table('outlet_internal_use_waste_headers')
                ->where('id', $id)
                ->value('status');
            
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil di-submit',
                'status' => $newStatus,
                'current_status' => $finalStatusCheck // Return current status to frontend
            ]);
        } catch (\Exception $e) {
            // Check if transaction is still active before rollback
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
                \Log::info('OutletInternalUseWaste submit - Transaction rolled back due to error');
            }
            
            \Log::error('Error in OutletInternalUseWaste submit method:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'header_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Check current status after rollback
            $currentHeader = DB::table('outlet_internal_use_waste_headers')->where('id', $id)->first();
            if ($currentHeader) {
                \Log::info('OutletInternalUseWaste submit - Current status after rollback', [
                    'header_id' => $id,
                    'status' => $currentHeader->status
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function storeAndSubmit(Request $request)
    {
        \Log::info('OutletInternalUseWaste storeAndSubmit method called with data:', $request->all());
        
        try {
            $request->validate([
                'type' => 'required|in:internal_use,spoil,waste,r_and_d,marketing,non_commodity,guest_supplies,wrong_maker,training',
                'date' => 'required|date',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'warehouse_outlet_id' => 'required|exists:warehouse_outlets,id',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.item_id' => 'required|exists:items,id',
                'items.*.qty' => 'required|numeric|min:0',
                'items.*.unit_id' => 'required|exists:units,id',
                'items.*.note' => 'nullable|string',
                'approvers' => 'nullable|array'
            ]);

            $userId = Auth::id() ?? auth()->id();
            if (!$userId) {
                throw new \Exception('User tidak terautentikasi. Silakan login ulang.');
            }
            
            DB::beginTransaction();
            
            // Generate final number directly (not DRAFT)
            $date = now()->format('Ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $finalNumber = 'CIU-' . $date . '-' . $random;
            
            // Determine status based on type
            $requiresApproval = in_array($request->type, ['r_and_d', 'marketing', 'wrong_maker', 'training']);
            $newStatus = $requiresApproval ? 'SUBMITTED' : 'PROCESSED';
            
            // Create header with final status directly
            $headerId = DB::table('outlet_internal_use_waste_headers')->insertGetId([
                'number' => $finalNumber,
                'type' => $request->type,
                'date' => $request->date,
                'outlet_id' => $request->outlet_id,
                'warehouse_outlet_id' => $request->warehouse_outlet_id,
                'notes' => $request->notes ?? null,
                'status' => $newStatus, // Directly set to final status
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Insert details and process stock (same logic as submit method)
            foreach ($request->items as $itemIndex => $item) {
                try {
                    // Insert detail
                    DB::table('outlet_internal_use_waste_details')->insert([
                        'header_id' => $headerId,
                        'item_id' => $item['item_id'],
                        'qty' => $item['qty'],
                        'unit_id' => $item['unit_id'],
                        'note' => $item['note'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    // Process stock immediately (same logic as submit)
                    $inventoryItem = DB::table('outlet_food_inventory_items')
                        ->where('item_id', $item['item_id'])
                        ->first();
                    if (!$inventoryItem) {
                        $itemName = DB::table('items')->where('id', $item['item_id'])->value('name') ?? 'Unknown';
                        throw new \Exception("Item '{$itemName}' tidak ditemukan di inventory");
                    }
                    
                    $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
                    if (!$itemMaster) {
                        throw new \Exception("Item master tidak ditemukan");
                    }
                    
                    $unit = DB::table('units')->where('id', $item['unit_id'])->value('name');
                    $qty_input = floatval($item['qty']);
                    $qty_small = 0;
                    $qty_medium = 0;
                    $qty_large = 0;
                    $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
                    $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
                    $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
                    $smallConv = floatval($itemMaster->small_conversion_qty ?: 1);
                    $mediumConv = floatval($itemMaster->medium_conversion_qty ?: 1);
                    
                    if ($unit === $unitSmall) {
                        $qty_small = $qty_input;
                        $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                        $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                    } elseif ($unit === $unitMedium) {
                        $qty_medium = $qty_input;
                        $qty_small = $qty_medium * $smallConv;
                        $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                    } elseif ($unit === $unitLarge) {
                        $qty_large = $qty_input;
                        $qty_medium = $qty_large * $mediumConv;
                        $qty_small = $qty_medium * $smallConv;
                    } else {
                        $qty_small = $qty_input;
                    }
                    
                    // Check stock
                    $stock = DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('id_outlet', $request->outlet_id)
                        ->where('warehouse_outlet_id', $request->warehouse_outlet_id)
                        ->first();
                    
                    if (!$stock) {
                        $itemName = DB::table('items')->where('id', $item['item_id'])->value('name') ?? 'Unknown';
                        throw new \Exception("Stok item '{$itemName}' tidak ditemukan");
                    }
                    
                    if ($stock->qty_small < $qty_small) {
                        $itemName = DB::table('items')->where('id', $item['item_id'])->value('name') ?? 'Unknown';
                        throw new \Exception("Stok item '{$itemName}' tidak cukup. Stok tersedia: " . number_format($stock->qty_small, 2) . ", dibutuhkan: " . number_format($qty_small, 2));
                    }
                    
                    // Update stock
                    $saldo_qty_small = $stock->qty_small - $qty_small;
                    $saldo_qty_medium = $stock->qty_medium - $qty_medium;
                    $saldo_qty_large = $stock->qty_large - $qty_large;
                    $saldo_value = $saldo_qty_small * $stock->last_cost_small;
                    
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $stock->id)
                        ->update([
                            'qty_small' => $saldo_qty_small,
                            'qty_medium' => $saldo_qty_medium,
                            'qty_large' => $saldo_qty_large,
                            'updated_at' => now()
                        ]);
                    
                    // Insert stock card
                    DB::table('outlet_food_inventory_cards')->insert([
                        'inventory_item_id' => $inventoryItem->id,
                        'id_outlet' => $request->outlet_id,
                        'warehouse_outlet_id' => $request->warehouse_outlet_id,
                        'date' => $request->date,
                        'reference_type' => 'outlet_internal_use_waste',
                        'reference_id' => $headerId,
                        'out_qty_small' => $qty_small,
                        'out_qty_medium' => $qty_medium,
                        'out_qty_large' => $qty_large,
                        'cost_per_small' => $stock->last_cost_small,
                        'cost_per_medium' => $stock->last_cost_medium,
                        'cost_per_large' => $stock->last_cost_large,
                        'value_out' => $qty_small * $stock->last_cost_small,
                        'saldo_qty_small' => $saldo_qty_small,
                        'saldo_qty_medium' => $saldo_qty_medium,
                        'saldo_qty_large' => $saldo_qty_large,
                        'saldo_value' => $saldo_value,
                        'description' => 'Stock Out - ' . ucfirst(str_replace('_', ' ', $request->type)),
                        'created_at' => now()
                    ]);
                } catch (\Exception $itemError) {
                    throw new \Exception("Error pada item ke-" . ($itemIndex + 1) . ": " . $itemError->getMessage());
                }
            }
            
            // Create approval flows if required
            if ($requiresApproval && !empty($request->approvers)) {
                foreach ($request->approvers as $index => $approverId) {
                    DB::table('outlet_internal_use_waste_approval_flows')->insert([
                        'header_id' => $headerId,
                        'approver_id' => $approverId,
                        'level' => $index + 1,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
            
            DB::commit();
            
            // Activity log
            try {
                $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $request->outlet_id)->value('nama_outlet') ?? 'Unknown';
                $warehouseName = DB::table('warehouse_outlets')->where('id', $request->warehouse_outlet_id)->value('name') ?? 'Unknown';
                $typeLabel = ucfirst(str_replace('_', ' ', $request->type));
                
                DB::table('activity_logs')->insert([
                    'user_id' => $userId,
                    'activity_type' => 'submit',
                    'module' => 'outlet_internal_use_waste',
                    'description' => "Submit Category Cost Outlet: {$typeLabel} - {$outletName} ({$warehouseName})",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => json_encode(['status' => null]),
                    'new_data' => json_encode(['status' => $newStatus]),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $logError) {
                \Log::warning('OutletInternalUseWaste storeAndSubmit - Activity log failed:', ['error' => $logError->getMessage()]);
            }
            
            // Send notification after commit (so it doesn't cause rollback if it fails)
            if ($requiresApproval && !empty($request->approvers)) {
                try {
                    $this->sendNotificationToNextApprover($headerId);
                } catch (\Exception $notifError) {
                    \Log::warning('OutletInternalUseWaste storeAndSubmit - Notification failed (but data saved):', [
                        'header_id' => $headerId,
                        'error' => $notifError->getMessage()
                    ]);
                }
            }
            
            \Log::info('OutletInternalUseWaste storeAndSubmit success', ['header_id' => $headerId]);
            
            return response()->json([
                'success' => true,
                'header_id' => $headerId,
                'message' => 'Data berhasil disubmit',
                'status' => $newStatus
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('OutletInternalUseWaste storeAndSubmit ERROR', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function edit($id)
    {
        $user = auth()->user();
        
        // Get header
        $header = DB::table('outlet_internal_use_waste_headers')->where('id', $id)->first();
        if (!$header) {
            abort(404, 'Data tidak ditemukan');
        }
        
        // Only allow edit if status is DRAFT
        if ($header->status !== 'DRAFT') {
            abort(403, 'Hanya draft yang dapat diedit');
        }
        
        // Check if user is the creator or admin
        if ($header->created_by != $user->id && $user->id_outlet != 1) {
            abort(403, 'Anda tidak memiliki hak untuk mengedit draft ini');
        }
        
        // Get details
        $details = DB::table('outlet_internal_use_waste_details as d')
            ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'd.unit_id', '=', 'u.id')
            ->select('d.*', 'i.name as item_name', 'u.name as unit_name')
            ->where('d.header_id', $id)
            ->get();
        
        // Get approval flows if any
        $approvalFlows = DB::table('outlet_internal_use_waste_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('af.header_id', $id)
            ->select(
                'af.approver_id',
                'u.nama_lengkap as name',
                'u.email',
                'j.nama_jabatan as jabatan'
            )
            ->orderBy('af.approval_level')
            ->get();
        
        // Get outlets
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();
        
        // Get warehouse outlets
        if ($user->id_outlet == 1) {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        } else {
            $warehouse_outlets = DB::table('warehouse_outlets')
                ->where('outlet_id', $user->id_outlet)
                ->where('status', 'active')
                ->select('id', 'name', 'outlet_id')
                ->orderBy('name')
                ->get();
        }
        
        $items = DB::table('items')->where('status', 'active')->get();
        $units = DB::table('units')->get();
        
        return inertia('OutletInternalUseWaste/Create', [
            'header' => $header,
            'details' => $details,
            'approvalFlows' => $approvalFlows,
            'outlets' => $outlets,
            'items' => $items,
            'units' => $units,
            'warehouse_outlets' => $warehouse_outlets,
            'isEdit' => true,
        ]);
    }

    public function show($id)
    {
        $header = DB::table('outlet_internal_use_waste_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->select('h.*', 'o.nama_outlet as outlet_name')
            ->where('h.id', $id)
            ->first();
        if (!$header) {
            abort(404, 'Data tidak ditemukan');
        }
        $details = DB::table('outlet_internal_use_waste_details as d')
            ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'd.unit_id', '=', 'u.id')
            ->select('d.*', 'i.name as item_name', 'u.name as unit_name')
            ->where('d.header_id', $id)
            ->get();
        return inertia('OutletInternalUseWaste/Show', [
            'id' => $id,
            'header' => $header,
            'details' => $details
        ]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            // Ambil data header
            $header = DB::table('outlet_internal_use_waste_headers')->where('id', $id)->first();
            if (!$header) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
            }

            // Ambil semua detail untuk header ini
            $details = DB::table('outlet_internal_use_waste_details')->where('header_id', $id)->get();

            // Proses rollback stok HANYA jika status bukan DRAFT
            // Karena DRAFT tidak pernah memotong stock, jadi tidak perlu rollback
            $shouldRollbackStock = $header->status !== 'DRAFT';
            
            \Log::info('OutletInternalUseWaste destroy - Starting delete', [
                'header_id' => $id,
                'status' => $header->status,
                'should_rollback_stock' => $shouldRollbackStock,
                'details_count' => $details->count()
            ]);

            // Proses rollback stok untuk setiap detail (hanya jika bukan DRAFT)
            foreach ($details as $detail) {
                // Cari inventory_item_id
                $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $detail->item_id)->first();
                if (!$inventoryItem) {
                    throw new \Exception('Inventory item not found for item_id: ' . $detail->item_id);
                }
                $inventory_item_id = $inventoryItem->id;
                
                \Log::info('OutletInternalUseWaste destroy - Processing detail', [
                    'header_id' => $id,
                    'detail_id' => $detail->id ?? 'N/A',
                    'item_id' => $detail->item_id,
                    'inventory_item_id' => $inventory_item_id
                ]);

                // Ambil data konversi dari tabel items
                $itemMaster = DB::table('items')->where('id', $detail->item_id)->first();
                $unit = DB::table('units')->where('id', $detail->unit_id)->value('name');
                $qty_input = $detail->qty;
                $qty_small = 0;
                $qty_medium = 0;
                $qty_large = 0;

                $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
                $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
                $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

                if ($unit === $unitSmall) {
                    $qty_small = $qty_input;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                } elseif ($unit === $unitMedium) {
                    $qty_medium = $qty_input;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                } elseif ($unit === $unitLarge) {
                    $qty_large = $qty_input;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                } else {
                    $qty_small = $qty_input;
                }

                // Rollback stok di outlet_food_inventory_stocks (HANYA jika bukan DRAFT)
                if ($shouldRollbackStock) {
                    $stock = DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $inventory_item_id)
                        ->where('id_outlet', $header->outlet_id)
                        ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                        ->first();
                    
                    if ($stock) {
                        $oldQtySmall = $stock->qty_small;
                        $oldQtyMedium = $stock->qty_medium;
                        $oldQtyLarge = $stock->qty_large;
                        
                        $stockUpdated = DB::table('outlet_food_inventory_stocks')
                            ->where('inventory_item_id', $inventory_item_id)
                            ->where('id_outlet', $header->outlet_id)
                            ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                            ->update([
                                'qty_small' => DB::raw('qty_small + ' . $qty_small),
                                'qty_medium' => DB::raw('qty_medium + ' . $qty_medium),
                                'qty_large' => DB::raw('qty_large + ' . $qty_large),
                                'updated_at' => now(),
                            ]);
                        
                        if ($stockUpdated === false || $stockUpdated === 0) {
                            \Log::error('OutletInternalUseWaste destroy - Failed to update stock', [
                                'header_id' => $id,
                                'item_id' => $detail->item_id,
                                'inventory_item_id' => $inventory_item_id
                            ]);
                            throw new \Exception("Gagal mengupdate stok untuk item ID: {$detail->item_id}");
                        }
                        
                        // Verify stock was updated
                        $updatedStock = DB::table('outlet_food_inventory_stocks')
                            ->where('inventory_item_id', $inventory_item_id)
                            ->where('id_outlet', $header->outlet_id)
                            ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                            ->first();
                        
                        \Log::info('OutletInternalUseWaste destroy - Stock rolled back successfully', [
                            'header_id' => $id,
                            'item_id' => $detail->item_id,
                            'qty_small_added' => $qty_small,
                            'qty_medium_added' => $qty_medium,
                            'qty_large_added' => $qty_large,
                            'old_qty_small' => $oldQtySmall,
                            'new_qty_small' => $updatedStock->qty_small ?? 'N/A',
                            'stock_updated' => $stockUpdated
                        ]);
                    } else {
                        \Log::warning('OutletInternalUseWaste destroy - Stock not found for rollback', [
                            'header_id' => $id,
                            'item_id' => $detail->item_id,
                            'inventory_item_id' => $inventory_item_id,
                            'outlet_id' => $header->outlet_id,
                            'warehouse_outlet_id' => $header->warehouse_outlet_id
                        ]);
                        // Don't throw error, just log warning - stock might have been deleted or moved
                    }

                    // Hapus kartu stok OUT terkait untuk header ini
                    // Cari semua kartu stock yang terkait dengan header ini dan item ini
                    $cardsToDelete = DB::table('outlet_food_inventory_cards')
                        ->where('reference_type', 'outlet_internal_use_waste')
                        ->where('reference_id', $id)
                        ->where('inventory_item_id', $inventory_item_id)
                        ->get();
                    
                    \Log::info('OutletInternalUseWaste destroy - Found stock cards to delete', [
                        'header_id' => $id,
                        'inventory_item_id' => $inventory_item_id,
                        'cards_found' => $cardsToDelete->count(),
                        'cards_reference_ids' => $cardsToDelete->pluck('reference_id')->toArray(),
                        'cards_ids' => $cardsToDelete->pluck('id')->toArray()
                    ]);
                    
                    // Hapus kartu stok OUT terkait untuk header ini dan item ini
                    $cardsDeleted = DB::table('outlet_food_inventory_cards')
                        ->where('reference_type', 'outlet_internal_use_waste')
                        ->where('reference_id', $id)
                        ->where('inventory_item_id', $inventory_item_id)
                        ->delete();
                    
                    \Log::info('OutletInternalUseWaste destroy - Stock cards deleted', [
                        'header_id' => $id,
                        'inventory_item_id' => $inventory_item_id,
                        'cards_deleted' => $cardsDeleted,
                        'expected_cards' => $cardsToDelete->count()
                    ]);
                    
                    // Jika tidak ada kartu stock yang dihapus, cek apakah ada kartu stock dengan reference_id yang berbeda
                    // Mungkin kartu stock dibuat dengan reference_id yang salah
                    if ($cardsDeleted === 0 && $shouldRollbackStock) {
                        // Cari kartu stock dengan kriteria yang sama (item, outlet, warehouse, date) tapi reference_id berbeda
                        $otherCards = DB::table('outlet_food_inventory_cards')
                            ->where('reference_type', 'outlet_internal_use_waste')
                            ->where('inventory_item_id', $inventory_item_id)
                            ->where('id_outlet', $header->outlet_id)
                            ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                            ->where('date', $header->date)
                            ->get();
                        
                        if ($otherCards->count() > 0) {
                            \Log::warning('OutletInternalUseWaste destroy - Found stock cards with different reference_id', [
                                'header_id' => $id,
                                'inventory_item_id' => $inventory_item_id,
                                'other_cards_count' => $otherCards->count(),
                                'other_cards_reference_ids' => $otherCards->pluck('reference_id')->toArray(),
                                'other_cards_descriptions' => $otherCards->pluck('description')->toArray(),
                                'other_cards_ids' => $otherCards->pluck('id')->toArray()
                            ]);
                            
                            // Jika ditemukan kartu stock dengan reference_id yang berbeda, tapi sesuai dengan detail ini
                            // Mungkin ada bug saat create kartu stock, jadi kita hapus juga
                            // Tapi hanya jika qty dan date sesuai
                            foreach ($otherCards as $otherCard) {
                                // Cek apakah kartu stock ini sesuai dengan detail yang akan dihapus
                                // Jika qty_small sesuai (dalam toleransi), hapus juga
                                $qtyDiff = abs($otherCard->out_qty_small - $qty_small);
                                if ($qtyDiff < 0.01) { // Toleransi 0.01
                                    \Log::info('OutletInternalUseWaste destroy - Deleting matching stock card with different reference_id', [
                                        'header_id' => $id,
                                        'card_id' => $otherCard->id,
                                        'card_reference_id' => $otherCard->reference_id,
                                        'card_qty_small' => $otherCard->out_qty_small,
                                        'expected_qty_small' => $qty_small
                                    ]);
                                    
                                    DB::table('outlet_food_inventory_cards')
                                        ->where('id', $otherCard->id)
                                        ->delete();
                                    
                                    $cardsDeleted++;
                                }
                            }
                        }
                    }
                } else {
                    \Log::info('OutletInternalUseWaste destroy - Skipping stock rollback (status is DRAFT)', [
                        'header_id' => $id,
                        'status' => $header->status
                    ]);
                }
            }

            // Hapus semua detail terlebih dahulu
            DB::table('outlet_internal_use_waste_details')->where('header_id', $id)->delete();
            
            // Hapus header
            DB::table('outlet_internal_use_waste_headers')->where('id', $id)->delete();

            // Activity log DELETE
            try {
                $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $header->outlet_id)->value('nama_outlet') ?? 'Unknown';
                $warehouseName = DB::table('warehouse_outlets')->where('id', $header->warehouse_outlet_id)->value('name') ?? 'Unknown';
                $typeLabel = ucfirst(str_replace('_', ' ', $header->type));
                
                DB::table('activity_logs')->insert([
                    'user_id' => Auth::id(),
                    'activity_type' => 'delete',
                    'module' => 'outlet_internal_use_waste',
                    'description' => "Menghapus Category Cost Outlet: {$typeLabel} - {$outletName} ({$warehouseName})",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'old_data' => json_encode([
                        'header_id' => $header->id,
                        'type' => $header->type,
                        'outlet_id' => $header->outlet_id,
                        'warehouse_outlet_id' => $header->warehouse_outlet_id,
                        'date' => $header->date,
                        'status' => $header->status,
                        'details_count' => $details->count()
                    ]),
                    'new_data' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $logError) {
                // Tidak throw error karena activity log bukan critical
                \Log::warning('OutletInternalUseWaste destroy - Activity log failed (but data deleted):', [
                    'header_id' => $id,
                    'error' => $logError->getMessage()
                ]);
            }
            
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getItemUnits($itemId)
    {
        $item = DB::table('items')->where('id', $itemId)->first();
        if (!$item) {
            return response()->json(['units' => []]);
        }

        $units = [];
        if ($item->small_unit_id) {
            $units[] = [
                'id' => $item->small_unit_id,
                'name' => DB::table('units')->where('id', $item->small_unit_id)->value('name')
            ];
        }
        if ($item->medium_unit_id) {
            $units[] = [
                'id' => $item->medium_unit_id,
                'name' => DB::table('units')->where('id', $item->medium_unit_id)->value('name')
            ];
        }
        if ($item->large_unit_id) {
            $units[] = [
                'id' => $item->large_unit_id,
                'name' => DB::table('units')->where('id', $item->large_unit_id)->value('name')
            ];
        }

        return response()->json(['units' => $units]);
    }

    public function report(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $ruko_id = $request->input('ruko_id');

        $query = DB::table('outlet_internal_use_wastes')
            ->leftJoin('tbl_data_outlet', 'outlet_internal_use_wastes.outlet_id', '=', 'tbl_data_outlet.id_outlet')
            ->leftJoin('items', 'outlet_internal_use_wastes.item_id', '=', 'items.id')
            ->leftJoin('units', 'outlet_internal_use_wastes.unit_id', '=', 'units.id')
            ->leftJoin('tbl_data_ruko', 'outlet_internal_use_wastes.ruko_id', '=', 'tbl_data_ruko.id_ruko')
            ->select(
                'outlet_internal_use_wastes.*',
                'tbl_data_outlet.nama_outlet as outlet_name',
                'items.name as item_name',
                'units.name as unit_name',
                'tbl_data_ruko.nama_ruko'
            )
            ->where('outlet_internal_use_wastes.type', 'internal_use');

        if ($from) {
            $query->where('outlet_internal_use_wastes.date', '>=', $from);
        }
        if ($to) {
            $query->where('outlet_internal_use_wastes.date', '<=', $to);
        }
        if ($ruko_id) {
            $query->where('outlet_internal_use_wastes.ruko_id', $ruko_id);
        }
        $data = $query->orderByDesc('outlet_internal_use_wastes.date')->orderByDesc('outlet_internal_use_wastes.id')->get();

        $rukos = DB::table('tbl_data_ruko')->get();

        return inertia('OutletInternalUseWaste/Report', [
            'data' => $data,
            'rukos' => $rukos,
            'filters' => $request->only(['from', 'to', 'ruko_id'])
        ]);
    }

    public function reportWasteSpoil(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $outlet_id = $request->input('outlet_id');

        $query = DB::table('outlet_internal_use_wastes')
            ->leftJoin('tbl_data_outlet', 'outlet_internal_use_wastes.outlet_id', '=', 'tbl_data_outlet.id_outlet')
            ->leftJoin('items', 'outlet_internal_use_wastes.item_id', '=', 'items.id')
            ->leftJoin('units', 'outlet_internal_use_wastes.unit_id', '=', 'units.id')
            ->select(
                'outlet_internal_use_wastes.*',
                'tbl_data_outlet.nama_outlet as outlet_name',
                'items.name as item_name',
                'units.name as unit_name'
            )
            ->whereIn('outlet_internal_use_wastes.type', ['spoil', 'waste']);

        if ($from) {
            $query->where('outlet_internal_use_wastes.date', '>=', $from);
        }
        if ($to) {
            $query->where('outlet_internal_use_wastes.date', '<=', $to);
        }
        if ($outlet_id) {
            $query->where('outlet_internal_use_wastes.outlet_id', $outlet_id);
        }
        $data = $query->orderByDesc('outlet_internal_use_wastes.date')->orderByDesc('outlet_internal_use_wastes.id')->get();

        $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();

        return inertia('OutletInternalUseWaste/ReportWasteSpoil', [
            'data' => $data,
            'outlets' => $outlets,
            'filters' => $request->only(['from', 'to', 'outlet_id'])
        ]);
    }

    /**
     * Universal report for internal use, spoil, waste (with filter type, warehouse, date, outlet)
     */
    public function reportUniversal(Request $request)
    {
        $user = auth()->user();
        $type = $request->input('type');
        $warehouse_outlet_id = $request->input('warehouse_outlet_id');
        $from = $request->input('from');
        $to = $request->input('to');
        $selected_outlet_id = $request->input('outlet_id');

        // Jika belum ada filter, return view kosong dengan filter saja
        if (!$from || !$to) {
            $types = [
                ['value' => '', 'label' => 'Semua'],
                ['value' => 'internal_use', 'label' => 'Internal Use'],
                ['value' => 'spoil', 'label' => 'Spoil'],
                ['value' => 'waste', 'label' => 'Waste'],
                ['value' => 'r_and_d', 'label' => 'R & D'],
                ['value' => 'marketing', 'label' => 'Marketing'],
                ['value' => 'non_commodity', 'label' => 'Non Commodity'],
                ['value' => 'guest_supplies', 'label' => 'Guest Supplies'],
                ['value' => 'wrong_maker', 'label' => 'Wrong Maker'],
                ['value' => 'training', 'label' => 'Training'],
            ];
            
            // Filter warehouse outlets based on user outlet
            $warehouse_outlets_query = DB::table('warehouse_outlets')->where('status', 'active');
            if ($user->id_outlet != 1) {
                $warehouse_outlets_query->where('outlet_id', $user->id_outlet);
            }
            $warehouse_outlets = $warehouse_outlets_query->select('id', 'name', 'outlet_id')->orderBy('name')->get();
            
            $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();

            return inertia('OutletInternalUseWaste/ReportUniversal', [
                'data' => [],
                'types' => $types,
                'warehouse_outlets' => $warehouse_outlets,
                'outlets' => $outlets,
                'filters' => $request->only(['type', 'warehouse_outlet_id', 'from', 'to', 'outlet_id']),
                'total_per_type' => [],
                'user_outlet_id' => $user->id_outlet,
            ]);
        }

        // Validasi: Maksimal range 3 bulan untuk mencegah timeout
        $fromDate = \Carbon\Carbon::parse($from);
        $toDate = \Carbon\Carbon::parse($to);
        $diffMonths = $fromDate->diffInMonths($toDate);
        
        if ($diffMonths > 3) {
            return redirect()->route('outlet-internal-use-waste.report-universal')
                ->with('error', 'Range tanggal maksimal 3 bulan. Silakan pilih range yang lebih kecil.')
                ->withInput($request->only(['type', 'warehouse_outlet_id', 'from', 'to', 'outlet_id']));
        }

        // Validasi outlet: jika user bukan admin, gunakan outlet user
        if ($user->id_outlet != 1) {
            $selected_outlet_id = $user->id_outlet;
        }

        // Type yang memerlukan approval: hanya yang sudah approved yang masuk ke report
        $typesRequiringApproval = ['r_and_d', 'marketing', 'wrong_maker', 'training'];
        
        $query = DB::table('outlet_internal_use_waste_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->select(
                'h.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name'
            );
        if ($user->id_outlet != 1) {
            $query->where('h.outlet_id', $user->id_outlet);
        } else if ($request->filled('outlet_id')) {
            $query->where('h.outlet_id', $request->input('outlet_id'));
        }
        if ($type) {
            $query->where('h.type', $type);
            // Jika type memerlukan approval, hanya ambil yang sudah approved
            if (in_array($type, $typesRequiringApproval)) {
                $query->where('h.status', 'APPROVED');
            }
        } else {
            // Jika tidak ada filter type, untuk type yang memerlukan approval hanya ambil yang sudah approved
            $query->where(function($q) use ($typesRequiringApproval) {
                // Type yang tidak memerlukan approval: semua status
                $q->whereNotIn('h.type', $typesRequiringApproval)
                  // Type yang memerlukan approval: hanya yang sudah approved
                  ->orWhere(function($subQ) use ($typesRequiringApproval) {
                      $subQ->whereIn('h.type', $typesRequiringApproval)
                           ->where('h.status', 'APPROVED');
                  });
            });
        }
        if ($warehouse_outlet_id) {
            $query->where('h.warehouse_outlet_id', $warehouse_outlet_id);
        }
        // Filter date wajib
        $query->where('h.date', '>=', $from);
        $query->where('h.date', '<=', $to);
        
        $data = $query->orderByDesc('h.date')->orderByDesc('h.id')->get();

        // Hitung total per type - Optimasi dengan batch query
        $headerIds = $data->pluck('id')->all();
        $totalPerType = [];
        if ($headerIds && count($headerIds) > 0) {
            // Batch query untuk details
            $details = DB::table('outlet_internal_use_waste_details as d')
                ->join('outlet_internal_use_waste_headers as h', 'd.header_id', '=', 'h.id')
                ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
                ->leftJoin('units as u', 'd.unit_id', '=', 'u.id')
                ->select('d.*', 'h.type as header_type', 'h.date as header_date', 'h.outlet_id as header_outlet_id', 'h.warehouse_outlet_id as header_warehouse_outlet_id', 'i.small_unit_id', 'i.medium_unit_id', 'i.large_unit_id', 'i.small_conversion_qty', 'i.medium_conversion_qty')
                ->whereIn('d.header_id', $headerIds)
                ->get();
            
            // Batch query untuk inventory items
            $itemIds = $details->pluck('item_id')->unique()->all();
            $inventoryItems = [];
            if (count($itemIds) > 0) {
                $inventoryItemsData = DB::table('outlet_food_inventory_items')
                    ->whereIn('item_id', $itemIds)
                    ->get()
                    ->keyBy('item_id');
                $inventoryItems = $inventoryItemsData->toArray();
            }
            
            // Batch query untuk MAC histories - optimasi dengan subquery
            $inventoryItemIds = collect($inventoryItems)->pluck('id')->unique()->all();
            $macHistories = [];
            if (count($inventoryItemIds) > 0 && count($headerIds) > 0) {
                // Get unique combinations of outlet_id, warehouse_outlet_id, and dates
                $headerData = $data->keyBy('id');
                $macQueryConditions = [];
                foreach ($details as $detail) {
                    $header = $headerData->get($detail->header_id);
                    if ($header && isset($inventoryItems[$detail->item_id])) {
                        $inventoryItemId = $inventoryItems[$detail->item_id]->id;
                        $key = "{$inventoryItemId}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";
                        if (!isset($macQueryConditions[$key])) {
                            $macQueryConditions[$key] = [
                                'inventory_item_id' => $inventoryItemId,
                                'id_outlet' => $header->outlet_id,
                                'warehouse_outlet_id' => $header->warehouse_outlet_id,
                                'date' => $header->date
                            ];
                        }
                    }
                }
                
                // Batch query MAC histories
                foreach ($macQueryConditions as $condition) {
                    $macRow = DB::table('outlet_food_inventory_cost_histories')
                        ->where('inventory_item_id', $condition['inventory_item_id'])
                        ->where('id_outlet', $condition['id_outlet'])
                        ->where('warehouse_outlet_id', $condition['warehouse_outlet_id'])
                        ->where('date', '<=', $condition['date'])
                        ->orderByDesc('date')
                        ->orderByDesc('id')
                        ->first();
                    if ($macRow) {
                        $macKey = "{$condition['inventory_item_id']}_{$condition['id_outlet']}_{$condition['warehouse_outlet_id']}_{$condition['date']}";
                        $macHistories[$macKey] = $macRow->mac;
                    }
                }
            }
            
            // Calculate totals
            foreach ($details as $item) {
                $mac = null;
                if (isset($inventoryItems[$item->item_id])) {
                    $inventoryItem = $inventoryItems[$item->item_id];
                    $header = $data->firstWhere('id', $item->header_id);
                    if ($header) {
                        $macKey = "{$inventoryItem->id}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";
                        if (isset($macHistories[$macKey])) {
                            $mac = $macHistories[$macKey];
                        }
                    }
                }
                
                $mac_converted = null;
                if ($mac !== null) {
                    $mac_converted = $mac;
                    if ($item->unit_id == $item->medium_unit_id && $item->small_conversion_qty > 0) {
                        $mac_converted = $mac * $item->small_conversion_qty;
                    } elseif ($item->unit_id == $item->large_unit_id && $item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) {
                        $mac_converted = $mac * $item->small_conversion_qty * $item->medium_conversion_qty;
                    }
                }
                $subtotal_mac = ($mac_converted !== null) ? ($mac_converted * $item->qty) : 0;
                $type = $item->header_type;
                if (!isset($totalPerType[$type])) $totalPerType[$type] = 0;
                $totalPerType[$type] += $subtotal_mac;
            }
            
            // Calculate subtotal MAC per header
            $subtotalPerHeader = [];
            foreach ($details as $item) {
                $mac = null;
                if (isset($inventoryItems[$item->item_id])) {
                    $inventoryItem = $inventoryItems[$item->item_id];
                    $header = $data->firstWhere('id', $item->header_id);
                    if ($header) {
                        $macKey = "{$inventoryItem->id}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";
                        if (isset($macHistories[$macKey])) {
                            $mac = $macHistories[$macKey];
                        }
                    }
                }
                
                $mac_converted = null;
                if ($mac !== null) {
                    $mac_converted = $mac;
                    if ($item->unit_id == $item->medium_unit_id && $item->small_conversion_qty > 0) {
                        $mac_converted = $mac * $item->small_conversion_qty;
                    } elseif ($item->unit_id == $item->large_unit_id && $item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) {
                        $mac_converted = $mac * $item->small_conversion_qty * $item->medium_conversion_qty;
                    }
                }
                $subtotal_mac = ($mac_converted !== null) ? ($mac_converted * $item->qty) : 0;
                
                if (!isset($subtotalPerHeader[$item->header_id])) {
                    $subtotalPerHeader[$item->header_id] = 0;
                }
                $subtotalPerHeader[$item->header_id] += $subtotal_mac;
            }
            
            // Add subtotal_mac to each header row
            $data = collect($data)->map(function($row) use ($subtotalPerHeader) {
                $row->subtotal_mac = $subtotalPerHeader[$row->id] ?? 0;
                return $row;
            });
        } else {
            // If no details, set subtotal_mac to 0 for all rows
            $data = collect($data)->map(function($row) {
                $row->subtotal_mac = 0;
                return $row;
            });
        }

        $types = [
            ['value' => '', 'label' => 'Semua'],
            ['value' => 'internal_use', 'label' => 'Internal Use'],
            ['value' => 'spoil', 'label' => 'Spoil'],
            ['value' => 'waste', 'label' => 'Waste'],
            ['value' => 'r_and_d', 'label' => 'R & D'],
            ['value' => 'marketing', 'label' => 'Marketing'],
            ['value' => 'non_commodity', 'label' => 'Non Commodity'],
            ['value' => 'guest_supplies', 'label' => 'Guest Supplies'],
            ['value' => 'wrong_maker', 'label' => 'Wrong Maker'],
            ['value' => 'training', 'label' => 'Training'],
        ];
        
        // Filter warehouse outlets based on selected outlet or user's outlet
        $warehouse_outlets_query = DB::table('warehouse_outlets')->where('status', 'active');
        if ($user->id_outlet == 1) {
            // For superuser, filter by selected outlet if any
            if ($selected_outlet_id) {
                $warehouse_outlets_query->where('outlet_id', $selected_outlet_id);
            }
        } else {
            // For regular user, only show warehouse outlets for their outlet
            $warehouse_outlets_query->where('outlet_id', $user->id_outlet);
        }
        $warehouse_outlets = $warehouse_outlets_query->select('id', 'name', 'outlet_id')->orderBy('name')->get();
        
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();

        return inertia('OutletInternalUseWaste/ReportUniversal', [
            'data' => $data,
            'types' => $types,
            'warehouse_outlets' => $warehouse_outlets,
            'outlets' => $outlets,
            'filters' => $request->only(['type', 'warehouse_outlet_id', 'from', 'to', 'outlet_id']),
            'total_per_type' => $totalPerType,
            'user_outlet_id' => $user->id_outlet,
        ]);
    }

    /**
     * Export Category Cost Outlet Report to Excel
     */
    public function exportReportUniversal(Request $request)
    {
        $user = auth()->user();
        $type = $request->input('type');
        $warehouseOutletId = $request->input('warehouse_outlet_id');
        $outletId = $request->input('outlet_id');
        $from = $request->input('from');
        $to = $request->input('to');
        
        // Validasi tanggal wajib
        if (!$from || !$to) {
            return redirect()->route('outlet-internal-use-waste.report-universal')
                ->with('error', 'Filter tanggal (Dari dan Sampai) wajib diisi untuk export.')
                ->withInput($request->only(['type', 'warehouse_outlet_id', 'from', 'to', 'outlet_id']));
        }
        
        // Validasi: Maksimal range 3 bulan
        $fromDate = \Carbon\Carbon::parse($from);
        $toDate = \Carbon\Carbon::parse($to);
        $diffMonths = $fromDate->diffInMonths($toDate);
        
        if ($diffMonths > 3) {
            return redirect()->route('outlet-internal-use-waste.report-universal')
                ->with('error', 'Range tanggal maksimal 3 bulan. Silakan pilih range yang lebih kecil.')
                ->withInput($request->only(['type', 'warehouse_outlet_id', 'from', 'to', 'outlet_id']));
        }
        
        // Validasi outlet: jika user bukan admin, gunakan outlet user
        if ($user->id_outlet != 1) {
            $outletId = $user->id_outlet;
        }
        
        $export = new \App\Exports\CategoryCostOutletExport(
            $type,
            $warehouseOutletId,
            $outletId,
            $from,
            $to,
            $user->id_outlet
        );
        
        $fileName = 'Category_Cost_Outlet_' . $from . '_' . $to . '.xlsx';
        
        return Excel::download($export, $fileName);
    }

    /**
     * Get detail items for a header (for report expand/collapse)
     */
    public function details($id)
    {
        // Ambil data header
        $header = DB::table('outlet_internal_use_waste_headers')->where('id', $id)->first();
        if (!$header) {
            \Log::debug('DETAILS: Header not found for id ' . $id);
            return response()->json(['details' => []]);
        }
        $details = DB::table('outlet_internal_use_waste_details as d')
            ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'd.unit_id', '=', 'u.id')
            ->select('d.*', 'i.name as item_name', 'u.name as unit_name', 'i.small_unit_id', 'i.medium_unit_id', 'i.large_unit_id', 'i.small_conversion_qty', 'i.medium_conversion_qty')
            ->where('d.header_id', $id)
            ->get();
        \Log::debug('DETAILS: Found ' . count($details) . ' detail(s) for header_id ' . $id);
        \Log::debug('DETAILS: Detail data', $details->toArray());
        $result = [];
        foreach ($details as $item) {
            try {
                // Cari inventory_item_id
                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $item->item_id)
                    ->first();
                \Log::debug('DETAILS: inventory_item_id for item_id ' . $item->item_id . ': ' . ($inventoryItem ? $inventoryItem->id : 'NOT FOUND'));
                $mac = null;
                if ($inventoryItem) {
                    // Ambil MAC terakhir sebelum/tanggal transaksi
                    $macRow = DB::table('outlet_food_inventory_cost_histories')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('id_outlet', $header->outlet_id)
                        ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                        ->where('date', '<=', $header->date)
                        ->orderByDesc('date')
                        ->orderByDesc('id')
                        ->first();
                    if ($macRow) {
                        $mac = $macRow->mac;
                    }
                }
                \Log::debug('DETAILS: MAC for item_id ' . $item->item_id . ': ' . ($mac !== null ? $mac : 'NOT FOUND'));
                // Konversi MAC ke unit yang dipakai user
                $mac_converted = null;
                if ($mac !== null) {
                    // Default: MAC sudah dalam unit kecil
                    $mac_converted = $mac;
                    // Cek unit yang dipakai user
                    if ($item->unit_id == $item->medium_unit_id && $item->small_conversion_qty > 0) {
                        $mac_converted = $mac * $item->small_conversion_qty;
                    } elseif ($item->unit_id == $item->large_unit_id && $item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) {
                        $mac_converted = $mac * $item->small_conversion_qty * $item->medium_conversion_qty;
                    }
                }
                $subtotal_mac = ($mac_converted !== null) ? ($mac_converted * $item->qty) : null;
                \Log::debug('DETAILS: mac_converted=' . $mac_converted . ', subtotal_mac=' . $subtotal_mac);
                $result[] = [
                    ...collect($item)->toArray(),
                    'mac_converted' => $mac_converted,
                    'subtotal_mac' => $subtotal_mac
                ];
            } catch (\Throwable $e) {
                \Log::error('DETAILS: Error processing item_id ' . $item->item_id . ': ' . $e->getMessage());
            }
        }
        \Log::debug('DETAILS: Final result', $result);
        return response()->json(['details' => $result]);
    }

    /**
     * Get approvers for approval flow
     */
    public function getApprovers(Request $request)
    {
        $search = $request->get('search', '');
        
        $users = User::where('users.status', 'A')
            ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where(function($query) use ($search) {
                $query->where('users.nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%")
                      ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
            })
            ->select('users.id', 'users.nama_lengkap as name', 'users.email', 'tbl_data_jabatan.nama_jabatan as jabatan')
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get();
        
        return response()->json(['success' => true, 'users' => $users]);
    }

    /**
     * Approve outlet internal use waste header
     */
    public function approve(Request $request, $id)
    {
        $header = DB::table('outlet_internal_use_waste_headers')->where('id', $id)->first();
        
        if (!$header) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        if ($header->status !== 'SUBMITTED') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya data dengan status SUBMITTED yang dapat di-approve'
            ], 400);
        }

        try {
            DB::beginTransaction();
            $currentApprover = auth()->user();
            
            // Support both 'note', 'comment', and 'notes' parameters
            $note = $request->input('note') ?? $request->input('comment') ?? $request->input('notes');
            
            // Update the approval flow for current approver
            // If approval_flow_id is provided, use it; otherwise find by approver_id
            if ($request->has('approval_flow_id')) {
                $currentApprovalFlow = DB::table('outlet_internal_use_waste_approval_flows')
                    ->where('id', $request->approval_flow_id)
                    ->where('header_id', $id)
                    ->where('status', 'PENDING')
                    ->first();
            } else {
                $currentApprovalFlow = DB::table('outlet_internal_use_waste_approval_flows')
                    ->where('header_id', $id)
                    ->where('approver_id', $currentApprover->id)
                    ->where('status', 'PENDING')
                    ->first();
            }
            
            if (!$currentApprovalFlow) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak untuk approve data ini'
                ], 403);
            }
            
            DB::table('outlet_internal_use_waste_approval_flows')
                ->where('id', $currentApprovalFlow->id)
                ->update([
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                    'comments' => $note,
                    'updated_at' => now(),
                ]);
            
            // Check if there are more approvers pending (need to check if all lower levels are approved)
            $currentLevel = $currentApprovalFlow->approval_level;
            $lowerLevelsPending = DB::table('outlet_internal_use_waste_approval_flows')
                ->where('header_id', $id)
                ->where('approval_level', '<', $currentLevel)
                ->where('status', 'PENDING')
                ->count();
            
            if ($lowerLevelsPending > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tunggu approval dari level yang lebih rendah terlebih dahulu'
                ], 400);
            }
            
            // Check if there are more approvers pending at same or higher level
            $pendingApprovers = DB::table('outlet_internal_use_waste_approval_flows')
                ->where('header_id', $id)
                ->where('status', 'PENDING')
                ->count();
            
            if ($pendingApprovers > 0) {
                // Still have pending approvers, keep status as SUBMITTED
                // Send notification to next approver
                DB::commit();
                $this->sendNotificationToNextApprover($id);
                
                // Activity log APPROVE (partial - masih ada approver lain)
                try {
                    $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $header->outlet_id)->value('nama_outlet') ?? 'Unknown';
                    $warehouseName = DB::table('warehouse_outlets')->where('id', $header->warehouse_outlet_id)->value('name') ?? 'Unknown';
                    $typeLabel = ucfirst(str_replace('_', ' ', $header->type));
                    $approverName = $currentApprover->nama_lengkap ?? $currentApprover->email ?? 'Unknown';
                    
                    DB::table('activity_logs')->insert([
                        'user_id' => $currentApprover->id,
                        'activity_type' => 'approve',
                        'module' => 'outlet_internal_use_waste',
                        'description' => "Approve Category Cost Outlet (Partial): {$typeLabel} - {$outletName} ({$warehouseName}) oleh {$approverName}",
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'old_data' => json_encode(['status' => 'SUBMITTED', 'approval_level' => $currentLevel]),
                        'new_data' => json_encode(['status' => 'SUBMITTED', 'approval_level' => $currentLevel, 'approved_by' => $currentApprover->id, 'pending_approvers' => $pendingApprovers]),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                } catch (\Exception $logError) {
                    \Log::warning('OutletInternalUseWaste approve - Activity log failed:', ['error' => $logError->getMessage()]);
                }
                
                $message = 'Approval berhasil! Notifikasi dikirim ke approver berikutnya.';
            } else {
                // All approvers have approved, process stock and update status
                \Log::info("OutletInternalUseWaste approve: All approvers approved, processing stock", [
                    'header_id' => $id,
                    'type' => $header->type,
                    'outlet_id' => $header->outlet_id
                ]);
                
                $this->processStockAfterApproval($id);
                
                DB::table('outlet_internal_use_waste_headers')
                    ->where('id', $id)
                    ->update([
                        'status' => 'APPROVED',
                        'updated_at' => now(),
                    ]);
                
                DB::commit();
                
                \Log::info("OutletInternalUseWaste approve: Stock processing and status update completed", [
                    'header_id' => $id,
                    'type' => $header->type,
                    'status' => 'APPROVED'
                ]);
                
                // Activity log APPROVE (complete - semua approver sudah approve)
                try {
                    $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $header->outlet_id)->value('nama_outlet') ?? 'Unknown';
                    $warehouseName = DB::table('warehouse_outlets')->where('id', $header->warehouse_outlet_id)->value('name') ?? 'Unknown';
                    $typeLabel = ucfirst(str_replace('_', ' ', $header->type));
                    $approverName = $currentApprover->nama_lengkap ?? $currentApprover->email ?? 'Unknown';
                    
                    DB::table('activity_logs')->insert([
                        'user_id' => $currentApprover->id,
                        'activity_type' => 'approve',
                        'module' => 'outlet_internal_use_waste',
                        'description' => "Approve Category Cost Outlet (Complete): {$typeLabel} - {$outletName} ({$warehouseName}) oleh {$approverName}",
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'old_data' => json_encode(['status' => 'SUBMITTED', 'approval_level' => $currentLevel]),
                        'new_data' => json_encode(['status' => 'APPROVED', 'approval_level' => $currentLevel, 'approved_by' => $currentApprover->id, 'stock_processed' => true]),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                } catch (\Exception $logError) {
                    \Log::warning('OutletInternalUseWaste approve - Activity log failed:', ['error' => $logError->getMessage()]);
                }
                
                $message = 'Semua approval telah selesai! Stock telah diproses.';
            }
            
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error approving outlet internal use waste: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses approval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject outlet internal use waste header
     */
    public function reject(Request $request, $id)
    {
        $header = DB::table('outlet_internal_use_waste_headers')->where('id', $id)->first();
        
        if (!$header) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        if ($header->status !== 'SUBMITTED') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya data dengan status SUBMITTED yang dapat ditolak'
            ], 400);
        }

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
            'reason' => 'nullable|string|max:500', // Alias for rejection_reason
            'comment' => 'nullable|string|max:500', // Alias for rejection_reason
        ]);
        
        // Support 'rejection_reason', 'reason', and 'comment' parameters
        $rejectionReason = $request->input('rejection_reason') ?? $request->input('reason') ?? $request->input('comment');
        
        if (!$rejectionReason) {
            return response()->json([
                'success' => false,
                'message' => 'Alasan penolakan harus diisi'
            ], 400);
        }

        try {
            DB::beginTransaction();
            $currentApprover = auth()->user();
            
            // Update the approval flow for current approver
            // If approval_flow_id is provided, use it; otherwise find by approver_id
            if ($request->has('approval_flow_id')) {
                $currentApprovalFlow = DB::table('outlet_internal_use_waste_approval_flows')
                    ->where('id', $request->approval_flow_id)
                    ->where('header_id', $id)
                    ->where('status', 'PENDING')
                    ->first();
            } else {
                $currentApprovalFlow = DB::table('outlet_internal_use_waste_approval_flows')
                    ->where('header_id', $id)
                    ->where('approver_id', $currentApprover->id)
                    ->where('status', 'PENDING')
                    ->first();
            }
            
            if (!$currentApprovalFlow) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak untuk menolak data ini'
                ], 403);
            }
            
            DB::table('outlet_internal_use_waste_approval_flows')
                ->where('id', $currentApprovalFlow->id)
                ->update([
                    'status' => 'REJECTED',
                    'rejected_at' => now(),
                    'comments' => $rejectionReason,
                    'updated_at' => now(),
                ]);
            
            // Update header status to REJECTED
            DB::table('outlet_internal_use_waste_headers')
                ->where('id', $id)
                ->update([
                    'status' => 'REJECTED',
                    'updated_at' => now(),
                ]);
            
            DB::commit();
            
            // Activity log REJECT
            try {
                $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $header->outlet_id)->value('nama_outlet') ?? 'Unknown';
                $warehouseName = DB::table('warehouse_outlets')->where('id', $header->warehouse_outlet_id)->value('name') ?? 'Unknown';
                $typeLabel = ucfirst(str_replace('_', ' ', $header->type));
                $approverName = $currentApprover->nama_lengkap ?? $currentApprover->email ?? 'Unknown';
                
                DB::table('activity_logs')->insert([
                    'user_id' => $currentApprover->id,
                    'activity_type' => 'reject',
                    'module' => 'outlet_internal_use_waste',
                    'description' => "Reject Category Cost Outlet: {$typeLabel} - {$outletName} ({$warehouseName}) oleh {$approverName}",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => json_encode(['status' => 'SUBMITTED', 'approval_level' => $currentApprovalFlow->approval_level]),
                    'new_data' => json_encode([
                        'status' => 'REJECTED',
                        'approval_level' => $currentApprovalFlow->approval_level,
                        'rejected_by' => $currentApprover->id,
                        'rejection_reason' => $rejectionReason
                    ]),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $logError) {
                \Log::warning('OutletInternalUseWaste reject - Activity log failed:', ['error' => $logError->getMessage()]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil ditolak'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error rejecting outlet internal use waste: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses penolakan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process stock after all approvals are complete
     */
    private function processStockAfterApproval($headerId)
    {
        $header = DB::table('outlet_internal_use_waste_headers')->where('id', $headerId)->first();
        if (!$header) {
            throw new \Exception('Header not found');
        }
        
        \Log::info("OutletInternalUseWaste processStockAfterApproval: Starting stock processing", [
            'header_id' => $headerId,
            'type' => $header->type,
            'outlet_id' => $header->outlet_id,
            'warehouse_outlet_id' => $header->warehouse_outlet_id
        ]);
        
        $details = DB::table('outlet_internal_use_waste_details')->where('header_id', $headerId)->get();
        
        if ($details->isEmpty()) {
            \Log::warning("OutletInternalUseWaste processStockAfterApproval: No details found for header_id: {$headerId}, type: {$header->type}");
            return;
        }
        
        \Log::info("OutletInternalUseWaste processStockAfterApproval: Found {$details->count()} detail items", [
            'header_id' => $headerId,
            'type' => $header->type
        ]);
        
        // First pass: Check all stock availability
        $insufficientStockItems = [];
        
        foreach ($details as $item) {
            $inventoryItem = DB::table('outlet_food_inventory_items')
                ->where('item_id', $item->item_id)
                ->first();
            if (!$inventoryItem) {
                \Log::warning("OutletInternalUseWaste processStockAfterApproval: Inventory item not found for item_id: {$item->item_id}, header_id: {$headerId}, type: {$header->type}");
                continue;
            }
            
            $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
            if (!$itemMaster) {
                \Log::warning("OutletInternalUseWaste processStockAfterApproval: Item master not found for item_id: {$item->item_id}, header_id: {$headerId}");
                continue;
            }
            
            $unit = DB::table('units')->where('id', $item->unit_id)->value('name');
            $qty_input = $item->qty;
            $qty_small = 0;
            $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
            
            if ($unit === $unitSmall) {
                $qty_small = $qty_input;
            } elseif ($unit === DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name')) {
                $qty_small = $qty_input * $smallConv;
            } elseif ($unit === DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name')) {
                $qty_small = $qty_input * $smallConv * $mediumConv;
            } else {
                $qty_small = $qty_input;
            }
            
            $stock = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventoryItem->id)
                ->where('id_outlet', $header->outlet_id)
                ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                ->first();
            
            if (!$stock) {
                \Log::warning("OutletInternalUseWaste processStockAfterApproval: Stock not found for inventory_item_id: {$inventoryItem->id}, outlet_id: {$header->outlet_id}, warehouse_outlet_id: {$header->warehouse_outlet_id}, header_id: {$headerId}, type: {$header->type}");
                continue;
            }
            
            // Check stock availability
            if ($qty_small > $stock->qty_small) {
                $itemName = $itemMaster->name ?? "Item ID: {$item->item_id}";
                $insufficientStockItems[] = [
                    'name' => $itemName,
                    'required' => $qty_small,
                    'available' => $stock->qty_small,
                    'unit' => $unitSmall
                ];
            }
        }
        
        // If any items have insufficient stock, throw error with all details
        if (!empty($insufficientStockItems)) {
            $errorMessage = "Stock tidak mencukupi untuk item berikut:\n\n";
            foreach ($insufficientStockItems as $stockItem) {
                $errorMessage .= "• {$stockItem['name']}\n";
                $errorMessage .= "  Dibutuhkan: {$stockItem['required']} {$stockItem['unit']}\n";
                $errorMessage .= "  Tersedia: {$stockItem['available']} {$stockItem['unit']}\n\n";
            }
            throw new \Exception($errorMessage);
        }
        
        // Second pass: Update all stocks (only if all checks passed)
        foreach ($details as $item) {
            $inventoryItem = DB::table('outlet_food_inventory_items')
                ->where('item_id', $item->item_id)
                ->first();
            if (!$inventoryItem) {
                \Log::warning("OutletInternalUseWaste processStockAfterApproval: Inventory item not found for item_id: {$item->item_id}, header_id: {$headerId}, type: {$header->type}");
                continue;
            }
            
            $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
            if (!$itemMaster) {
                \Log::warning("OutletInternalUseWaste processStockAfterApproval: Item master not found for item_id: {$item->item_id}, header_id: {$headerId}");
                continue;
            }
            
            $unit = DB::table('units')->where('id', $item->unit_id)->value('name');
            $qty_input = $item->qty;
            $qty_small = 0;
            $qty_medium = 0;
            $qty_large = 0;
            $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
            $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
            $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
            
            if ($unit === $unitSmall) {
                $qty_small = $qty_input;
                $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
            } elseif ($unit === $unitMedium) {
                $qty_medium = $qty_input;
                $qty_small = $qty_medium * $smallConv;
                $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
            } elseif ($unit === $unitLarge) {
                $qty_large = $qty_input;
                $qty_medium = $qty_large * $mediumConv;
                $qty_small = $qty_medium * $smallConv;
            } else {
                $qty_small = $qty_input;
            }
            
            $stock = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventoryItem->id)
                ->where('id_outlet', $header->outlet_id)
                ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                ->first();
            
            if (!$stock) {
                \Log::warning("OutletInternalUseWaste processStockAfterApproval: Stock not found for inventory_item_id: {$inventoryItem->id}, outlet_id: {$header->outlet_id}, warehouse_outlet_id: {$header->warehouse_outlet_id}, header_id: {$headerId}, type: {$header->type}");
                continue;
            }
            
            // Update stok di outlet (kurangi) - Stock check already done in first pass
            $updated = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventoryItem->id)
                ->where('id_outlet', $header->outlet_id)
                ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                ->update([
                    'qty_small' => $stock->qty_small - $qty_small,
                    'qty_medium' => $stock->qty_medium - $qty_medium,
                    'qty_large' => $stock->qty_large - $qty_large,
                    'updated_at' => now(),
                ]);
            
            if ($updated === 0) {
                $itemName = $itemMaster->name ?? $item->item_id;
                \Log::error("OutletInternalUseWaste processStockAfterApproval: Failed to update stock for inventory_item_id: {$inventoryItem->id}, outlet_id: {$header->outlet_id}, warehouse_outlet_id: {$header->warehouse_outlet_id}, header_id: {$headerId}, type: {$header->type}");
                throw new \Exception("Gagal mengupdate stock untuk item: {$itemName}");
            }
            
            \Log::info("OutletInternalUseWaste processStockAfterApproval: Stock updated successfully", [
                'header_id' => $headerId,
                'type' => $header->type,
                'item_id' => $item->item_id,
                'inventory_item_id' => $inventoryItem->id,
                'outlet_id' => $header->outlet_id,
                'warehouse_outlet_id' => $header->warehouse_outlet_id,
                'qty_small_reduced' => $qty_small,
                'old_qty_small' => $stock->qty_small,
                'new_qty_small' => $stock->qty_small - $qty_small
            ]);
            
            // Insert kartu stok OUT
            DB::table('outlet_food_inventory_cards')->insert([
                'inventory_item_id' => $inventoryItem->id,
                'id_outlet' => $header->outlet_id,
                'warehouse_outlet_id' => $header->warehouse_outlet_id,
                'date' => $header->date,
                'reference_type' => 'outlet_internal_use_waste',
                'reference_id' => $headerId,
                'out_qty_small' => $qty_small,
                'out_qty_medium' => $qty_medium,
                'out_qty_large' => $qty_large,
                'cost_per_small' => $stock->last_cost_small,
                'cost_per_medium' => $stock->last_cost_medium,
                'cost_per_large' => $stock->last_cost_large,
                'value_out' => $qty_small * $stock->last_cost_small,
                'saldo_qty_small' => $stock->qty_small - $qty_small,
                'saldo_qty_medium' => $stock->qty_medium - $qty_medium,
                'saldo_qty_large' => $stock->qty_large - $qty_large,
                'saldo_value' => ($stock->qty_small - $qty_small) * $stock->last_cost_small,
                'description' => 'Stock Out - ' . $header->type . ' (After Approval)',
                'created_at' => now(),
            ]);
        }
        
        \Log::info("OutletInternalUseWaste processStockAfterApproval: Stock processing completed", [
            'header_id' => $headerId,
            'type' => $header->type,
            'total_details_processed' => $details->count()
        ]);
    }

    /**
     * Send notification to the next approver in line
     */
    private function sendNotificationToNextApprover($headerId)
    {
        try {
            // Get the lowest level approver that is still pending
            $nextApprover = DB::table('outlet_internal_use_waste_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->where('af.header_id', $headerId)
                ->where('af.status', 'PENDING')
                ->orderBy('af.approval_level')
                ->select('u.id', 'u.nama_lengkap', 'u.email')
                ->first();

            if (!$nextApprover) {
                return; // No pending approvers
            }

            // Get header details
            $header = DB::table('outlet_internal_use_waste_headers as h')
                ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
                ->join('users as creator', 'h.created_by', '=', 'creator.id')
                ->where('h.id', $headerId)
                ->select('h.*', 'o.nama_outlet', 'creator.nama_lengkap as creator_name')
                ->first();

            if (!$header) {
                return;
            }

            // Create notification
            NotificationService::insert([
                'user_id' => $nextApprover->id,
                'type' => 'outlet_internal_use_waste_approval',
                'title' => 'Approval Category Cost Outlet',
                'message' => "Category Cost Outlet dengan tipe {$header->type} dari outlet {$header->nama_outlet} oleh {$header->creator_name} menunggu approval Anda.",
                'is_read' => 0,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error sending notification to next approver: ' . $e->getMessage());
        }
    }

    /**
     * Get pending approvals for current user
     */
    public function getPendingApprovals(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: User not authenticated',
                'headers' => []
            ], 401);
        }
        
        // Superadmin: user dengan id_role = '5af56935b011a' bisa melihat semua approval
        $isSuperadmin = $currentUser->id_role === '5af56935b011a';
        
        // Get all headers that have pending approval for current user
        // Only show if all lower level approvals are done
        $query = DB::table('outlet_internal_use_waste_headers as h')
            ->join('outlet_internal_use_waste_approval_flows as af', 'h.id', '=', 'af.header_id')
            ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->join('users as creator', 'h.created_by', '=', 'creator.id')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->where('af.status', 'PENDING')
            ->where('h.status', 'SUBMITTED');
        
        // Superadmin can see all pending approvals, regular users only their own
        if (!$isSuperadmin) {
            $query->where('af.approver_id', $currentUser->id);
        }
        
        $pendingHeaders = $query
            ->leftJoin('users as approver', 'af.approver_id', '=', 'approver.id')
            ->select(
                'h.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'creator.nama_lengkap as creator_name',
                'af.approval_level',
                'approver.nama_lengkap as approver_name'
            )
            ->get()
            ->map(function($header) {
                // If approver_name is null, get it from the next pending approval flow
                if (!$header->approver_name) {
                    $nextFlow = DB::table('outlet_internal_use_waste_approval_flows as af')
                        ->leftJoin('users as u', 'af.approver_id', '=', 'u.id')
                        ->where('af.header_id', $header->id)
                        ->where('af.status', 'PENDING')
                        ->orderBy('af.approval_level', 'asc')
                        ->select('u.nama_lengkap as approver_name')
                        ->first();
                    $header->approver_name = $nextFlow->approver_name ?? null;
                }
                return $header;
            })
            ->filter(function($header) use ($currentUser, $isSuperadmin) {
                // Superadmin can see all pending approvals
                if ($isSuperadmin) {
                    return true;
                }
                
                // Check if all lower level approvals are done
                $lowerLevelsPending = DB::table('outlet_internal_use_waste_approval_flows')
                    ->where('header_id', $header->id)
                    ->where('approval_level', '<', $header->approval_level)
                    ->where('status', 'PENDING')
                    ->count();
                
                return $lowerLevelsPending === 0;
            })
            ->values();
        
        return response()->json([
            'success' => true,
            'headers' => $pendingHeaders
        ]);
    }

    /**
     * Get approval details for a header (for modal display)
     */
    public function getApprovalDetails($id)
    {
        $header = DB::table('outlet_internal_use_waste_headers as h')
            ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->join('users as creator', 'h.created_by', '=', 'creator.id')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->where('h.id', $id)
            ->select(
                'h.*',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'creator.nama_lengkap as creator_name'
            )
            ->first();
        
        if (!$header) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }
        
        $details = DB::table('outlet_internal_use_waste_details as d')
            ->join('items as i', 'd.item_id', '=', 'i.id')
            ->join('units as u', 'd.unit_id', '=', 'u.id')
            ->where('d.header_id', $id)
            ->select('d.*', 'i.name as item_name', 'u.name as unit_name')
            ->get();
        
        $approvalFlows = DB::table('outlet_internal_use_waste_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('af.header_id', $id)
            ->select(
                'af.*',
                'u.nama_lengkap as approver_name',
                'u.email as approver_email',
                'j.nama_jabatan as approver_jabatan'
            )
            ->orderBy('af.approval_level')
            ->get();
        
        // Get current approver (pending approver for current user)
        $currentApprover = null;
        $currentUserId = auth()->id();
        if ($currentUserId) {
            $currentApprover = DB::table('outlet_internal_use_waste_approval_flows')
                ->where('header_id', $id)
                ->where('approver_id', $currentUserId)
                ->where('status', 'PENDING')
                ->first();
        }
        
        return response()->json([
            'success' => true,
            'header' => $header,
            'details' => $details,
            'approval_flows' => $approvalFlows,
            'current_approval_flow_id' => $currentApprover ? $currentApprover->id : null,
        ]);
    }
} 