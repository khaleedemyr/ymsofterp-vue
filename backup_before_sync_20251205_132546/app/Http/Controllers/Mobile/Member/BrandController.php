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
            // Query dari tbl_data_outlet dengan kondisi status='A', is_outlet=1, dan is_fc=0 (exclude franchise outlets)
            $brands = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->where('is_outlet', 1)
                ->where('is_fc', 0)
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
                            ->filter(function ($gallery) {
                                // Only include galleries with valid image
                                return $gallery->image && !empty($gallery->image);
                            })
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
                    
                    // Process facility data for list (simplified)
                    $facilityCount = 0;
                    if ($brandData && $brandData->facility) {
                        $facilityData = is_string($brandData->facility) 
                            ? json_decode($brandData->facility, true) 
                            : $brandData->facility;
                        if (is_array($facilityData)) {
                            $facilityCount = count($facilityData);
                        }
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
                        'tripadvisor_link' => $brandData ? $brandData->tripadvisor_link : null,
                        'facility_count' => $facilityCount,
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
                ->where('is_fc', 0)
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
            if ($brandData) {
                // Reload galleries to ensure they're loaded
                $brandData->load('galleries');
                
                \Log::info('BrandController show - Brand data loaded', [
                    'brand_id' => $brandData->id,
                    'outlet_id' => $id,
                    'has_galleries_relation' => $brandData->relationLoaded('galleries'),
                    'galleries_count' => $brandData->galleries ? $brandData->galleries->count() : 0,
                ]);
                
                if ($brandData->galleries && $brandData->galleries->count() > 0) {
                    \Log::info('BrandController show - Galleries found', [
                        'brand_id' => $brandData->id,
                        'outlet_id' => $id,
                        'galleries_count' => $brandData->galleries->count(),
                        'galleries' => $brandData->galleries->map(function($g) {
                            return ['id' => $g->id, 'image' => $g->image, 'sort_order' => $g->sort_order];
                        })->toArray()
                    ]);
                    
                    $galleryImages = $brandData->galleries
                        ->filter(function ($gallery) {
                            // Only include galleries with valid image (not null and not empty after trim)
                            $hasImage = $gallery->image && !empty(trim($gallery->image));
                            if (!$hasImage) {
                                \Log::warning('BrandController show - Gallery filtered out (no image)', [
                                    'gallery_id' => $gallery->id,
                                    'brand_id' => $brandData->id,
                                    'image_value' => $gallery->image,
                                ]);
                            }
                            return $hasImage;
                        })
                        ->sortBy('sort_order')
                        ->map(function ($gallery) {
                            $imageUrl = null;
                            if ($gallery->image) {
                                $imageUrl = 'https://ymsofterp.com/storage/' . ltrim($gallery->image, '/');
                            }
                            return [
                                'id' => $gallery->id,
                                'image' => $imageUrl,
                                'sort_order' => $gallery->sort_order ?? 0,
                            ];
                        })
                        ->filter(function ($item) {
                            // Also filter out items with null image URL after processing
                            return !empty($item['image']);
                        })
                        ->values()
                        ->toArray();
                    
                    \Log::info('BrandController show - Gallery images processed', [
                        'brand_id' => $brandData->id,
                        'outlet_id' => $id,
                        'gallery_images_count' => count($galleryImages),
                        'gallery_images' => $galleryImages
                    ]);
                } else {
                    \Log::info('BrandController show - No galleries found for brand', [
                        'brand_id' => $brandData->id,
                        'outlet_id' => $id,
                    ]);
                }
            } else {
                \Log::info('BrandController show - No brand data found', [
                    'outlet_id' => $id,
                ]);
            }

            // Process facility data
            $facilities = [];
            if ($brandData && $brandData->facility) {
                $facilityData = is_string($brandData->facility) 
                    ? json_decode($brandData->facility, true) 
                    : $brandData->facility;
                
                \Log::info('BrandController show - facilityData: ' . json_encode($facilityData));
                
                if (is_array($facilityData)) {
                    $facilityInfo = [
                        'wifi' => [
                            'name' => 'Speed Wi-fi',
                            'description' => 'High-speed internet access is available to support your connectivity throughout your visit.',
                            'image' => 'https://ymsofterp.com/images/WIFI.png'
                        ],
                        'smoking_area' => [
                            'name' => 'Smoking Area',
                            'description' => 'Our designated smoking area offers comfort for guests who wish to relax without disturbing others',
                            'image' => 'https://ymsofterp.com/images/SMOKINGAREA.png'
                        ],
                        'mushola' => [
                            'name' => 'Mushola',
                            'description' => 'A clean and comfortable prayer room is available to ensure you can worship in peace',
                            'image' => 'https://ymsofterp.com/images/MUSHOLA.png'
                        ],
                        'meeting_room' => [
                            'name' => 'Meeting Room',
                            'description' => 'A comfortable meeting room, suitable for business needs or private events.',
                            'image' => 'https://ymsofterp.com/images/MEETINGROOM.png'
                        ],
                        'valet_parking' => [
                            'name' => 'Free Valet Parking',
                            'description' => 'Enjoy complimentary parking convenience with our valet service',
                            'image' => 'https://ymsofterp.com/images/VALET.png'
                        ],
                    ];
                    
                    foreach ($facilityData as $facilityKey) {
                        if (isset($facilityInfo[$facilityKey])) {
                            $facilities[] = array_merge(['key' => $facilityKey], $facilityInfo[$facilityKey]);
                        }
                    }
                    
                    \Log::info('BrandController show - processed facilities: ' . json_encode($facilities));
                }
            }
            
            \Log::info('BrandController show - final facilities count: ' . count($facilities));

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
                    'tripadvisor_link' => $brandData ? $brandData->tripadvisor_link : null,
                    'facility' => $facilities,
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

