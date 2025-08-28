<?php

namespace App\Http\Controllers;

use App\Models\ContraBon;
use App\Models\ContraBonItem;
use App\Models\PurchaseOrderFood;
use App\Models\PurchaseOrderFoodItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ContraBonController extends Controller
{
    public function index(Request $request)
    {
        $query = ContraBon::with(['supplier', 'purchaseOrder', 'retailFood', 'creator'])->orderByDesc('created_at');

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('number', 'like', "%$search%")
                  ->orWhere('supplier_invoice_number', 'like', "%$search%")
                  ->orWhereHas('supplier', function($q2) use ($search) {
                      $q2->where('name', 'like', "%$search%");
                  })
                  ->orWhereHas('purchaseOrder', function($q2) use ($search) {
                      $q2->where('number', 'like', "%$search%");
                  })
                  ->orWhereHas('retailFood', function($q2) use ($search) {
                      $q2->where('retail_number', 'like', "%$search%");
                  })
                  ->orWhere('total_amount', 'like', "%$search%")
                  ->orWhere('status', 'like', "%$search%")
                  ->orWhereHas('creator', function($q2) use ($search) {
                      $q2->where('nama_lengkap', 'like', "%$search%");
                  });
            });
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->from) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('date', '<=', $request->to);
        }
        $contraBons = $query->paginate(10)->withQueryString();
        return inertia('ContraBon/Index', [
            'contraBons' => $contraBons,
            'filters' => $request->only(['search', 'status', 'from', 'to']),
        ]);
    }

    public function create()
    {
        return inertia('ContraBon/Form');
    }

    public function store(Request $request)
    {
        $request->validate([
            'po_id' => 'required',
            'date' => 'required|date',
            'items' => 'required|array',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'supplier_invoice_number' => 'nullable|string|max:100',
            'source_type' => 'nullable|in:purchase_order,retail_food',
            'source_id' => 'nullable|integer',
        ]);

        DB::beginTransaction();
        try {
            $sourceType = $request->input('source_type', 'purchase_order');
            $sourceId = $request->input('source_id');
            $supplierId = null;
            $poId = null;
            $grId = null;

            // Handle different source types
            if ($sourceType === 'purchase_order') {
                $po = PurchaseOrderFood::findOrFail($request->po_id);
                $supplierId = $po->supplier_id;
                $poId = $po->id;
                $grId = $request->input('gr_id');
            } elseif ($sourceType === 'retail_food') {
                $retailFood = \App\Models\RetailFood::findOrFail($sourceId);
                $supplierId = $retailFood->supplier_id;
                $poId = null;
                $grId = null;
            }
            
            // Generate contra bon number
            $dateStr = date('Ymd', strtotime($request->date));
            $countToday = ContraBon::whereDate('date', $request->date)->count();
            $number = 'CB-' . $dateStr . '-' . str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);

            // Calculate total amount
            $totalAmount = collect($request->items)->sum(function ($item) {
                return $item['quantity'] * $item['price'];
            });

            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                \Log::info('File ditemukan', [$request->file('image')]);
                $imagePath = $request->file('image')->store('contra_bon_images', 'public');
            } else {
                \Log::info('File TIDAK ditemukan');
            }
            \Log::info('Image path yang akan disimpan:', [$imagePath]);

            // Create contra bon
            $contraBon = ContraBon::create([
                'number' => $number,
                'date' => $request->date,
                'supplier_id' => $supplierId,
                'po_id' => $poId,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
                'image_path' => $imagePath,
                'status' => 'draft',
                'created_by' => Auth::id(),
                'supplier_invoice_number' => $request->supplier_invoice_number,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);

            // Create contra bon items
            foreach ($request->items as $item) {
                ContraBonItem::create([
                    'contra_bon_id' => $contraBon->id,
                    'item_id' => $item['item_id'],
                    'po_item_id' => $item['po_item_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit_id'],
                    'price' => $item['price'],
                    'total' => $item['quantity'] * $item['price'],
                    'notes' => $item['notes'] ?? null
                ]);
            }

            // Activity log
            \App\Models\ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'contra_bon',
                'description' => 'Create Contra Bon: ' . $contraBon->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $contraBon->fresh()->toArray(),
            ]);

            // Send notification to Finance Manager
            $financeManagers = \DB::table('users')
                ->where('id_jabatan', 160)
                ->where('status', 'A')
                ->pluck('id');
            
            $this->sendNotification(
                $financeManagers,
                'contra_bon_approval',
                'Approval Contra Bon',
                "Contra Bon {$contraBon->number} menunggu approval Anda.",
                route('contra-bons.show', $contraBon->id)
            );

            DB::commit();
            return redirect()->route('contra-bons.index')
                ->with('success', 'Contra Bon berhasil dibuat');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $contraBon = ContraBon::with([
            'supplier',
            'purchaseOrder',
            'items.item',
            'items.unit',
            'creator',
            'approver',
            'financeManager',
            'gmFinance'
        ])->findOrFail($id);

        return inertia('ContraBon/Show', [
            'contraBon' => $contraBon,
            'user' => auth()->user()
        ]);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'approved' => 'required|boolean',
            'note' => 'nullable|string'
        ]);

        $user = Auth::user();
        $contraBon = ContraBon::findOrFail($id);

        // Superadmin check
        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';

        // Finance Manager Approval
        if (
            ($user->id_jabatan == 160 && $user->status == 'A' && $contraBon->status == 'draft' && !$contraBon->finance_manager_approved_at)
            || ($isSuperadmin && $contraBon->status == 'draft' && !$contraBon->finance_manager_approved_at)
        ) {
            $contraBon->update([
                'finance_manager_approved_at' => now(),
                'finance_manager_approved_by' => $user->id,
                'finance_manager_note' => $request->note,
                'status' => $request->approved ? 'draft' : 'rejected'
            ]);

            // Log activity
            \App\Models\ActivityLog::create([
                'user_id' => $user->id,
                'activity_type' => $request->approved ? 'approve' : 'reject',
                'module' => 'contra_bon',
                'description' => ($request->approved ? 'Approve' : 'Reject') . ' Contra Bon (Finance Manager): ' . $contraBon->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $contraBon->fresh()->toArray(),
            ]);

            // Notifikasi ke GM Finance jika approve
            if ($request->approved) {
                $gmFinances = \DB::table('users')
                    ->where('id_jabatan', 152)
                    ->where('status', 'A')
                    ->pluck('id');
                $this->sendNotification(
                    $gmFinances,
                    'contra_bon_approval',
                    'Approval Contra Bon',
                    "Contra Bon {$contraBon->number} menunggu approval Anda.",
                    route('contra-bons.show', $contraBon->id)
                );
            }

            $msg = 'Contra Bon berhasil ' . ($request->approved ? 'diapprove' : 'direject');
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }
            return back()->with('success', $msg);
        }

        // GM Finance Approval
        if (
            ($user->id_jabatan == 152 && $user->status == 'A' && $contraBon->status == 'draft' && $contraBon->finance_manager_approved_at && !$contraBon->gm_finance_approved_at)
            || ($isSuperadmin && $contraBon->status == 'draft' && $contraBon->finance_manager_approved_at && !$contraBon->gm_finance_approved_at)
        ) {
            $contraBon->update([
                'gm_finance_approved_at' => now(),
                'gm_finance_approved_by' => $user->id,
                'gm_finance_note' => $request->note,
                'status' => $request->approved ? 'approved' : 'rejected'
            ]);

            // Log activity
            \App\Models\ActivityLog::create([
                'user_id' => $user->id,
                'activity_type' => $request->approved ? 'approve' : 'reject',
                'module' => 'contra_bon',
                'description' => ($request->approved ? 'Approve' : 'Reject') . ' Contra Bon (GM Finance): ' . $contraBon->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $contraBon->fresh()->toArray(),
            ]);

            $msg = 'Contra Bon berhasil ' . ($request->approved ? 'diapprove' : 'direject');
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }
            return back()->with('success', $msg);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Anda tidak berhak melakukan approval pada tahap ini'], 403);
        }
        return back()->with('error', 'Anda tidak berhak melakukan approval pada tahap ini');
    }

    private function sendNotification($userIds, $type, $title, $message, $url) {
        $now = now();
        $data = [];
        foreach ($userIds as $uid) {
            $data[] = [
                'user_id' => $uid,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'is_read' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        \DB::table('notifications')->insert($data);
    }

    // API: Get approved Good Receives with PO, supplier, and items (with PO price)
    public function getApprovedGoodReceives()
    {
        $goodReceives = \DB::table('food_good_receives as gr')
            ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->join('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->join('pr_foods as pr', 'po.pr_food_id', '=', 'pr.id')
            ->where('gr.status', 'approved')
            ->select('gr.id', 'gr.gr_number', 'gr.receive_date', 'gr.po_id', 'po.number as po_number', 'pr.pr_number as pr_number', 's.name as supplier_name')
            ->orderByDesc('gr.receive_date')
            ->get();

        $result = [];
        foreach ($goodReceives as $gr) {
            $items = \DB::table('food_good_receive_items as gri')
                ->join('items as i', 'gri.item_id', '=', 'i.id')
                ->join('units as u', 'gri.unit_id', '=', 'u.id')
                ->join('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
                ->where('gri.good_receive_id', $gr->id)
                ->select(
                    'gri.id',
                    'gri.item_id',
                    'i.name as item_name',
                    'gri.unit_id',
                    'u.name as unit_name',
                    'gri.qty_received',
                    'poi.price as po_price'
                )
                ->get();
            $result[] = [
                'id' => $gr->id,
                'gr_number' => $gr->gr_number,
                'receive_date' => $gr->receive_date,
                'po_id' => $gr->po_id,
                'po_number' => $gr->po_number,
                'pr_number' => $gr->pr_number,
                'supplier_name' => $gr->supplier_name,
                'items' => $items,
            ];
        }
        return response()->json($result);
    }

    // API: Get PO list with approved GR for Contra Bon create
    public function getPOWithApprovedGR()
    {
        try {
            // Ambil semua po_id yang sudah ada di contra bon
            $usedPOs = \DB::table('food_contra_bons')
                ->where('source_type', 'purchase_order')
                ->whereNotNull('po_id')
                ->pluck('po_id')
                ->toArray();

            $poWithGR = \DB::table('purchase_order_foods as po')
                ->join('food_good_receives as gr', 'gr.po_id', '=', 'po.id')
                ->join('suppliers as s', 'po.supplier_id', '=', 's.id')
                ->join('users as po_creator', 'po.created_by', '=', 'po_creator.id')
                ->join('users as gr_receiver', 'gr.received_by', '=', 'gr_receiver.id')
                ->whereNotIn('po.id', $usedPOs)
                ->select(
                    'po.id as po_id',
                    'po.number as po_number',
                    'po.date as po_date',
                    'po_creator.nama_lengkap as po_creator_name',
                    'gr.id as gr_id',
                    'gr.gr_number',
                    'gr.receive_date as gr_date',
                    'gr_receiver.nama_lengkap as gr_receiver_name',
                    's.id as supplier_id',
                    's.name as supplier_name'
                )
                ->orderByDesc('gr.receive_date')
                ->get();

            $result = [];
            foreach ($poWithGR as $row) {
                $items = \DB::table('food_good_receive_items as gri')
                    ->join('items as i', 'gri.item_id', '=', 'i.id')
                    ->join('units as u', 'gri.unit_id', '=', 'u.id')
                    ->join('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
                    ->where('gri.good_receive_id', $row->gr_id)
                    ->select(
                        'gri.id',
                        'gri.item_id',
                        'gri.po_item_id',
                        'i.name as item_name',
                        'gri.unit_id',
                        'u.name as unit_name',
                        'gri.qty_received',
                        'poi.price as po_price'
                    )
                    ->get();
                $result[] = [
                    'po_id' => $row->po_id,
                    'po_number' => $row->po_number,
                    'po_date' => $row->po_date,
                    'po_creator_name' => $row->po_creator_name,
                    'gr_id' => $row->gr_id,
                    'gr_number' => $row->gr_number,
                    'gr_date' => $row->gr_date,
                    'gr_receiver_name' => $row->gr_receiver_name,
                    'supplier_id' => $row->supplier_id,
                    'supplier_name' => $row->supplier_name,
                    'items' => $items,
                ];
            }
            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Error in getPOWithApprovedGR: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal mengambil data PO/GR: ' . $e->getMessage()], 500);
        }
    }

    // API: Get Retail Food with contra bon payment method
    public function getRetailFoodContraBon()
    {
        try {
            // Ambil semua retail_food_id yang sudah ada di contra bon
            $usedRetailFoods = \DB::table('food_contra_bons')
                ->where('source_type', 'retail_food')
                ->whereNotNull('source_id')
                ->pluck('source_id')
                ->toArray();

            $retailFoods = \DB::table('retail_food as rf')
                ->join('suppliers as s', 'rf.supplier_id', '=', 's.id')
                ->join('users as creator', 'rf.created_by', '=', 'creator.id')
                ->where('rf.payment_method', 'contra_bon')
                ->where('rf.status', 'approved')
                ->whereNotIn('rf.id', $usedRetailFoods)
                ->select(
                    'rf.id as retail_food_id',
                    'rf.retail_number',
                    'rf.transaction_date',
                    'rf.total_amount',
                    'rf.notes',
                    's.id as supplier_id',
                    's.name as supplier_name',
                    'creator.nama_lengkap as creator_name'
                )
                ->orderByDesc('rf.transaction_date')
                ->get();

            $result = [];
            foreach ($retailFoods as $row) {
                $items = \DB::table('retail_food_items as rfi')
                    ->join('items as i', 'rfi.item_id', '=', 'i.id')
                    ->join('units as u', 'rfi.unit_id', '=', 'u.id')
                    ->where('rfi.retail_food_id', $row->retail_food_id)
                    ->select(
                        'rfi.id',
                        'rfi.item_id',
                        'i.name as item_name',
                        'rfi.unit_id',
                        'u.name as unit_name',
                        'rfi.qty',
                        'rfi.price'
                    )
                    ->get();
                $result[] = [
                    'retail_food_id' => $row->retail_food_id,
                    'retail_number' => $row->retail_number,
                    'transaction_date' => $row->transaction_date,
                    'total_amount' => $row->total_amount,
                    'notes' => $row->notes,
                    'supplier_id' => $row->supplier_id,
                    'supplier_name' => $row->supplier_name,
                    'creator_name' => $row->creator_name,
                    'items' => $items,
                ];
            }
            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Error in getRetailFoodContraBon: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal mengambil data Retail Food: ' . $e->getMessage()], 500);
        }
    }

    public function edit($id)
    {
        $contraBon = ContraBon::with([
            'supplier',
            'purchaseOrder',
            'items.item',
            'items.unit',
            'creator',
            'approver',
            'financeManager',
            'gmFinance'
        ])->findOrFail($id);

        return inertia('ContraBon/Form', [
            'contraBon' => $contraBon
        ]);
    }

    public function destroy($id)
    {
        $contraBon = ContraBon::findOrFail($id);
        $contraBon->delete();
        return redirect()->route('contra-bons.index')->with('success', 'Contra Bon berhasil dihapus');
    }
} 