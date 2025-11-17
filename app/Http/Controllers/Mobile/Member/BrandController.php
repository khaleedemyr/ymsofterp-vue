<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsBrand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller
{
    /**
     * Get all active brands/outlets
     */
    public function index()
    {
        try {
            // Query dari tbl_data_outlet dengan kondisi status='A' dan is_outlet=1
            $brands = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->where('is_outlet', 1)
                ->orderBy('nama_outlet', 'asc')
                ->get()
                ->map(function ($brand) {
                    // Convert object to array untuk akses yang lebih aman
                    $brandArray = (array) $brand;
                    
                    // Handle kolom 'long' yang merupakan reserved word
                    $longValue = null;
                    if (isset($brandArray['long'])) {
                        $longValue = $brandArray['long'];
                    }
                    
                    // Get brand data from member_apps_brands
                    $outletId = $brandArray['id_outlet'] ?? 0;
                    $brandData = MemberAppsBrand::where('outlet_id', $outletId)
                        ->where('is_active', true)
                        ->with('galleries')
                        ->first();
                    
                    // Build logo URL
                    $logoUrl = null;
                    if ($brandData && $brandData->logo) {
                        $logoUrl = 'https://ymsofterp.com/storage/' . ltrim($brandData->logo, '/');
                    }
                    
                    // Build PDF URLs
                    $pdfMenuUrl = null;
                    if ($brandData && $brandData->pdf_menu) {
                        $pdfMenuUrl = 'https://ymsofterp.com/storage/' . ltrim($brandData->pdf_menu, '/');
                    }
                    
                    $pdfNewDiningUrl = null;
                    if ($brandData && $brandData->pdf_new_dining_experience) {
                        $pdfNewDiningUrl = 'https://ymsofterp.com/storage/' . ltrim($brandData->pdf_new_dining_experience, '/');
                    }
                    
                    // Build gallery images
                    $galleryImages = [];
                    if ($brandData && $brandData->galleries) {
                        $galleryImages = $brandData->galleries
                            ->sortBy('sort_order')
                            ->map(function ($gallery) {
                                $imageUrl = null;
                                if ($gallery->image) {
                                    $imageUrl = 'https://ymsofterp.com/storage/' . ltrim($gallery->image, '/');
                                }
                                return [
                                    'id' => $gallery->id,
                                    'image' => $imageUrl,
                                    'sort_order' => $gallery->sort_order,
                                ];
                            })
                            ->values()
                            ->toArray();
                    }
                    
                    return [
                        'id' => $outletId,
                        'name' => $brandArray['nama_outlet'] ?? '',
                        'code' => $brandArray['qr_code'] ?? null,
                        'address' => $brandArray['lokasi'] ?? null,
                        'phone' => null,
                        'email' => null,
                        'image' => null,
                        'description' => $brandArray['keterangan'] ?? null,
                        'lat' => $brandArray['lat'] ?? null,
                        'long' => $longValue,
                        'url_places' => $brandArray['url_places'] ?? null,
                        'logo' => $logoUrl,
                        'pdf_menu' => $pdfMenuUrl,
                        'pdf_new_dining_experience' => $pdfNewDiningUrl,
                        'whatsapp_number' => $brandData ? $brandData->whatsapp_number : null,
                        'gallery' => $galleryImages,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $brands,
                'message' => 'Brands retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve brands: ' . $e->getMessage(),
                'error' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Get brand detail by outlet ID
     */
    public function show($id)
    {
        try {
            $outlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', $id)
                ->where('status', 'A')
                ->where('is_outlet', 1)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Brand not found'
                ], 404);
            }

            $outletArray = (array) $outlet;
            $longValue = isset($outletArray['long']) ? $outletArray['long'] : null;

            // Get brand data from member_apps_brands
            $brandData = MemberAppsBrand::where('outlet_id', $id)
                ->where('is_active', true)
                ->with('galleries')
                ->first();

            // Build logo URL
            $logoUrl = null;
            if ($brandData && $brandData->logo) {
                $logoUrl = 'https://ymsofterp.com/storage/' . ltrim($brandData->logo, '/');
            }

            // Build PDF URLs
            $pdfMenuUrl = null;
            if ($brandData && $brandData->pdf_menu) {
                $pdfMenuUrl = 'https://ymsofterp.com/storage/' . ltrim($brandData->pdf_menu, '/');
            }

            $pdfNewDiningUrl = null;
            if ($brandData && $brandData->pdf_new_dining_experience) {
                $pdfNewDiningUrl = 'https://ymsofterp.com/storage/' . ltrim($brandData->pdf_new_dining_experience, '/');
            }

            // Build gallery images
            $galleryImages = [];
            if ($brandData && $brandData->galleries) {
                $galleryImages = $brandData->galleries
                    ->where('is_active', true)
                    ->sortBy('sort_order')
                    ->map(function ($gallery) {
                        $imageUrl = null;
                        if ($gallery->image) {
                            $imageUrl = 'https://ymsofterp.com/storage/' . ltrim($gallery->image, '/');
                        }
                        return [
                            'id' => $gallery->id,
                            'image' => $imageUrl,
                            'sort_order' => $gallery->sort_order,
                        ];
                    })
                    ->values()
                    ->toArray();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'name' => $outletArray['nama_outlet'] ?? '',
                    'code' => $outletArray['qr_code'] ?? null,
                    'address' => $outletArray['lokasi'] ?? null,
                    'phone' => null,
                    'email' => null,
                    'image' => null,
                    'description' => $outletArray['keterangan'] ?? null,
                    'lat' => $outletArray['lat'] ?? null,
                    'long' => $longValue,
                    'url_places' => $outletArray['url_places'] ?? null,
                    'logo' => $logoUrl,
                    'pdf_menu' => $pdfMenuUrl,
                    'pdf_new_dining_experience' => $pdfNewDiningUrl,
                    'whatsapp_number' => $brandData ? $brandData->whatsapp_number : null,
                    'gallery' => $galleryImages,
                ],
                'message' => 'Brand retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve brand: ' . $e->getMessage(),
                'error' => $e->getTraceAsString()
            ], 500);
        }
    }
}

