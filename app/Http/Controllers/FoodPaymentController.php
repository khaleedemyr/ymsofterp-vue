<?php

namespace App\Http\Controllers;

use App\Models\FoodPayment;
use App\Models\FoodPaymentContraBon;
use App\Models\ContraBon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FoodPaymentController extends Controller
{
    // List all food payments
    public function index(Request $request)
    {
        $query = FoodPayment::with(['supplier', 'creator', 'financeManager', 'contraBons'])->orderByDesc('created_at');

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('number', 'like', "%$search%")
                  ->orWhereHas('supplier', function($q2) use ($search) {
                      $q2->where('name', 'like', "%$search%");
                  })
                  ->orWhere('payment_type', 'like', "%$search%")
                  ->orWhere('total', 'like', "%$search%")
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

        $payments = $query->paginate(10)->withQueryString();

        return inertia('FoodPayment/Index', [
            'payments' => $payments,
            'filters' => $request->only(['search', 'status', 'from', 'to']),
        ]);
    }

    // Show create form
    public function create()
    {
        return inertia('FoodPayment/Form');
    }

    // Store new food payment
    public function store(Request $request)
    {
        \Log::info('FoodPaymentController@store - Input', $request->all());
        try {
            $validated = $request->validate([
                'date' => 'required|date',
                'payment_type' => 'required|string',
                'supplier_id' => 'required|exists:suppliers,id',
                'contra_bon_ids' => 'required|array|min:1',
                'contra_bon_ids.*' => 'exists:food_contra_bons,id',
                'notes' => 'nullable|string',
                'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            ]);
            \Log::info('FoodPaymentController@store - Validated', $validated);

            DB::beginTransaction();
            // Generate number
            $dateStr = date('Ymd', strtotime($validated['date']));
            $countToday = FoodPayment::whereDate('date', $validated['date'])->count() + 1;
            $number = 'FP-' . $dateStr . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

            // Hitung total
            $contraBons = \App\Models\ContraBon::whereIn('id', $validated['contra_bon_ids'])->get();
            \Log::info('FoodPaymentController@store - ContraBons', $contraBons->toArray());
            $total = $contraBons->sum('total_amount');

            // Upload file jika ada
            $buktiPath = null;
            if ($request->hasFile('bukti_transfer')) {
                $buktiPath = $request->file('bukti_transfer')->store('food_payment_bukti', 'public');
                \Log::info('FoodPaymentController@store - Bukti transfer path', [$buktiPath]);
            }

            // Simpan FoodPayment
            $payment = FoodPayment::create([
                'number' => $number,
                'date' => $validated['date'],
                'supplier_id' => $validated['supplier_id'],
                'total' => $total,
                'payment_type' => $validated['payment_type'],
                'notes' => $validated['notes'] ?? null,
                'bukti_transfer_path' => $buktiPath,
                'status' => 'paid',
                'created_by' => Auth::id(),
            ]);
            \Log::info('FoodPaymentController@store - FoodPayment created', $payment->toArray());

            // Simpan relasi ke contra bon
            foreach ($contraBons as $cb) {
                FoodPaymentContraBon::create([
                    'food_payment_id' => $payment->id,
                    'contra_bon_id' => $cb->id,
                ]);
                // Update status contra bon menjadi paid
                $cb->status = 'paid';
                $cb->save();
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('FoodPaymentController@store - Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Show detail food payment
    public function show($id)
    {
        $payment = FoodPayment::with(['supplier', 'creator', 'financeManager', 'contraBons.purchaseOrder', 'contraBons.retailFood'])->findOrFail($id);
        
        // Transform contra bons to include source type and outlet information
        $payment->contra_bons = $payment->contra_bons->map(function($contraBon) {
            $sourceTypeDisplay = 'Unknown';
            $outletNames = [];
            
            if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
                if ($contraBon->purchaseOrder->source_type === 'pr_foods') {
                    $sourceTypeDisplay = 'PR Foods';
                } elseif ($contraBon->purchaseOrder->source_type === 'ro_supplier') {
                    $sourceTypeDisplay = 'RO Supplier';
                    // Get outlet names for RO Supplier
                    $outletData = \DB::table('food_floor_orders as fo')
                        ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                        ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                        ->where('poi.purchase_order_food_id', $contraBon->purchaseOrder->id)
                        ->select('o.nama_outlet')
                        ->distinct()
                        ->get();
                    
                    $outletNames = $outletData->pluck('nama_outlet')->filter()->unique()->toArray();
                }
            } elseif ($contraBon->source_type === 'retail_food') {
                $sourceTypeDisplay = 'Retail Food';
                // Get outlet name for Retail Food
                if ($contraBon->retailFood) {
                    $outletNames = [$contraBon->retailFood->outlet_name];
                }
            }
            
            $contraBon->source_type_display = $sourceTypeDisplay;
            $contraBon->outlet_names = $outletNames;
            
            return $contraBon;
        });
        
        return inertia('FoodPayment/Show', [
            'payment' => $payment
        ]);
    }

    // Approve payment (Finance Manager)
    public function approve(Request $request, $id)
    {
        // Logic approval
        return response()->json(['success' => true]);
    }

    // API: Get contra bon yang belum dibayar
    public function getContraBonUnpaid()
    {
        $paidContraBonIds = FoodPaymentContraBon::pluck('contra_bon_id')->toArray();
        $contraBons = ContraBon::with(['supplier', 'purchaseOrder', 'retailFood'])
            ->where('status', 'approved')
            ->whereNotIn('id', $paidContraBonIds)
            ->get();
        
        // Transform data to include source type and outlet information
        $contraBons = $contraBons->map(function($contraBon) {
            $sourceTypeDisplay = 'Unknown';
            $outletNames = [];
            
            if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
                if ($contraBon->purchaseOrder->source_type === 'pr_foods') {
                    $sourceTypeDisplay = 'PR Foods';
                } elseif ($contraBon->purchaseOrder->source_type === 'ro_supplier') {
                    $sourceTypeDisplay = 'RO Supplier';
                    // Get outlet names for RO Supplier
                    $outletData = \DB::table('food_floor_orders as fo')
                        ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                        ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                        ->where('poi.purchase_order_food_id', $contraBon->purchaseOrder->id)
                        ->select('o.nama_outlet')
                        ->distinct()
                        ->get();
                    
                    $outletNames = $outletData->pluck('nama_outlet')->filter()->unique()->toArray();
                }
            } elseif ($contraBon->source_type === 'retail_food') {
                $sourceTypeDisplay = 'Retail Food';
                // Get outlet name for Retail Food
                if ($contraBon->retailFood) {
                    $outletNames = [$contraBon->retailFood->outlet_name];
                }
            }
            
            $contraBon->source_type_display = $sourceTypeDisplay;
            $contraBon->outlet_names = $outletNames;
            
            return $contraBon;
        });
        
        return response()->json($contraBons);
    }

    public function edit($id)
    {
        $payment = FoodPayment::with(['supplier', 'creator', 'financeManager', 'contraBons'])->findOrFail($id);
        return inertia('FoodPayment/Form', [
            'payment' => $payment
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'date' => 'required|date',
                'payment_type' => 'required|string',
                'supplier_id' => 'required|exists:suppliers,id',
                'contra_bon_ids' => 'required|array|min:1',
                'contra_bon_ids.*' => 'exists:food_contra_bons,id',
                'notes' => 'nullable|string',
                'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            ]);

            DB::beginTransaction();

            $payment = FoodPayment::findOrFail($id);
            
            // Hitung total dari contra bon yang dipilih
            $contraBons = ContraBon::whereIn('id', $validated['contra_bon_ids'])->get();
            $total = $contraBons->sum('total_amount');

            // Upload file jika ada
            $buktiPath = $payment->bukti_transfer_path;
            if ($request->hasFile('bukti_transfer')) {
                // Hapus file lama jika ada
                if ($buktiPath) {
                    Storage::disk('public')->delete($buktiPath);
                }
                $buktiPath = $request->file('bukti_transfer')->store('food_payment_bukti', 'public');
            }

            // Update FoodPayment
            $payment->update([
                'date' => $validated['date'],
                'supplier_id' => $validated['supplier_id'],
                'total' => $total,
                'payment_type' => $validated['payment_type'],
                'notes' => $validated['notes'] ?? null,
                'bukti_transfer_path' => $buktiPath,
            ]);

            // Hapus relasi lama
            FoodPaymentContraBon::where('food_payment_id', $payment->id)->delete();

            // Update status contra bon lama menjadi approved
            ContraBon::whereIn('id', $payment->contraBons->pluck('id'))
                ->update(['status' => 'approved']);

            // Buat relasi baru
            foreach ($contraBons as $cb) {
                FoodPaymentContraBon::create([
                    'food_payment_id' => $payment->id,
                    'contra_bon_id' => $cb->id,
                ]);
                // Update status contra bon baru menjadi paid
                $cb->status = 'paid';
                $cb->save();
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('FoodPaymentController@update - Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $payment = FoodPayment::findOrFail($id);

            // Update status contra bon menjadi approved
            ContraBon::whereIn('id', $payment->contraBons->pluck('id'))
                ->update(['status' => 'approved']);

            // Hapus relasi
            FoodPaymentContraBon::where('food_payment_id', $payment->id)->delete();

            // Hapus file bukti transfer jika ada
            if ($payment->bukti_transfer_path) {
                Storage::disk('public')->delete($payment->bukti_transfer_path);
            }

            // Hapus payment
            $payment->delete();

            DB::commit();
            // Redirect ke index dengan pesan sukses
            return redirect()->route('food-payments.index')->with('success', 'Food Payment berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus Food Payment: ' . $e->getMessage());
        }
    }
} 