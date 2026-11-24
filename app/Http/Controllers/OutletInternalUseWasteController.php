<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\CategoryCostMacResolver;
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
                'u.nama_lengkap as creator_name',
                'u.avatar as creator_avatar'
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

        // subtotal_mac now stored on header for faster index response
        
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
        if (!$user) {
            return redirect()->route('login')->with('error', 'Sesi login berakhir. Silakan login kembali.');
        }

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
                'header_id' => 'nullable|integer',
                'type' => 'required|in:internal_use,spoil,waste,usage,r_and_d,marketing,non_commodity,guest_supplies,wrong_maker,training',
                'date' => 'required|date',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'warehouse_outlet_id' => 'required|exists:warehouse_outlets,id',
                'notes' => 'nullable|string',
                'items' => $itemsRequired . '|array|' . $itemsMin,
                'items.*.item_id' => 'required_with:items|exists:items,id',
                'items.*.qty' => 'required_with:items|numeric|min:0',
                'items.*.unit_id' => 'required_with:items|exists:units,id',
                'items.*.note' => 'nullable|string',
                'items.*.line_mode' => 'nullable|in:material,menu',
                'items.*.modifier_option_ids' => 'nullable|array',
                'items.*.modifier_option_ids.*' => 'integer|exists:modifier_options,id',
                'items.*.stock_on_hand' => 'nullable|numeric|min:0',
                'items.*.physical_qty' => 'nullable|numeric|min:0',
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
            
            $headerId = null;

            // If header_id provided, update that specific draft
            if ($request->filled('header_id')) {
                $headerId = (int) $request->header_id;
                $existingHeader = DB::table('outlet_internal_use_waste_headers')
                    ->where('id', $headerId)
                    ->where('created_by', $userId)
                    ->where('status', 'DRAFT')
                    ->lockForUpdate()
                    ->first();

                if (!$existingHeader) {
                    throw new \Exception('Draft tidak ditemukan atau sudah tidak bisa diedit.');
                }

                \Log::info('OutletInternalUseWaste store - Updating by header_id', [
                    'header_id' => $headerId
                ]);

                DB::table('outlet_internal_use_waste_headers')
                    ->where('id', $headerId)
                    ->update([
                        'type' => $request->type,
                        'date' => $request->date,
                        'outlet_id' => $request->outlet_id,
                        'warehouse_outlet_id' => $request->warehouse_outlet_id,
                        'notes' => $request->notes,
                        'status' => $status,
                        'updated_at' => now()
                    ]);

                DB::table('outlet_internal_use_waste_details')->where('header_id', $headerId)->delete();
                DB::table('outlet_internal_use_waste_approval_flows')->where('header_id', $headerId)->delete();
            }

            // Cek apakah sudah ada draft untuk outlet, warehouse, type, dan user yang sama
            // Mirip dengan Food Floor Order: cari draft berdasarkan kombinasi outlet_id, warehouse_outlet_id, type, dan user
            // Gunakan lockForUpdate untuk mencegah race condition saat multiple requests datang bersamaan
            $existingHeader = null;
            if ($headerId === null) {
                $existingHeader = DB::table('outlet_internal_use_waste_headers')
                    ->where('outlet_id', $request->outlet_id)
                    ->where('warehouse_outlet_id', $request->warehouse_outlet_id)
                    ->where('type', $request->type)
                    ->where('created_by', $userId)
                    ->where('status', 'DRAFT')
                    ->lockForUpdate()
                    ->first();
            }
            
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
            } else if ($headerId === null) {
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
                    if (empty($item['item_id'])) {
                        throw new \Exception('Item ID tidak boleh kosong untuk item ke-'.($itemIndex + 1));
                    }
                    if (empty($item['qty']) || $item['qty'] <= 0) {
                        throw new \Exception('Quantity harus lebih dari 0 untuk item ke-'.($itemIndex + 1));
                    }
                    if (empty($item['unit_id'])) {
                        throw new \Exception('Unit ID tidak boleh kosong untuk item ke-'.($itemIndex + 1));
                    }

                    $itemMaster = DB::table('items')->where('id', $item['item_id'])->first();
                    if (!$itemMaster) {
                        throw new \Exception('Item master tidak ditemukan untuk item ke-'.($itemIndex + 1));
                    }

                    $unit = DB::table('units')->where('id', $item['unit_id'])->value('name');
                    if (!$unit) {
                        throw new \Exception('Unit tidak ditemukan untuk item ke-'.($itemIndex + 1));
                    }

                    $lineMode = $this->normalizeLineMode($item['line_mode'] ?? null, $request->type);

                    if ($lineMode === 'menu') {
                        $bomCount = DB::table('item_bom')
                            ->where('item_id', $item['item_id'])
                            ->where('stock_cut', 1)
                            ->count();
                        if ($bomCount === 0) {
                            $itemName = $itemMaster->name ?? 'Unknown';
                            throw new \Exception("Menu \"{$itemName}\" tidak punya BOM stock_cut untuk item ke-".($itemIndex + 1));
                        }
                    } else {
                        $inventoryItem = DB::table('outlet_food_inventory_items')
                            ->where('item_id', $item['item_id'])
                            ->first();
                        if (!$inventoryItem) {
                            $itemName = $itemMaster->name ?? 'Unknown';
                            throw new \Exception("Item \"{$itemName}\" tidak ditemukan di inventory untuk item ke-".($itemIndex + 1));
                        }

                        $stock = DB::table('outlet_food_inventory_stocks')
                            ->where('inventory_item_id', $inventoryItem->id)
                            ->where('id_outlet', $request->outlet_id)
                            ->where('warehouse_outlet_id', $request->warehouse_outlet_id)
                            ->first();
                        if (!$stock) {
                            $itemName = $itemMaster->name ?? 'Unknown';
                            throw new \Exception("Stok item \"{$itemName}\" tidak ditemukan di outlet dan warehouse outlet yang dipilih untuk item ke-".($itemIndex + 1));
                        }
                        if ($stock->qty_small <= 0) {
                            $itemName = $itemMaster->name ?? 'Unknown';
                            throw new \Exception("Stok item \"{$itemName}\" tidak tersedia (stock: 0). Tidak dapat menyimpan draft untuk item ke-".($itemIndex + 1));
                        }
                    }

                    $detailInserted = DB::table('outlet_internal_use_waste_details')->insert(
                        $this->buildDetailInsertRow($headerId, $item, $request->type)
                    );

                    if (!$detailInserted) {
                        throw new \Exception('Gagal menyimpan detail item ke-'.($itemIndex + 1));
                    }
                } catch (\Exception $itemError) {
                    throw new \Exception('Error pada item ke-'.($itemIndex + 1).': '.$itemError->getMessage());
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
            
            $cuts = $this->expandDetailsToMaterialCuts($header, $details);
            // SUBMITTED: validasi stok BOM/material saja; PROCESSED: potong + kartu
            $subtotalMac = $this->applyMaterialStockCuts(
                $header,
                (int) $id,
                $cuts,
                'Stock Out - '.$header->type,
                $newStatus === 'PROCESSED'
            );
            
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
                'subtotal_mac' => $subtotalMac,
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
                'type' => 'required|in:internal_use,spoil,waste,usage,r_and_d,marketing,non_commodity,guest_supplies,wrong_maker,training',
                'date' => 'required|date',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'warehouse_outlet_id' => 'required|exists:warehouse_outlets,id',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.item_id' => 'required|exists:items,id',
                'items.*.qty' => 'required|numeric|min:0',
                'items.*.unit_id' => 'required|exists:units,id',
                'items.*.note' => 'nullable|string',
                'items.*.line_mode' => 'nullable|in:material,menu',
                'items.*.modifier_option_ids' => 'nullable|array',
                'items.*.modifier_option_ids.*' => 'integer|exists:modifier_options,id',
                'items.*.stock_on_hand' => 'nullable|numeric|min:0',
                'items.*.physical_qty' => 'nullable|numeric|min:0',
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
            
            foreach ($request->items as $itemIndex => $item) {
                try {
                    $lineMode = $this->normalizeLineMode($item['line_mode'] ?? null, $request->type);
                    if ($lineMode === 'menu') {
                        $bomCount = DB::table('item_bom')
                            ->where('item_id', $item['item_id'])
                            ->where('stock_cut', 1)
                            ->count();
                        if ($bomCount === 0) {
                            $itemName = DB::table('items')->where('id', $item['item_id'])->value('name') ?? 'Unknown';
                            throw new \Exception("Menu \"{$itemName}\" tidak punya BOM stock_cut");
                        }
                    }

                    DB::table('outlet_internal_use_waste_details')->insert(
                        $this->buildDetailInsertRow($headerId, $item, $request->type)
                    );
                } catch (\Exception $itemError) {
                    throw new \Exception('Error pada item ke-'.($itemIndex + 1).': '.$itemError->getMessage());
                }
            }

            $header = DB::table('outlet_internal_use_waste_headers')->where('id', $headerId)->first();
            $details = DB::table('outlet_internal_use_waste_details')->where('header_id', $headerId)->get();
            $cuts = $this->expandDetailsToMaterialCuts($header, $details);
            $subtotalMac = $this->applyMaterialStockCuts(
                $header,
                (int) $headerId,
                $cuts,
                'Stock Out - '.ucfirst(str_replace('_', ' ', $request->type)),
                $newStatus === 'PROCESSED'
            );

            DB::table('outlet_internal_use_waste_headers')
                ->where('id', $headerId)
                ->update([
                    'subtotal_mac' => $subtotalMac,
                    'updated_at' => now()
                ]);

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
            ->get()
            ->map(function ($row) {
                $row->modifier_option_ids = $this->parseModifierOptionIds($row->modifier_option_ids ?? null);
                $row->modifier_labels = $this->resolveModifierOptionLabels($row->modifier_option_ids);
                return $row;
            });
        
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

    public function show($id, Request $request)
    {
        $header = DB::table('outlet_internal_use_waste_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->select('h.*', 'o.nama_outlet as outlet_name', 'wo.name as warehouse_outlet_name')
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
            ->get()
            ->map(function ($row) use ($header) {
                $lineMode = $this->normalizeLineMode($row->line_mode ?? 'material', (string) $header->type);
                $row->line_mode = $lineMode;
                $modifierOptionIds = $this->parseModifierOptionIds($row->modifier_option_ids ?? null);
                $row->modifier_option_ids = $modifierOptionIds;
                $row->modifier_labels = $this->resolveModifierOptionLabels($modifierOptionIds);
                $row->bom_preview = $lineMode === 'menu'
                    ? $this->previewMenuBomLines((int) $row->item_id, (float) $row->qty, $modifierOptionIds)
                    : [];
                return $row;
            });

        if ($request->wantsJson() || $request->header('Accept') == 'application/json') {
            return response()->json([
                'data' => $header,
                'items' => $details,
            ]);
        }

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

            // Rollback jika ada kartu OUT (PROCESSED/APPROVED, atau legacy bug SUBMITTED yang sudah motong).
            // Tanpa kartu: jangan rollback — phantom IN.
            $hasStockCards = $this->hasOutletInternalUseWasteStockCards((int) $id);
            $shouldRollbackStock = $hasStockCards || $header->status === 'PROCESSED';
            
            \Log::info('OutletInternalUseWaste destroy - Starting delete', [
                'header_id' => $id,
                'status' => $header->status,
                'has_stock_cards' => $hasStockCards,
                'should_rollback_stock' => $shouldRollbackStock,
                'details_count' => $details->count()
            ]);

            // Rollback via kartu OUT (aman untuk baris material & menu/BOM)
            if ($shouldRollbackStock) {
                $this->rollbackStockFromCards($header, (int) $id);
            } else {
                \Log::info('OutletInternalUseWaste destroy - Skipping stock rollback', [
                    'header_id' => $id,
                    'status' => $header->status
                ]);
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

    public function getStock(Request $request)
    {
        $request->validate([
            'item_id' => 'required|integer',
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer'
        ]);

        $inventoryItem = DB::table('outlet_food_inventory_items')
            ->where('item_id', $request->item_id)
            ->first();

        if (!$inventoryItem) {
            $itemName = DB::table('items')->where('id', $request->item_id)->value('name') ?? 'Unknown';
            return response()->json([
                'success' => false,
                'message' => "Item '{$itemName}' tidak ditemukan di inventory"
            ], 404);
        }

        $stock = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('id_outlet', $request->outlet_id)
            ->where('warehouse_outlet_id', $request->warehouse_outlet_id)
            ->first();

        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => 'Stok item tidak ditemukan untuk outlet/warehouse yang dipilih'
            ], 404);
        }

        $item = DB::table('items')->where('id', $request->item_id)->first();
        $unitSmall = $item?->small_unit_id ? DB::table('units')->where('id', $item->small_unit_id)->value('name') : null;
        $unitMedium = $item?->medium_unit_id ? DB::table('units')->where('id', $item->medium_unit_id)->value('name') : null;
        $unitLarge = $item?->large_unit_id ? DB::table('units')->where('id', $item->large_unit_id)->value('name') : null;

        return response()->json([
            'success' => true,
            'qty_small' => $stock->qty_small ?? 0,
            'qty_medium' => $stock->qty_medium ?? 0,
            'qty_large' => $stock->qty_large ?? 0,
            'unit_small' => $unitSmall,
            'unit_medium' => $unitMedium,
            'unit_large' => $unitLarge,
        ]);
    }

    public function getStockCutItems(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'warehouse_outlet_id' => 'required|integer|exists:warehouse_outlets,id',
        ]);

        // 1) Material dari item_bom (existing behavior untuk Usage)
        $itemBomRows = DB::table('item_bom as ib')
            ->join('items as i', 'i.id', '=', 'ib.material_item_id')
            ->join('outlet_food_inventory_items as ofii', 'ofii.item_id', '=', 'i.id')
            ->join('outlet_food_inventory_stocks as ofs', function ($join) use ($request) {
                $join->on('ofs.inventory_item_id', '=', 'ofii.id')
                    ->where('ofs.id_outlet', '=', $request->outlet_id)
                    ->where('ofs.warehouse_outlet_id', '=', $request->warehouse_outlet_id);
            })
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->leftJoin('units as us', 'us.id', '=', 'i.small_unit_id')
            ->leftJoin('units as um', 'um.id', '=', 'i.medium_unit_id')
            ->leftJoin('units as ul', 'ul.id', '=', 'i.large_unit_id')
            ->where('ib.stock_cut', 1)
            ->where('i.status', 'active')
            ->where(function ($query) {
                $query->where('ofs.qty_small', '>', 0)
                    ->orWhere('ofs.qty_medium', '>', 0)
                    ->orWhere('ofs.qty_large', '>', 0);
            })
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'c.name as category_name',
                'sc.name as sub_category_name',
                'i.small_unit_id',
                'us.name as unit_small',
                'i.medium_unit_id',
                'um.name as unit_medium',
                'i.large_unit_id',
                'ul.name as unit_large',
                'ofs.qty_small',
                'ofs.qty_medium',
                'ofs.qty_large'
            )
            ->get();

        // 2) Material dari modifier_options.modifier_bom_json dengan stock_cut=true
        $toBool = function ($value): bool {
            return $value === true || $value === 1 || $value === '1' || $value === 'true';
        };

        $modifierMaterialItemIds = [];
        $modifierOptions = DB::table('modifier_options')
            ->whereNotNull('modifier_bom_json')
            ->where('modifier_bom_json', '!=', '')
            ->where('modifier_bom_json', '!=', '[]')
            ->select('modifier_bom_json')
            ->get();

        foreach ($modifierOptions as $modifierOption) {
            $bomRows = json_decode((string) $modifierOption->modifier_bom_json, true);
            if (!is_array($bomRows)) {
                continue;
            }
            foreach ($bomRows as $bomRow) {
                if (!is_array($bomRow)) {
                    continue;
                }
                if (!$toBool($bomRow['stock_cut'] ?? false)) {
                    continue;
                }
                $materialItemId = isset($bomRow['item_id']) ? (int) $bomRow['item_id'] : 0;
                if ($materialItemId > 0) {
                    $modifierMaterialItemIds[] = $materialItemId;
                }
            }
        }

        $modifierMaterialItemIds = array_values(array_unique($modifierMaterialItemIds));

        $modifierBomRows = collect();
        if (!empty($modifierMaterialItemIds)) {
            $modifierBomRows = DB::table('items as i')
                ->join('outlet_food_inventory_items as ofii', 'ofii.item_id', '=', 'i.id')
                ->join('outlet_food_inventory_stocks as ofs', function ($join) use ($request) {
                    $join->on('ofs.inventory_item_id', '=', 'ofii.id')
                        ->where('ofs.id_outlet', '=', $request->outlet_id)
                        ->where('ofs.warehouse_outlet_id', '=', $request->warehouse_outlet_id);
                })
                ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
                ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
                ->leftJoin('units as us', 'us.id', '=', 'i.small_unit_id')
                ->leftJoin('units as um', 'um.id', '=', 'i.medium_unit_id')
                ->leftJoin('units as ul', 'ul.id', '=', 'i.large_unit_id')
                ->whereIn('i.id', $modifierMaterialItemIds)
                ->where('i.status', 'active')
                ->where(function ($query) {
                    $query->where('ofs.qty_small', '>', 0)
                        ->orWhere('ofs.qty_medium', '>', 0)
                        ->orWhere('ofs.qty_large', '>', 0);
                })
                ->select(
                    'i.id as item_id',
                    'i.name as item_name',
                    'c.name as category_name',
                    'sc.name as sub_category_name',
                    'i.small_unit_id',
                    'us.name as unit_small',
                    'i.medium_unit_id',
                    'um.name as unit_medium',
                    'i.large_unit_id',
                    'ul.name as unit_large',
                    'ofs.qty_small',
                    'ofs.qty_medium',
                    'ofs.qty_large'
                )
                ->get();
        }

        $rows = $itemBomRows
            ->merge($modifierBomRows)
            ->unique('item_id')
            ->sortBy('item_name')
            ->values();

        $items = $rows->map(function ($row) {
            return [
                'item_id' => (int) $row->item_id,
                'item_name' => $row->item_name,
                'category_name' => $row->category_name ?: 'Tanpa Kategori',
                'sub_category_name' => $row->sub_category_name ?: 'Tanpa Sub Kategori',
                'unit_id' => $row->small_unit_id,
                'unit_name' => $row->unit_small,
                'stock' => [
                    'qty_small' => (float) ($row->qty_small ?? 0),
                    'qty_medium' => (float) ($row->qty_medium ?? 0),
                    'qty_large' => (float) ($row->qty_large ?? 0),
                    'unit_small' => $row->unit_small,
                    'unit_medium' => $row->unit_medium,
                    'unit_large' => $row->unit_large,
                ],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    /**
     * Cari item yang punya stok di outlet (termasuk WIP hasil produksi).
     * Dipakai form Category Cost non-Usage: input WIP memotong stok WIP itu sendiri.
     */
    public function searchItemsWithStock(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'warehouse_outlet_id' => 'required|integer|exists:warehouse_outlets,id',
            'q' => 'nullable|string|max:100',
        ]);

        $q = trim((string) $request->input('q', ''));
        $outletId = (int) $request->outlet_id;
        $warehouseOutletId = (int) $request->warehouse_outlet_id;

        $query = DB::table('outlet_food_inventory_stocks as ofs')
            ->join('outlet_food_inventory_items as ofii', 'ofii.id', '=', 'ofs.inventory_item_id')
            ->join('items as i', 'i.id', '=', 'ofii.item_id')
            ->join('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('units as us', 'us.id', '=', 'i.small_unit_id')
            ->leftJoin('units as um', 'um.id', '=', 'i.medium_unit_id')
            ->leftJoin('units as ul', 'ul.id', '=', 'i.large_unit_id')
            ->where('ofs.id_outlet', $outletId)
            ->where('ofs.warehouse_outlet_id', $warehouseOutletId)
            ->where('i.status', 'active')
            ->where('c.is_asset', '0')
            ->where(function ($sub) {
                $sub->where('ofs.qty_small', '>', 0)
                    ->orWhere('ofs.qty_medium', '>', 0)
                    ->orWhere('ofs.qty_large', '>', 0);
            });

        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(function ($sub) use ($like) {
                $sub->where('i.name', 'like', $like)
                    ->orWhere('i.sku', 'like', $like);
            });
        }

        $rows = $query
            ->select(
                'i.id',
                'i.name',
                'i.sku',
                'i.type',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'us.name as small_unit_name',
                'um.name as medium_unit_name',
                'ul.name as large_unit_name',
                'ofs.qty_small',
                'ofs.qty_medium',
                'ofs.qty_large'
            )
            ->orderByRaw("CASE WHEN UPPER(TRIM(i.type)) = 'WIP' THEN 0 ELSE 1 END")
            ->orderBy('i.name')
            ->limit(40)
            ->get();

        $items = $rows->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'type' => $row->type,
                'is_wip' => strtoupper(trim((string) ($row->type ?? ''))) === 'WIP',
                'small_unit_id' => $row->small_unit_id,
                'medium_unit_id' => $row->medium_unit_id,
                'large_unit_id' => $row->large_unit_id,
                'small_unit_name' => $row->small_unit_name,
                'medium_unit_name' => $row->medium_unit_name,
                'large_unit_name' => $row->large_unit_name,
                'stock' => [
                    'qty_small' => (float) ($row->qty_small ?? 0),
                    'qty_medium' => (float) ($row->qty_medium ?? 0),
                    'qty_large' => (float) ($row->qty_large ?? 0),
                    'unit_small' => $row->small_unit_name,
                    'unit_medium' => $row->medium_unit_name,
                    'unit_large' => $row->large_unit_name,
                ],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    /**
     * Cari menu POS (categories.show_pos=1) yang available di outlet dan punya BOM stock_cut=1.
     */
    public function searchPosMenus(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'q' => 'nullable|string|max:100',
        ]);

        $q = trim((string) $request->input('q', ''));
        $outletId = (int) $request->outlet_id;
        $regionId = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('region_id');

        $availableItemIds = DB::table('item_availabilities')
            ->where(function ($query) use ($outletId, $regionId) {
                $query->where('availability_type', 'all');
                if ($regionId) {
                    $query->orWhere(function ($q2) use ($regionId) {
                        $q2->where('availability_type', 'region')->where('region_id', $regionId);
                    });
                }
                $query->orWhere(function ($q2) use ($outletId) {
                    $q2->where('availability_type', 'outlet')->where('outlet_id', $outletId);
                });
            })
            ->pluck('item_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if (empty($availableItemIds)) {
            return response()->json(['success' => true, 'items' => []]);
        }

        $menuIdsWithBom = DB::table('item_bom')
            ->where('stock_cut', 1)
            ->whereIn('item_id', $availableItemIds)
            ->pluck('item_id')
            ->unique()
            ->values()
            ->all();

        if (empty($menuIdsWithBom)) {
            return response()->json(['success' => true, 'items' => []]);
        }

        $query = DB::table('items as i')
            ->join('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('units as us', 'us.id', '=', 'i.small_unit_id')
            ->leftJoin('units as um', 'um.id', '=', 'i.medium_unit_id')
            ->leftJoin('units as ul', 'ul.id', '=', 'i.large_unit_id')
            ->whereIn('i.id', $menuIdsWithBom)
            ->where('i.status', 'active')
            ->where('c.show_pos', '1')
            ->where('c.is_asset', '0');

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('i.name', 'like', $like)
                    ->orWhere('i.sku', 'like', $like);
            });
        }

        $rows = $query
            ->select(
                'i.id',
                'i.name',
                'i.sku',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'us.name as small_unit_name',
                'um.name as medium_unit_name',
                'ul.name as large_unit_name'
            )
            ->orderBy('i.name')
            ->limit(40)
            ->get();

        $items = $rows->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'line_mode' => 'menu',
                'small_unit_id' => $row->small_unit_id,
                'medium_unit_id' => $row->medium_unit_id,
                'large_unit_id' => $row->large_unit_id,
                'small_unit_name' => $row->small_unit_name,
                'medium_unit_name' => $row->medium_unit_name,
                'large_unit_name' => $row->large_unit_name,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    /**
     * Modifier groups + options yang terpasang ke menu item.
     */
    public function getMenuModifiers($itemId)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return response()->json(['success' => false, 'message' => 'Item tidak valid'], 422);
        }

        $rows = DB::table('item_modifier_options as imo')
            ->join('modifier_options as mo', 'mo.id', '=', 'imo.modifier_option_id')
            ->join('modifiers as m', 'm.id', '=', 'mo.modifier_id')
            ->where('imo.item_id', $itemId)
            ->select(
                'm.id as modifier_id',
                'm.name as modifier_name',
                'mo.id as option_id',
                'mo.name as option_name'
            )
            ->orderBy('m.name')
            ->orderBy('mo.name')
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $mid = (int) $row->modifier_id;
            if (!isset($groups[$mid])) {
                $groups[$mid] = [
                    'id' => $mid,
                    'name' => $row->modifier_name,
                    'options' => [],
                ];
            }
            $groups[$mid]['options'][] = [
                'id' => (int) $row->option_id,
                'name' => $row->option_name,
            ];
        }

        return response()->json([
            'success' => true,
            'groups' => array_values($groups),
        ]);
    }

    public function items(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $limit = (int) $request->input('limit', 20);
        if ($limit <= 0) {
            $limit = 20;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $query = DB::table('items')
            ->join('categories', 'categories.id', '=', 'items.category_id')
            ->where('items.status', 'active')
            ->where('categories.is_asset', '0')
            ->select('items.id', 'items.name', 'items.sku');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('items.name', 'like', "%{$search}%")
                  ->orWhere('items.sku', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('items.name')->limit($limit)->get();

        return response()->json($items);
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
                ['value' => 'usage', 'label' => 'Usage'],
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
                ->select('d.*', 'h.type as header_type', 'h.date as header_date', 'h.outlet_id as header_outlet_id', 'h.warehouse_outlet_id as header_warehouse_outlet_id', 'i.type as item_type', 'i.small_unit_id', 'i.medium_unit_id', 'i.large_unit_id', 'i.small_conversion_qty', 'i.medium_conversion_qty')
                ->whereIn('d.header_id', $headerIds)
                ->get();
            
            // Batch query untuk inventory items
            $itemIds = $details->pluck('item_id')->unique()->all();
            $inventoryItems = [];
            if (count($itemIds) > 0) {
                $inventoryItems = DB::table('outlet_food_inventory_items')
                    ->whereIn('item_id', $itemIds)
                    ->get()
                    ->keyBy('item_id');
            }
            
            // Batch query untuk MAC histories - optimasi dengan subquery
            $inventoryItemIds = $inventoryItems->pluck('id')->unique()->values()->all();
            $macHistories = [];
            if (count($inventoryItemIds) > 0 && count($headerIds) > 0) {
                // Get unique combinations of outlet_id, warehouse_outlet_id, and dates
                $headerData = $data->keyBy('id');
                $macQueryConditions = [];
                foreach ($details as $detail) {
                    $header = $headerData->get($detail->header_id);
                    $inventoryItem = $inventoryItems->get($detail->item_id);
                    if ($header && $inventoryItem) {
                        $inventoryItemId = (int) $inventoryItem->id;
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
                
                foreach ($macQueryConditions as $condition) {
                    $macKey = "{$condition['inventory_item_id']}_{$condition['id_outlet']}_{$condition['warehouse_outlet_id']}_{$condition['date']}";
                    $macHistories[$macKey] = CategoryCostMacResolver::resolveHistoryMacAtDate(
                        (int) $condition['inventory_item_id'],
                        (int) $condition['id_outlet'],
                        (int) $condition['warehouse_outlet_id'],
                        (string) $condition['date']
                    );
                }
            }
            
            // Hitung subtotal MAC per header
            $subtotalPerHeader = [];
            foreach ($details as $item) {
                $mac = null;
                $header = $data->firstWhere('id', $item->header_id);
                $inventoryItem = $inventoryItems->get($item->item_id);
                if ($header && $inventoryItem) {
                    $macKey = "{$inventoryItem->id}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";
                    $mac = $macHistories[$macKey] ?? null;
                }

                $lineMac = $this->categoryCostMacForDetailLine(
                    $item,
                    $mac,
                    (int) ($item->header_outlet_id ?? 0),
                    (int) ($item->header_warehouse_outlet_id ?? 0),
                    (string) ($item->header_date ?? '')
                );
                $subtotal_mac = $lineMac['subtotal_mac'];
                
                if (!isset($subtotalPerHeader[$item->header_id])) {
                    $subtotalPerHeader[$item->header_id] = 0;
                }
                $subtotalPerHeader[$item->header_id] += $subtotal_mac;
            }
            
            // Add subtotal_mac to each header row (fallback ke nilai tersimpan jika hitung ulang 0)
            $data = collect($data)->map(function ($row) use ($subtotalPerHeader) {
                $recalculated = round((float) ($subtotalPerHeader[$row->id] ?? 0), 2);
                $stored = round((float) ($row->subtotal_mac ?? 0), 2);
                $row->subtotal_mac = $recalculated > 0 ? $recalculated : ($stored > 0 ? $stored : $recalculated);

                return $row;
            });

            $totalPerType = [];
            foreach ($data as $row) {
                $type = (string) ($row->type ?? '');
                if ($type === '') {
                    continue;
                }
                $totalPerType[$type] = ($totalPerType[$type] ?? 0) + (float) ($row->subtotal_mac ?? 0);
            }
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
            ['value' => 'usage', 'label' => 'Usage'],
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
            ->select('d.*', 'i.name as item_name', 'i.type as item_type', 'u.name as unit_name', 'i.small_unit_id', 'i.medium_unit_id', 'i.large_unit_id', 'i.small_conversion_qty', 'i.medium_conversion_qty')
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
                    $mac = CategoryCostMacResolver::resolveHistoryMacAtDate(
                        (int) $inventoryItem->id,
                        (int) $header->outlet_id,
                        (int) $header->warehouse_outlet_id,
                        (string) $header->date
                    );
                }
                \Log::debug('DETAILS: MAC for item_id ' . $item->item_id . ': ' . ($mac !== null ? $mac : 'NOT FOUND'));
                $lineMac = $this->categoryCostMacForDetailLine(
                    $item,
                    $mac,
                    (int) $header->outlet_id,
                    (int) $header->warehouse_outlet_id,
                    (string) $header->date
                );
                $mac_converted = $lineMac['mac_converted'];
                $subtotal_mac = $mac !== null ? $lineMac['subtotal_mac'] : null;
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
        $like = $search !== '' ? ('%' . addcslashes($search, '%_\\') . '%') : null;

        $users = User::where('users.status', 'A')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->when($like !== null, function ($q) use ($like) {
                $q->where(function ($query) use ($like) {
                    $query->where('users.nama_lengkap', 'like', $like)
                          ->orWhere('users.email', 'like', $like)
                          ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', $like);
                });
            })
            ->select('users.id', 'users.nama_lengkap as name', 'users.email', 'tbl_data_jabatan.nama_jabatan as jabatan')
            ->orderBy('users.nama_lengkap')
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
            
            // Legacy bug: storeAndSubmit dulu bisa sudah potong stok saat SUBMITTED.
            // Saat reject, kembalikan stok jika kartu OUT sudah ada.
            if ($this->hasOutletInternalUseWasteStockCards((int) $id)) {
                \Log::warning('OutletInternalUseWaste reject - Rolling back premature stock cards', [
                    'header_id' => $id,
                    'type' => $header->type,
                    'status' => $header->status,
                ]);
                $this->rollbackStockFromCards($header, (int) $id);
            }

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

        $cuts = $this->expandDetailsToMaterialCuts($header, $details);

        // Idempotent: legacy storeAndSubmit bisa sudah menulis kartu saat SUBMITTED.
        // Jangan potong ulang — cukup sync subtotal_mac dari kartu yang ada.
        if ($this->hasOutletInternalUseWasteStockCards((int) $headerId)) {
            $subtotalMac = (float) DB::table('outlet_food_inventory_cards')
                ->where('reference_type', 'outlet_internal_use_waste')
                ->where('reference_id', $headerId)
                ->sum('value_out');

            \Log::warning('OutletInternalUseWaste processStockAfterApproval: Stock cards already exist, skipping re-cut', [
                'header_id' => $headerId,
                'type' => $header->type,
                'subtotal_mac_from_cards' => $subtotalMac,
            ]);
        } else {
            $subtotalMac = $this->applyMaterialStockCuts(
                $header,
                (int) $headerId,
                $cuts,
                'Stock Out - '.$header->type.' (After Approval)',
                true
            );
        }

        DB::table('outlet_internal_use_waste_headers')
            ->where('id', $headerId)
            ->update([
                'subtotal_mac' => $subtotalMac,
                'updated_at' => now(),
            ]);
        
        \Log::info("OutletInternalUseWaste processStockAfterApproval: Stock processing completed", [
            'header_id' => $headerId,
            'type' => $header->type,
            'total_details_processed' => $details->count()
        ]);
    }

    private function hasOutletInternalUseWasteStockCards(int $headerId): bool
    {
        return DB::table('outlet_food_inventory_cards')
            ->where('reference_type', 'outlet_internal_use_waste')
            ->where('reference_id', $headerId)
            ->exists();
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

    /**
     * Build detail row for insert (termasuk snapshot usage: stock on hand & stock fisik).
     */
    private function buildDetailInsertRow(int $headerId, array $item, string $type): array
    {
        $lineMode = $this->normalizeLineMode($item['line_mode'] ?? null, $type);
        $modifierOptionIds = $lineMode === 'menu'
            ? $this->sanitizeModifierOptionIdsForItem((int) $item['item_id'], $item['modifier_option_ids'] ?? [])
            : [];

        $row = [
            'header_id' => $headerId,
            'item_id' => $item['item_id'],
            'line_mode' => $lineMode,
            'qty' => $item['qty'],
            'unit_id' => $item['unit_id'],
            'note' => $item['note'] ?? null,
            'modifier_option_ids' => !empty($modifierOptionIds) ? json_encode(array_values($modifierOptionIds)) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($type === 'usage') {
            $row['stock_on_hand'] = array_key_exists('stock_on_hand', $item) && $item['stock_on_hand'] !== null && $item['stock_on_hand'] !== ''
                ? (float) $item['stock_on_hand']
                : null;
            $row['physical_qty'] = array_key_exists('physical_qty', $item) && $item['physical_qty'] !== null && $item['physical_qty'] !== ''
                ? (float) $item['physical_qty']
                : null;
        }

        return $row;
    }

    /**
     * Update baris stok setelah pemotongan Category Cost.
     * Item WIP: qty & value disinkronkan (potong stok WIP itu sendiri, bukan bahan baku).
     */
    private function buildCategoryCostStockRowUpdate(
        object $itemMaster,
        object $stock,
        float $qty_small,
        float $qty_medium,
        float $qty_large,
        float $costPerSmall
    ): array {
        $newQtySmall = (float) $stock->qty_small - $qty_small;
        $newQtyMedium = (float) $stock->qty_medium - $qty_medium;
        $newQtyLarge = (float) $stock->qty_large - $qty_large;

        $update = [
            'qty_small' => $newQtySmall,
            'qty_medium' => $newQtyMedium,
            'qty_large' => $newQtyLarge,
            'updated_at' => now(),
        ];

        if (CategoryCostMacResolver::isWipItem($itemMaster)) {
            $update['value'] = max(0, $newQtySmall * $costPerSmall);
        }

        return $update;
    }

    /**
     * Hitung MAC & subtotal satu baris detail category cost (termasuk penyesuaian item WIP).
     *
     * @return array{mac_converted: ?float, subtotal_mac: float}
     */
    private function categoryCostMacForDetailLine(
        object $detailRow,
        ?float $historyMac,
        int $outletId,
        int $warehouseOutletId,
        string $asOfDate
    ): array {
        if ($historyMac === null) {
            return ['mac_converted' => null, 'subtotal_mac' => 0.0];
        }

        $itemMaster = (object) [
            'id' => $detailRow->item_id,
            'type' => $detailRow->item_type ?? null,
            'small_unit_id' => $detailRow->small_unit_id,
            'medium_unit_id' => $detailRow->medium_unit_id,
            'large_unit_id' => $detailRow->large_unit_id,
            'small_conversion_qty' => $detailRow->small_conversion_qty,
            'medium_conversion_qty' => $detailRow->medium_conversion_qty,
        ];

        $macPerSmall = CategoryCostMacResolver::resolveMacPerSmallUnit(
            $itemMaster,
            $historyMac,
            $outletId,
            $warehouseOutletId,
            $asOfDate
        );
        $macConverted = CategoryCostMacResolver::convertMacToUnit(
            $macPerSmall,
            $itemMaster,
            (int) $detailRow->unit_id
        );

        return [
            'mac_converted' => $macConverted,
            'subtotal_mac' => $macConverted * (float) ($detailRow->qty ?? 0),
        ];
    }

    /**
     * MAC per satuan kecil untuk transaksi category cost (spoil/waste/dll).
     * Pakai histori MAC per tanggal — sama dengan laporan, bukan last_cost_small mentah.
     */
    private function resolveCategoryCostMacPerSmall(
        object $itemMaster,
        int $inventoryItemId,
        int $outletId,
        int $warehouseOutletId,
        string $asOfDate,
        ?object $stock = null
    ): float {
        $historyMac = CategoryCostMacResolver::resolveHistoryMacAtDate(
            $inventoryItemId,
            $outletId,
            $warehouseOutletId,
            $asOfDate
        );

        if ($historyMac === null && $stock) {
            $historyMac = (float) ($stock->last_cost_small ?? 0);
        }

        return CategoryCostMacResolver::resolveMacPerSmallUnit(
            $itemMaster,
            $historyMac,
            $outletId,
            $warehouseOutletId,
            $asOfDate
        );
    }

    private function normalizeLineMode(?string $mode, string $docType): string
    {
        if (in_array($docType, ['usage', 'stock_cut'], true)) {
            return 'material';
        }

        return $mode === 'menu' ? 'menu' : 'material';
    }

    /**
     * Konversi qty input ke triplet small/medium/large menurut master item.
     *
     * @return array{qty_small: float, qty_medium: float, qty_large: float}
     */
    private function convertQtyToUnitTriplet(object $itemMaster, $unitId, float $qtyInput): array
    {
        $unitName = is_numeric($unitId)
            ? DB::table('units')->where('id', $unitId)->value('name')
            : (string) $unitId;
        $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
        $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
        $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
        $smallConv = (float) ($itemMaster->small_conversion_qty ?: 1);
        $mediumConv = (float) ($itemMaster->medium_conversion_qty ?: 1);

        $qty_small = 0.0;
        $qty_medium = 0.0;
        $qty_large = 0.0;

        if ($unitName === $unitSmall) {
            $qty_small = $qtyInput;
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
            $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
        } elseif ($unitName === $unitMedium) {
            $qty_medium = $qtyInput;
            $qty_small = $qty_medium * $smallConv;
            $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
        } elseif ($unitName === $unitLarge) {
            $qty_large = $qtyInput;
            $qty_medium = $qty_large * $mediumConv;
            $qty_small = $qty_medium * $smallConv;
        } else {
            $qty_small = $qtyInput;
        }

        return [
            'qty_small' => $qty_small,
            'qty_medium' => $qty_medium,
            'qty_large' => $qty_large,
        ];
    }

    /**
     * Expand detail lines → aggregated material cuts.
     * material: potong item itu sendiri; menu: explode item_bom.stock_cut=1 × qty porsi.
     *
     * @return array<int, array{item_id:int,qty_small:float,qty_medium:float,qty_large:float,sources:array<int,string>}>
     */
    private function expandDetailsToMaterialCuts(object $header, $details): array
    {
        $cuts = [];

        foreach ($details as $detail) {
            $lineMode = $this->normalizeLineMode($detail->line_mode ?? 'material', (string) $header->type);
            $menuQty = (float) $detail->qty;

            if ($lineMode === 'menu') {
                $menuName = DB::table('items')->where('id', $detail->item_id)->value('name') ?? ('Menu #'.$detail->item_id);
                $bomRows = DB::table('item_bom')
                    ->where('item_id', $detail->item_id)
                    ->where('stock_cut', 1)
                    ->get();

                if ($bomRows->isEmpty()) {
                    throw new \Exception("Menu \"{$menuName}\" tidak punya BOM stock_cut. Tidak dapat memotong stok.");
                }

                foreach ($bomRows as $bom) {
                    $this->accumulateMaterialCut(
                        $cuts,
                        (int) $bom->material_item_id,
                        $bom->unit_id,
                        (float) $bom->qty * $menuQty,
                        $menuName.' × '.$menuQty
                    );
                }

                $modifierOptionIds = $this->parseModifierOptionIds($detail->modifier_option_ids ?? null);
                foreach ($this->expandModifierOptionsToBomCuts($modifierOptionIds, $menuQty, $menuName) as $modCut) {
                    $this->accumulateMaterialCut(
                        $cuts,
                        (int) $modCut['item_id'],
                        $modCut['unit_id'],
                        (float) $modCut['qty'],
                        $modCut['source']
                    );
                }
                continue;
            }

            $itemMaster = DB::table('items')->where('id', $detail->item_id)->first();
            if (!$itemMaster) {
                throw new \Exception('Item master tidak ditemukan untuk item_id: '.$detail->item_id);
            }
            $needed = $this->convertQtyToUnitTriplet($itemMaster, $detail->unit_id, $menuQty);
            $mid = (int) $detail->item_id;
            if (!isset($cuts[$mid])) {
                $cuts[$mid] = [
                    'item_id' => $mid,
                    'qty_small' => 0.0,
                    'qty_medium' => 0.0,
                    'qty_large' => 0.0,
                    'sources' => [],
                ];
            }
            $cuts[$mid]['qty_small'] += $needed['qty_small'];
            $cuts[$mid]['qty_medium'] += $needed['qty_medium'];
            $cuts[$mid]['qty_large'] += $needed['qty_large'];
            $cuts[$mid]['sources'][] = ($itemMaster->name ?? ('Item #'.$mid));
        }

        return $cuts;
    }

    /**
     * Validasi + (opsional) potong stok & tulis kartu OUT dari aggregated cuts.
     * Return subtotal MAC (qty_small × mac_per_small).
     */
    private function applyMaterialStockCuts(
        object $header,
        int $headerId,
        array $cuts,
        string $description,
        bool $applyStock = true
    ): float {
        $subtotalMac = 0.0;
        $insufficient = [];

        foreach ($cuts as $cut) {
            $itemMaster = DB::table('items')->where('id', $cut['item_id'])->first();
            if (!$itemMaster) {
                throw new \Exception('Item master tidak ditemukan untuk item_id: '.$cut['item_id']);
            }

            $inventoryItem = DB::table('outlet_food_inventory_items')
                ->where('item_id', $cut['item_id'])
                ->first();
            if (!$inventoryItem) {
                $name = $itemMaster->name ?? ('Item #'.$cut['item_id']);
                throw new \Exception("Item \"{$name}\" tidak ditemukan di inventory outlet");
            }

            $stock = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventoryItem->id)
                ->where('id_outlet', $header->outlet_id)
                ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                ->first();

            $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name') ?? 'unit';
            $available = $stock ? (float) $stock->qty_small : 0.0;
            if (!$stock || $cut['qty_small'] > $available) {
                $insufficient[] = [
                    'name' => $itemMaster->name ?? ('Item #'.$cut['item_id']),
                    'required' => $cut['qty_small'],
                    'available' => $available,
                    'unit' => $unitSmall,
                    'sources' => $cut['sources'] ?? [],
                ];
                continue;
            }

            $macPerSmall = $this->resolveCategoryCostMacPerSmall(
                $itemMaster,
                (int) $inventoryItem->id,
                (int) $header->outlet_id,
                (int) $header->warehouse_outlet_id,
                (string) $header->date,
                $stock
            );
            $subtotalMac += $cut['qty_small'] * $macPerSmall;

            if (!$applyStock) {
                continue;
            }

            [$costPerSmall, $costPerMedium, $costPerLarge] = CategoryCostMacResolver::costRatesPerUnit(
                $itemMaster,
                $macPerSmall,
                $stock
            );

            $updated = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventoryItem->id)
                ->where('id_outlet', $header->outlet_id)
                ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                ->update($this->buildCategoryCostStockRowUpdate(
                    $itemMaster,
                    $stock,
                    $cut['qty_small'],
                    $cut['qty_medium'],
                    $cut['qty_large'],
                    $costPerSmall
                ));

            if ($updated === 0) {
                throw new \Exception('Gagal mengupdate stock untuk item: '.($itemMaster->name ?? $cut['item_id']));
            }

            $sourceNote = !empty($cut['sources'])
                ? ' ['.implode('; ', array_unique($cut['sources'])).']'
                : '';

            DB::table('outlet_food_inventory_cards')->insert([
                'inventory_item_id' => $inventoryItem->id,
                'id_outlet' => $header->outlet_id,
                'warehouse_outlet_id' => $header->warehouse_outlet_id,
                'date' => $header->date,
                'reference_type' => 'outlet_internal_use_waste',
                'reference_id' => $headerId,
                'out_qty_small' => $cut['qty_small'],
                'out_qty_medium' => $cut['qty_medium'],
                'out_qty_large' => $cut['qty_large'],
                'cost_per_small' => $costPerSmall,
                'cost_per_medium' => $costPerMedium,
                'cost_per_large' => $costPerLarge,
                'value_out' => $cut['qty_small'] * $costPerSmall,
                'saldo_qty_small' => $stock->qty_small - $cut['qty_small'],
                'saldo_qty_medium' => $stock->qty_medium - $cut['qty_medium'],
                'saldo_qty_large' => $stock->qty_large - $cut['qty_large'],
                'saldo_value' => ($stock->qty_small - $cut['qty_small']) * $costPerSmall,
                'description' => $description.$sourceNote,
                'created_at' => now(),
            ]);
        }

        if (!empty($insufficient)) {
            $errorMessage = "Stock tidak mencukupi untuk item berikut:\n\n";
            foreach ($insufficient as $row) {
                $errorMessage .= "• {$row['name']}\n";
                $errorMessage .= "  Dibutuhkan: {$row['required']} {$row['unit']}\n";
                $errorMessage .= "  Tersedia: {$row['available']} {$row['unit']}\n";
                if (!empty($row['sources'])) {
                    $errorMessage .= '  Sumber: '.implode(', ', array_unique($row['sources']))."\n";
                }
                $errorMessage .= "\n";
            }
            throw new \Exception($errorMessage);
        }

        return $subtotalMac;
    }

    /**
     * Preview BOM stock_cut untuk baris menu (Show page), termasuk modifier terpilih.
     */
    private function previewMenuBomLines(int $menuItemId, float $menuQty, array $modifierOptionIds = []): array
    {
        $rows = DB::table('item_bom as ib')
            ->join('items as i', 'i.id', '=', 'ib.material_item_id')
            ->leftJoin('units as u', 'u.id', '=', 'ib.unit_id')
            ->where('ib.item_id', $menuItemId)
            ->where('ib.stock_cut', 1)
            ->select('i.id as material_item_id', 'i.name as material_name', 'ib.qty', 'ib.unit_id', 'u.name as unit_name')
            ->orderBy('i.name')
            ->get();

        $preview = $rows->map(function ($row) use ($menuQty) {
            return [
                'material_item_id' => (int) $row->material_item_id,
                'material_name' => $row->material_name,
                'qty_per_portion' => (float) $row->qty,
                'qty_total' => (float) $row->qty * $menuQty,
                'unit_id' => $row->unit_id,
                'unit_name' => $row->unit_name,
                'source' => 'menu',
            ];
        })->values()->all();

        $menuName = DB::table('items')->where('id', $menuItemId)->value('name') ?? ('Menu #'.$menuItemId);
        foreach ($this->expandModifierOptionsToBomCuts($modifierOptionIds, $menuQty, $menuName) as $modCut) {
            $material = DB::table('items')->where('id', $modCut['item_id'])->first();
            $unitName = $modCut['unit_id']
                ? (DB::table('units')->where('id', $modCut['unit_id'])->value('name') ?? null)
                : null;
            $preview[] = [
                'material_item_id' => (int) $modCut['item_id'],
                'material_name' => ($material->name ?? ('Item #'.$modCut['item_id'])).' ['.$modCut['option_name'].']',
                'qty_per_portion' => (float) $modCut['qty_per_portion'],
                'qty_total' => (float) $modCut['qty'],
                'unit_id' => $modCut['unit_id'],
                'unit_name' => $unitName,
                'source' => 'modifier',
            ];
        }

        return $preview;
    }

    private function parseModifierOptionIds($value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map('intval', $value))));
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_unique(array_filter(array_map('intval', $decoded))));
            }
        }

        return [];
    }

    private function sanitizeModifierOptionIdsForItem(int $itemId, $rawIds): array
    {
        $ids = $this->parseModifierOptionIds($rawIds);
        if ($itemId <= 0 || empty($ids)) {
            return [];
        }

        return DB::table('item_modifier_options')
            ->where('item_id', $itemId)
            ->whereIn('modifier_option_id', $ids)
            ->pluck('modifier_option_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function resolveModifierOptionLabels(array $optionIds): array
    {
        if (empty($optionIds)) {
            return [];
        }

        return DB::table('modifier_options as mo')
            ->join('modifiers as m', 'm.id', '=', 'mo.modifier_id')
            ->whereIn('mo.id', $optionIds)
            ->orderBy('m.name')
            ->orderBy('mo.name')
            ->get(['m.name as modifier_name', 'mo.name as option_name'])
            ->map(fn ($row) => trim(($row->modifier_name ? $row->modifier_name.': ' : '').$row->option_name))
            ->values()
            ->all();
    }

    /**
     * Modifier BOM semantics (sama StockCut): stock_cut true/1 = skip; false/null = ikut potong.
     */
    private function isModifierBomLineCountedForStock($stockCut): bool
    {
        if ($stockCut === null || $stockCut === false || $stockCut === 0 || $stockCut === '0' || $stockCut === 'false' || $stockCut === '') {
            return true;
        }
        if ($stockCut === true || $stockCut === 1 || $stockCut === '1' || $stockCut === 'true' || $stockCut === 'yes') {
            return false;
        }

        return false;
    }

    /**
     * @return array<int, array{item_id:int,unit_id:mixed,qty:float,qty_per_portion:float,option_name:string,source:string}>
     */
    private function expandModifierOptionsToBomCuts(array $optionIds, float $menuQty, string $menuName): array
    {
        if (empty($optionIds) || $menuQty <= 0) {
            return [];
        }

        $options = DB::table('modifier_options')
            ->whereIn('id', $optionIds)
            ->get(['id', 'name', 'modifier_bom_json']);

        $cuts = [];
        foreach ($options as $option) {
            $bomRows = json_decode((string) ($option->modifier_bom_json ?? ''), true);
            if (!is_array($bomRows)) {
                continue;
            }
            foreach ($bomRows as $bomRow) {
                if (!is_array($bomRow)) {
                    continue;
                }
                if (!$this->isModifierBomLineCountedForStock($bomRow['stock_cut'] ?? null)) {
                    continue;
                }
                $materialItemId = isset($bomRow['item_id']) ? (int) $bomRow['item_id'] : 0;
                if ($materialItemId <= 0) {
                    continue;
                }
                $qtyPerPortion = (float) ($bomRow['qty'] ?? 0);
                if ($qtyPerPortion <= 0) {
                    continue;
                }
                $cuts[] = [
                    'item_id' => $materialItemId,
                    'unit_id' => $bomRow['unit_id'] ?? null,
                    'qty' => $qtyPerPortion * $menuQty,
                    'qty_per_portion' => $qtyPerPortion,
                    'option_name' => $option->name,
                    'source' => $menuName.' + '.$option->name.' × '.$menuQty,
                ];
            }
        }

        return $cuts;
    }

    private function accumulateMaterialCut(
        array &$cuts,
        int $materialItemId,
        $unitId,
        float $qtyInput,
        string $source
    ): void {
        if ($materialItemId <= 0 || $qtyInput <= 0) {
            return;
        }
        $material = DB::table('items')->where('id', $materialItemId)->first();
        if (!$material) {
            return;
        }
        $needed = $this->convertQtyToUnitTriplet($material, $unitId, $qtyInput);
        if (!isset($cuts[$materialItemId])) {
            $cuts[$materialItemId] = [
                'item_id' => $materialItemId,
                'qty_small' => 0.0,
                'qty_medium' => 0.0,
                'qty_large' => 0.0,
                'sources' => [],
            ];
        }
        $cuts[$materialItemId]['qty_small'] += $needed['qty_small'];
        $cuts[$materialItemId]['qty_medium'] += $needed['qty_medium'];
        $cuts[$materialItemId]['qty_large'] += $needed['qty_large'];
        $cuts[$materialItemId]['sources'][] = $source;
    }

    /**
     * Rollback stok PROCESSED dari kartu OUT header (aman untuk material & menu/BOM).
     */
    private function rollbackStockFromCards(object $header, int $headerId): void
    {
        $cards = DB::table('outlet_food_inventory_cards')
            ->where('reference_type', 'outlet_internal_use_waste')
            ->where('reference_id', $headerId)
            ->get();

        $aggregated = [];
        foreach ($cards as $card) {
            $invId = (int) $card->inventory_item_id;
            if (!isset($aggregated[$invId])) {
                $aggregated[$invId] = [
                    'qty_small' => 0.0,
                    'qty_medium' => 0.0,
                    'qty_large' => 0.0,
                ];
            }
            $aggregated[$invId]['qty_small'] += (float) ($card->out_qty_small ?? 0);
            $aggregated[$invId]['qty_medium'] += (float) ($card->out_qty_medium ?? 0);
            $aggregated[$invId]['qty_large'] += (float) ($card->out_qty_large ?? 0);
        }

        foreach ($aggregated as $invId => $qty) {
            $stock = DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $invId)
                ->where('id_outlet', $header->outlet_id)
                ->where('warehouse_outlet_id', $header->warehouse_outlet_id)
                ->first();

            if (!$stock) {
                \Log::warning('OutletInternalUseWaste rollbackStockFromCards - stock row missing', [
                    'header_id' => $headerId,
                    'inventory_item_id' => $invId,
                ]);
                continue;
            }

            DB::table('outlet_food_inventory_stocks')
                ->where('id', $stock->id)
                ->update([
                    'qty_small' => DB::raw('qty_small + '.$qty['qty_small']),
                    'qty_medium' => DB::raw('qty_medium + '.$qty['qty_medium']),
                    'qty_large' => DB::raw('qty_large + '.$qty['qty_large']),
                    'updated_at' => now(),
                ]);
        }

        DB::table('outlet_food_inventory_cards')
            ->where('reference_type', 'outlet_internal_use_waste')
            ->where('reference_id', $headerId)
            ->delete();
    }
} 