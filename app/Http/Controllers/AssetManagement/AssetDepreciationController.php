<?php

namespace App\Http\Controllers\AssetManagement;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\AssetDepreciationHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetDepreciationController extends Controller
{
    /**
     * Show depreciation for an asset
     */
    public function show($assetId)
    {
        $asset = Asset::with(['category', 'currentOutlet', 'depreciation'])->findOrFail($assetId);
        
        $depreciation = $asset->depreciation;
        
        // Calculate years used if purchase date exists
        $yearsUsed = 0;
        if ($asset->purchase_date) {
            $yearsUsed = Carbon::parse($asset->purchase_date)->diffInYears(now());
        }

        return Inertia::render('AssetManagement/Depreciations/Show', [
            'asset' => $asset,
            'depreciation' => $depreciation,
            'yearsUsed' => $yearsUsed,
        ]);
    }

    /**
     * Calculate depreciation for an asset
     */
    public function calculate($assetId, Request $request)
    {
        $asset = Asset::findOrFail($assetId);

        $validator = Validator::make($request->all(), [
            'purchase_price' => 'required|numeric|min:0',
            'useful_life' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $purchasePrice = $request->purchase_price;
            $usefulLife = $request->useful_life;
            
            // Calculate depreciation rate
            $depreciationRate = 1 / $usefulLife;
            
            // Calculate annual depreciation
            $annualDepreciation = $purchasePrice / $usefulLife;
            
            // Calculate years used
            $yearsUsed = 0;
            if ($asset->purchase_date) {
                $yearsUsed = Carbon::parse($asset->purchase_date)->diffInYears(now());
            }
            
            // Calculate accumulated depreciation
            $accumulatedDepreciation = $annualDepreciation * $yearsUsed;
            
            // Calculate current value
            $currentValue = max(0, $purchasePrice - $accumulatedDepreciation);

            // Create or update depreciation record
            $depreciation = AssetDepreciation::updateOrCreate(
                ['asset_id' => $assetId],
                [
                    'purchase_price' => $purchasePrice,
                    'useful_life' => $usefulLife,
                    'depreciation_method' => 'Straight-Line',
                    'depreciation_rate' => $depreciationRate,
                    'annual_depreciation' => $annualDepreciation,
                    'current_value' => $currentValue,
                    'accumulated_depreciation' => $accumulatedDepreciation,
                    'last_calculated_date' => now()->toDateString(),
                ]
            );

            // Create history record
            AssetDepreciationHistory::create([
                'asset_id' => $assetId,
                'calculation_date' => now()->toDateString(),
                'purchase_price' => $purchasePrice,
                'useful_life' => $usefulLife,
                'depreciation_amount' => $annualDepreciation,
                'accumulated_depreciation' => $accumulatedDepreciation,
                'current_value' => $currentValue,
                'years_used' => $yearsUsed,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Depreciation calculated successfully',
                'data' => $depreciation->fresh(),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate depreciation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get depreciation history for an asset
     */
    public function history($assetId)
    {
        $asset = Asset::with(['category', 'currentOutlet'])->findOrFail($assetId);
        
        $history = AssetDepreciationHistory::where('asset_id', $assetId)
            ->orderBy('calculation_date', 'desc')
            ->get();

        return Inertia::render('AssetManagement/Depreciations/History', [
            'asset' => $asset,
            'history' => $history,
        ]);
    }
}

