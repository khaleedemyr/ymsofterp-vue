<?php

namespace App\Http\Controllers;

use App\Models\RetailNonFood;
use App\Models\ChartOfAccount;
use App\Models\Jurnal;
use App\Models\JurnalGlobal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RetailNonFoodPaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user()->load('outlet');
        $userOutletId = $user->id_outlet;

        // Get retail non food dengan payment_method = cash (include yang sudah ada jurnal)
        $query = RetailNonFood::with([
                'outlet', 
                'creator', 
                'supplier', 
                'categoryBudget', 
                'items'
            ])
            ->where('payment_method', 'cash')
            ->where('status', 'approved')
            ->orderByDesc('transaction_date');

        // Filter by outlet
        if ($userOutletId != 1) {
            $query->where('outlet_id', $userOutletId);
        }

        // Apply search filter
        $search = $request->get('search', '');
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('retail_number', 'like', "%{$search}%")
                  ->orWhereHas('outlet', function($outletQuery) use ($search) {
                      $outletQuery->where('nama_outlet', 'like', "%{$search}%");
                  })
                  ->orWhereHas('supplier', function($supplierQuery) use ($search) {
                      $supplierQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Apply date filter
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }

        $retailNonFoods = $query->paginate(20)->withQueryString();

        // Manual load jurnal untuk setiap retail non food untuk avoid collation issue
        $retailNonFoods->getCollection()->transform(function ($rnf) {
            $rnf->jurnal = \App\Models\Jurnal::where('reference_type', 'retail_non_food')
                ->where('reference_id', $rnf->id)
                ->with(['coaDebit', 'coaKredit'])
                ->orderBy('created_at', 'desc')
                ->limit(1)
                ->get();
            return $rnf;
        });

        // Get COAs for selection
        $coas = ChartOfAccount::where('is_active', 1)
            ->orderBy('code')
            ->get()
            ->map(function($coa) {
                return [
                    'id' => $coa->id,
                    'code' => $coa->code,
                    'name' => $coa->name,
                    'display_name' => $coa->code . ' - ' . $coa->name
                ];
            });

        return Inertia::render('RetailNonFoodPayment/Index', [
            'user' => $user,
            'retailNonFoods' => $retailNonFoods,
            'coas' => $coas,
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'retail_non_food_id' => 'required|exists:retail_non_food,id',
                'coa_id' => 'required|exists:chart_of_accounts,id',
            ]);

            DB::beginTransaction();

            $retailNonFood = RetailNonFood::with('outlet')->findOrFail($request->retail_non_food_id);

            // Check if already has jurnal
            if ($retailNonFood->jurnal_created) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Transaksi ini sudah memiliki jurnal'
                ], 422);
            }

            // Create Jurnal
            $this->createJurnalForRetailNonFood($retailNonFood, $request->coa_id);

            // Mark as jurnal_created
            $retailNonFood->update(['jurnal_created' => true]);

            DB::commit();

            return response()->json([
                'message' => 'Jurnal berhasil dibuat untuk transaksi ' . $retailNonFood->retail_number,
                'data' => $retailNonFood
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('RETAIL_NON_FOOD_PAYMENT: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'message' => 'Gagal membuat jurnal: ' . $e->getMessage()
            ], 500);
        }
    }

    private function createJurnalForRetailNonFood($retailNonFood, $coaId)
    {
        $user = auth()->user();
        $userId = $user->id;
        $currentDate = now();

        // Generate nomor jurnal
        $prefix = 'JU';
        $date = date('Ymd');
        $lastJurnal = Jurnal::where('no_jurnal', 'like', $prefix . $date . '%')
            ->orderBy('no_jurnal', 'desc')
            ->first();
        
        if ($lastJurnal) {
            $sequence = (int) substr($lastJurnal->no_jurnal, -4) + 1;
        } else {
            $sequence = 1;
        }
        
        $noJurnal = $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);

        $description = "Payment Retail Non Food - " . $retailNonFood->retail_number . " - " . ($retailNonFood->outlet ? $retailNonFood->outlet->nama_outlet : 'Unknown');

        // Create Jurnal Entry - Payment to Vendor (Kas Outlet keluar)
        // Debit: Expense (COA user pilih)
        // Kredit: Kas Outlet (54)
        Jurnal::create([
            'no_jurnal' => $noJurnal,
            'tanggal' => $retailNonFood->transaction_date,
            'tanggal_jurnal' => $retailNonFood->transaction_date,
            'coa_debit_id' => $coaId, // Expense
            'coa_kredit_id' => 54, // Kas Outlet
            'jumlah_debit' => $retailNonFood->total_amount,
            'jumlah_kredit' => $retailNonFood->total_amount,
            'nominal' => $retailNonFood->total_amount,
            'keterangan' => $description,
            'outlet_id' => $retailNonFood->outlet_id,
            'reference_type' => 'retail_non_food',
            'reference_id' => $retailNonFood->id,
            'status' => 'posted',
            'posted_at' => $currentDate,
            'posted_by' => $userId,
            'created_by' => $userId,
        ]);

        // Create Jurnal Global
        JurnalGlobal::create([
            'no_jurnal' => $noJurnal,
            'tanggal' => $retailNonFood->transaction_date,
            'tanggal_jurnal' => $retailNonFood->transaction_date,
            'coa_debit_id' => $coaId, // Expense
            'coa_kredit_id' => 54, // Kas Outlet
            'jumlah_debit' => $retailNonFood->total_amount,
            'jumlah_kredit' => $retailNonFood->total_amount,
            'nominal' => $retailNonFood->total_amount,
            'keterangan' => $description,
            'outlet_id' => $retailNonFood->outlet_id,
            'reference_type' => 'retail_non_food',
            'reference_id' => $retailNonFood->id,
            'status' => 'posted',
            'posted_at' => $currentDate,
            'posted_by' => $userId,
            'created_by' => $userId,
        ]);

        \Log::info('RETAIL_NON_FOOD_PAYMENT: Jurnal created', [
            'retail_non_food_id' => $retailNonFood->id,
            'retail_number' => $retailNonFood->retail_number,
            'no_jurnal' => $noJurnal,
            'coa_id' => $coaId,
            'amount' => $retailNonFood->total_amount,
            'outlet_id' => $retailNonFood->outlet_id,
        ]);
    }

    public function rollback(Request $request)
    {
        try {
            $request->validate([
                'retail_non_food_id' => 'required|exists:retail_non_food,id',
            ]);

            DB::beginTransaction();

            $retailNonFood = RetailNonFood::findOrFail($request->retail_non_food_id);

            // Check if has jurnal
            if (!$retailNonFood->jurnal_created) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Transaksi ini belum memiliki jurnal'
                ], 422);
            }

            // Delete jurnal entries
            $deletedJurnal = Jurnal::where('reference_type', 'retail_non_food')
                ->where('reference_id', $retailNonFood->id)
                ->delete();

            $deletedJurnalGlobal = JurnalGlobal::where('reference_type', 'retail_non_food')
                ->where('reference_id', $retailNonFood->id)
                ->delete();

            // Mark as not jurnal_created
            $retailNonFood->update(['jurnal_created' => false]);

            DB::commit();

            \Log::info('RETAIL_NON_FOOD_PAYMENT: Jurnal rollback', [
                'retail_non_food_id' => $retailNonFood->id,
                'retail_number' => $retailNonFood->retail_number,
                'deleted_jurnal' => $deletedJurnal,
                'deleted_jurnal_global' => $deletedJurnalGlobal,
            ]);

            return response()->json([
                'message' => 'Jurnal berhasil di-rollback untuk transaksi ' . $retailNonFood->retail_number,
                'data' => $retailNonFood
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('RETAIL_NON_FOOD_PAYMENT_ROLLBACK: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'message' => 'Gagal rollback jurnal: ' . $e->getMessage()
            ], 500);
        }
    }
}
