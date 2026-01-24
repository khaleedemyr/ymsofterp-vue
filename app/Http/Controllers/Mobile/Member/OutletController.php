<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OutletController extends Controller
{
    /**
     * Get nearest outlets based on member location
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNearestOutlets(Request $request)
    {
        try {
            // Validate request parameters
            $request->validate([
                'lat' => 'required|numeric|between:-90,90',
                'long' => 'required|numeric|between:-180,180',
                'limit' => 'nullable|integer|min:1|max:20',
            ]);

            $memberLat = (float) $request->input('lat');
            $memberLong = (float) $request->input('long');
            $limit = (int) ($request->input('limit', 5));

            Log::info('Get Nearest Outlets', [
                'lat' => $memberLat,
                'long' => $memberLong,
                'limit' => $limit,
            ]);

            // Get outlets with filter: status='A' AND is_outlet=1 AND is_fc=0
            // Calculate distance using Haversine formula
            // Note: lat and long are stored as varchar(50), so we need to cast them
            // Note: 'long' is a reserved keyword in MySQL, so we need to escape it with backticks
            // Join with brands table for circular logo (via tbl_data_outlet.id_brand)
            // Join with member_apps_brands for background logo (via outlet_id)
            // We'll use raw query to handle potential invalid values in lat/long columns
            $outlets = DB::select("
                SELECT 
                    o.id_outlet,
                    o.nama_outlet,
                    o.lokasi,
                    o.lat,
                    o.`long`,
                    o.brand,
                    o.id_brand,
                    b.logo AS brand_logo,
                    mab.logo AS background_logo,
                    (
                        6371 * acos(
                            cos(radians(?)) * 
                            cos(radians(CAST(o.lat AS DECIMAL(10, 8)))) * 
                            cos(radians(CAST(o.`long` AS DECIMAL(10, 8))) - radians(?)) + 
                            sin(radians(?)) * 
                            sin(radians(CAST(o.lat AS DECIMAL(10, 8))))
                        )
                    ) AS distance
                FROM tbl_data_outlet o
                LEFT JOIN brands b ON o.id_brand = b.id
                LEFT JOIN member_apps_brands mab ON o.id_outlet = mab.outlet_id AND mab.is_active = 1
                WHERE o.status = 'A'
                    AND o.is_outlet = 1
                    AND o.is_fc = 0
                    AND o.lat IS NOT NULL
                    AND o.`long` IS NOT NULL
                    AND o.lat != ''
                    AND o.`long` != ''
                    AND CAST(o.lat AS DECIMAL(10, 8)) BETWEEN -90 AND 90
                    AND CAST(o.`long` AS DECIMAL(10, 8)) BETWEEN -180 AND 180
                HAVING distance IS NOT NULL
                ORDER BY distance ASC
                LIMIT ?
            ", [$memberLat, $memberLong, $memberLat, $limit]);

            // Format response
            $formattedOutlets = collect($outlets)->map(function ($outlet) {
                // Build logo URLs
                // brand_logo: from brands table (for circular logo in popup)
                $brandLogoUrl = null;
                if (!empty($outlet->brand_logo)) {
                    $logoPath = ltrim($outlet->brand_logo, '/');
                    $brandLogoUrl = 'https://ymsofterp.com/storage/' . $logoPath;
                }
                
                // background_logo: from member_apps_brands table (for background image in popup)
                $backgroundLogoUrl = null;
                if (!empty($outlet->background_logo)) {
                    $logoPath = ltrim($outlet->background_logo, '/');
                    $backgroundLogoUrl = 'https://ymsofterp.com/storage/' . $logoPath;
                }
                
                return [
                    'id_outlet' => $outlet->id_outlet,
                    'nama_outlet' => $outlet->nama_outlet,
                    'alamat' => $outlet->lokasi, // Map lokasi to alamat for frontend compatibility
                    'lat' => $outlet->lat,
                    'long' => $outlet->long,
                    'brand' => $outlet->brand,
                    'id_brand' => $outlet->id_brand,
                    'brand_logo' => $brandLogoUrl, // Full URL to brand logo from brands table (for circular logo)
                    'background_logo' => $backgroundLogoUrl, // Full URL to background logo from member_apps_brands (for background image)
                    'distance' => round((float) $outlet->distance, 2), // Distance in kilometers
                ];
            });

            Log::info('Nearest Outlets Found', [
                'count' => $formattedOutlets->count(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $formattedOutlets->toArray()
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Get Nearest Outlets Validation Error', [
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Get Nearest Outlets Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get nearest outlets',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
