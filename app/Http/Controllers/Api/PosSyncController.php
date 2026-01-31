<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PosSyncController extends Controller
{
    /**
     * Check for changes in server data
     * Returns last_updated timestamps for each table
     */
    public function checkChanges(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
                'last_sync' => 'nullable|array', // {table_name: timestamp}
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');
            $lastSync = $request->input('last_sync', []);

            Log::info('POS Sync: Check Changes', [
                'kode_outlet' => $kodeOutlet,
                'last_sync' => $lastSync
            ]);

            // Get outlet ID
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet not found',
                    'data' => []
                ], 404);
            }

            $idOutlet = $outlet->id_outlet;
            $regionId = $outlet->region_id ?? null;

            $changes = [];

            // Check users
            $usersLastUpdated = DB::table('users')
                ->where(function($query) use ($idOutlet) {
                    $query->where('id_outlet', $idOutlet)
                          ->orWhere('email', 'admin@gmail.com');
                })
                ->max('updated_at');
            
            $changes['users'] = [
                'has_changes' => !isset($lastSync['users']) || 
                    ($usersLastUpdated && $usersLastUpdated > $lastSync['users']),
                'last_updated' => $usersLastUpdated
            ];

            // Check categories
            $categoriesLastUpdated = DB::table('categories')
                ->where('status', 'active')
                ->where(function($query) use ($idOutlet) {
                    $query->where('show_pos', '0')
                          ->orWhereExists(function($q) use ($idOutlet) {
                              $q->select(DB::raw(1))
                                ->from('category_outlet')
                                ->whereColumn('category_outlet.category_id', 'categories.id')
                                ->where('category_outlet.outlet_id', $idOutlet);
                          });
                })
                ->max('updated_at');
            
            $changes['categories'] = [
                'has_changes' => !isset($lastSync['categories']) || 
                    ($categoriesLastUpdated && $categoriesLastUpdated > $lastSync['categories']),
                'last_updated' => $categoriesLastUpdated
            ];

            // Check sub_categories
            $subCategoriesLastUpdated = DB::table('sub_categories')
                ->where('status', 'active')
                ->where(function($query) use ($regionId, $idOutlet) {
                    $query->where('show_pos', 0)
                          ->orWhereExists(function($q) use ($regionId, $idOutlet) {
                              $q->select(DB::raw(1))
                                ->from('sub_category_availabilities')
                                ->whereColumn('sub_category_availabilities.sub_category_id', 'sub_categories.id')
                                ->where(function($w) use ($regionId, $idOutlet) {
                                    $w->where(function($w2) use ($regionId) {
                                        $w2->where('availability_type', 'byRegion')
                                           ->where('region_id', $regionId);
                                    })->orWhere(function($w2) use ($idOutlet) {
                                        $w2->where('availability_type', 'byOutlet')
                                           ->where('outlet_id', $idOutlet);
                                    });
                                });
                          });
                })
                ->max('updated_at');
            
            $changes['sub_categories'] = [
                'has_changes' => !isset($lastSync['sub_categories']) || 
                    ($subCategoriesLastUpdated && $subCategoriesLastUpdated > $lastSync['sub_categories']),
                'last_updated' => $subCategoriesLastUpdated
            ];

            // Check units
            $unitsLastUpdated = DB::table('units')
                ->where('status', 'active')
                ->max('updated_at');
            
            $changes['units'] = [
                'has_changes' => !isset($lastSync['units']) || 
                    ($unitsLastUpdated && $unitsLastUpdated > $lastSync['units']),
                'last_updated' => $unitsLastUpdated
            ];

            // Check items (complex query based on availability)
            $itemsLastUpdated = DB::table('items')
                ->where('status', 'active')
                ->where(function($query) use ($regionId, $idOutlet) {
                    $query->whereExists(function($q) use ($regionId, $idOutlet) {
                        $q->select(DB::raw(1))
                          ->from('item_availabilities')
                          ->whereColumn('item_availabilities.item_id', 'items.id')
                          ->where(function($w) use ($regionId, $idOutlet) {
                              $w->where(function($w2) {
                                  $w2->where('availability_type', 'all');
                              })->orWhere(function($w2) use ($regionId) {
                                  $w2->where('availability_type', 'byRegion')
                                     ->where('region_id', $regionId);
                              })->orWhere(function($w2) use ($idOutlet) {
                                  $w2->where('availability_type', 'byOutlet')
                                     ->where('outlet_id', $idOutlet);
                              });
                          });
                    });
                })
                ->max('updated_at');
            
            $changes['items'] = [
                'has_changes' => !isset($lastSync['items']) || 
                    ($itemsLastUpdated && $itemsLastUpdated > $lastSync['items']),
                'last_updated' => $itemsLastUpdated
            ];

            // Check modifiers
            $modifiersLastUpdated = DB::table('modifiers')
                ->max('updated_at');
            
            $changes['modifiers'] = [
                'has_changes' => !isset($lastSync['modifiers']) || 
                    ($modifiersLastUpdated && $modifiersLastUpdated > $lastSync['modifiers']),
                'last_updated' => $modifiersLastUpdated
            ];

            // Check modifier_options
            $modifierOptionsLastUpdated = DB::table('modifier_options')
                ->max('updated_at');
            
            $changes['modifier_options'] = [
                'has_changes' => !isset($lastSync['modifier_options']) || 
                    ($modifierOptionsLastUpdated && $modifierOptionsLastUpdated > $lastSync['modifier_options']),
                'last_updated' => $modifierOptionsLastUpdated
            ];

            // Check promos
            $promosLastUpdated = DB::table('promos')
                ->where('status', 'active')
                ->max('updated_at');
            
            $changes['promos'] = [
                'has_changes' => !isset($lastSync['promos']) || 
                    ($promosLastUpdated && $promosLastUpdated > $lastSync['promos']),
                'last_updated' => $promosLastUpdated
            ];

            // Check payment_types
            $paymentTypesLastUpdated = DB::table('payment_types')
                ->where('status', 'active')
                ->max('updated_at');
            
            $changes['payment_types'] = [
                'has_changes' => !isset($lastSync['payment_types']) || 
                    ($paymentTypesLastUpdated && $paymentTypesLastUpdated > $lastSync['payment_types']),
                'last_updated' => $paymentTypesLastUpdated
            ];

            // Check reservations
            $today = Carbon::today()->toDateString();
            $reservationsLastUpdated = DB::table('reservations')
                ->where('outlet_id', $idOutlet)
                ->where('reservation_date', '>=', $today)
                ->max('updated_at');
            
            $changes['reservations'] = [
                'has_changes' => !isset($lastSync['reservations']) || 
                    ($reservationsLastUpdated && $reservationsLastUpdated > $lastSync['reservations']),
                'last_updated' => $reservationsLastUpdated
            ];

            // Check investors
            $investorsLastUpdated = DB::table('investors')
                ->join('investor_outlet', 'investors.id', '=', 'investor_outlet.investor_id')
                ->where('investor_outlet.outlet_id', $idOutlet)
                ->max('investors.updated_at');
            
            $changes['investors'] = [
                'has_changes' => !isset($lastSync['investors']) || 
                    ($investorsLastUpdated && $investorsLastUpdated > $lastSync['investors']),
                'last_updated' => $investorsLastUpdated
            ];

            // Check officer_checks
            $officerChecksLastUpdated = DB::table('officer_checks')
                ->max('updated_at');
            
            $changes['officer_checks'] = [
                'has_changes' => !isset($lastSync['officer_checks']) || 
                    ($officerChecksLastUpdated && $officerChecksLastUpdated > $lastSync['officer_checks']),
                'last_updated' => $officerChecksLastUpdated
            ];

            // Check retail_food
            $retailFoodLastUpdated = DB::table('retail_food')
                ->where('outlet_id', $idOutlet)
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->max('updated_at');
            
            $changes['retail_food'] = [
                'has_changes' => !isset($lastSync['retail_food']) || 
                    ($retailFoodLastUpdated && $retailFoodLastUpdated > $lastSync['retail_food']),
                'last_updated' => $retailFoodLastUpdated
            ];

            return response()->json([
                'success' => true,
                'data' => $changes
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Check Changes Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check changes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync users
     */
    public function syncUsers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');

            Log::info('POS Sync: Sync Users', [
                'kode_outlet' => $kodeOutlet
            ]);

            // Get outlet ID
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet not found',
                    'data' => []
                ], 404);
            }

            $idOutlet = $outlet->id_outlet;

            // Get users for outlet + admin
            $usersOutlet = DB::table('users')
                ->where('id_outlet', $idOutlet)
                ->get();

            $usersAdmin = DB::table('users')
                ->where('email', 'admin@gmail.com')
                ->get();

            // Merge and remove duplicates
            $userMap = [];
            foreach ($usersOutlet as $user) {
                $userMap[$user->id] = $user;
            }
            foreach ($usersAdmin as $user) {
                $userMap[$user->id] = $user;
            }

            $users = array_values($userMap);

            // Format users for POS
            $formattedUsers = array_map(function($user) {
                return [
                    'id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap ?? $user->name ?? '',
                    'jabatan' => $user->jabatan ?? $user->role ?? '',
                    'divisi' => $user->divisi ?? $user->division_id ?? '',
                    'email' => $user->email ?? '',
                    'password' => $user->password ?? '',
                    'jenis_kelamin' => $user->jenis_kelamin ?? '',
                    'pin_pos' => $user->pin_pos ?? null,
                    'tanggal_lahir' => $user->tanggal_lahir ?? null,
                    'role' => $user->role ?? $user->jabatan ?? '',
                    'status' => $user->status ?? 'A',
                    'updated_at' => $user->updated_at
                ];
            }, $users);

            return response()->json([
                'success' => true,
                'data' => $formattedUsers,
                'count' => count($formattedUsers)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Users Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync users: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync categories
     */
    public function syncCategories(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');

            // Get outlet ID
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet not found',
                    'data' => []
                ], 404);
            }

            $idOutlet = $outlet->id_outlet;

            // Get categories show_pos = 0
            $categories0 = DB::table('categories')
                ->where('show_pos', '0')
                ->where('status', 'active')
                ->get();

            // Get categories show_pos = 1 for this outlet
            $categories1 = DB::table('categories')
                ->join('category_outlet', 'categories.id', '=', 'category_outlet.category_id')
                ->where('categories.show_pos', '1')
                ->where('categories.status', 'active')
                ->where('category_outlet.outlet_id', $idOutlet)
                ->select('categories.*')
                ->get();

            // Merge and remove duplicates
            $categoryMap = [];
            foreach ($categories0 as $cat) {
                $categoryMap[$cat->id] = $cat;
            }
            foreach ($categories1 as $cat) {
                $categoryMap[$cat->id] = $cat;
            }

            $categories = array_values($categoryMap);

            // Format categories
            $formattedCategories = array_map(function($cat) {
                return [
                    'id' => $cat->id,
                    'code' => $cat->code ?? '',
                    'name' => $cat->name ?? '',
                    'description' => $cat->description ?? null,
                    'status' => $cat->status ?? 'active',
                    'show_pos' => $cat->show_pos ?? '0',
                    'created_at' => $cat->created_at,
                    'updated_at' => $cat->updated_at
                ];
            }, $categories);

            return response()->json([
                'success' => true,
                'data' => $formattedCategories,
                'count' => count($formattedCategories)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Categories Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync categories: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync sub_categories
     */
    public function syncSubCategories(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');

            // Get outlet ID and region ID
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet not found',
                    'data' => []
                ], 404);
            }

            $idOutlet = $outlet->id_outlet;
            $regionId = $outlet->region_id ?? null;

            // Get sub_categories show_pos = 0
            $subs0 = DB::table('sub_categories')
                ->where('show_pos', 0)
                ->where('status', 'active')
                ->get();

            // Get sub_categories show_pos = 1 for this region/outlet
            $subs1 = DB::table('sub_categories')
                ->join('sub_category_availabilities', 'sub_categories.id', '=', 'sub_category_availabilities.sub_category_id')
                ->where('sub_categories.show_pos', 1)
                ->where('sub_categories.status', 'active')
                ->where(function($query) use ($regionId, $idOutlet) {
                    $query->where(function($q) use ($regionId) {
                        $q->where('sub_category_availabilities.availability_type', 'byRegion')
                          ->where('sub_category_availabilities.region_id', $regionId);
                    })->orWhere(function($q) use ($idOutlet) {
                        $q->where('sub_category_availabilities.availability_type', 'byOutlet')
                          ->where('sub_category_availabilities.outlet_id', $idOutlet);
                    });
                })
                ->select('sub_categories.*')
                ->distinct()
                ->get();

            // Merge and remove duplicates
            $subMap = [];
            foreach ($subs0 as $sub) {
                $subMap[$sub->id] = $sub;
            }
            foreach ($subs1 as $sub) {
                $subMap[$sub->id] = $sub;
            }

            $subCategories = array_values($subMap);

            // Format sub_categories
            $formattedSubCategories = array_map(function($sub) {
                return [
                    'id' => $sub->id,
                    'category_id' => $sub->category_id,
                    'name' => $sub->name ?? '',
                    'description' => $sub->description ?? null,
                    'status' => $sub->status ?? 'active',
                    'show_pos' => $sub->show_pos ?? 0,
                    'created_at' => $sub->created_at,
                    'updated_at' => $sub->updated_at
                ];
            }, $subCategories);

            return response()->json([
                'success' => true,
                'data' => $formattedSubCategories,
                'count' => count($formattedSubCategories)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Sub Categories Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync sub_categories: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync units
     */
    public function syncUnits(Request $request)
    {
        try {
            $units = DB::table('units')
                ->where('status', 'active')
                ->get();

            $formattedUnits = array_map(function($unit) {
                return [
                    'id' => $unit->id,
                    'code' => $unit->code ?? '',
                    'name' => $unit->name ?? '',
                    'status' => $unit->status ?? 'active',
                    'type' => $unit->type ?? null,
                    'created_at' => $unit->created_at,
                    'updated_at' => $unit->updated_at
                ];
            }, $units->toArray());

            return response()->json([
                'success' => true,
                'data' => $formattedUnits,
                'count' => count($formattedUnits)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Units Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync units: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync items (with pagination support)
     */
    public function syncItems(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 500);

            // Get outlet ID and region ID
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet not found',
                    'data' => []
                ], 404);
            }

            $idOutlet = $outlet->id_outlet;
            $regionId = $outlet->region_id ?? null;

            // Get items based on availability with price (prioritas outlet > region > all)
            // Sama persis dengan query di syncItems.js yang lama
            $itemsQuery = DB::table('items as i')
                ->leftJoin('item_availabilities as ia_outlet', function($join) use ($idOutlet) {
                    $join->on('ia_outlet.item_id', '=', 'i.id')
                         ->where('ia_outlet.availability_type', 'outlet')
                         ->where('ia_outlet.outlet_id', $idOutlet);
                })
                ->leftJoin('item_availabilities as ia_region', function($join) use ($regionId) {
                    $join->on('ia_region.item_id', '=', 'i.id')
                         ->where('ia_region.availability_type', 'region')
                         ->where('ia_region.region_id', $regionId);
                })
                ->leftJoin('item_availabilities as ia_all', function($join) {
                    $join->on('ia_all.item_id', '=', 'i.id')
                         ->where('ia_all.availability_type', 'all');
                })
                ->leftJoin('item_prices as ip_outlet', function($join) use ($idOutlet) {
                    $join->on('ip_outlet.item_id', '=', 'i.id')
                         ->where('ip_outlet.availability_price_type', 'outlet')
                         ->where('ip_outlet.outlet_id', $idOutlet);
                })
                ->leftJoin('item_prices as ip_region', function($join) use ($regionId) {
                    $join->on('ip_region.item_id', '=', 'i.id')
                         ->where('ip_region.availability_price_type', 'region')
                         ->where('ip_region.region_id', $regionId);
                })
                ->leftJoin('item_prices as ip_all', function($join) {
                    $join->on('ip_all.item_id', '=', 'i.id')
                         ->where('ip_all.availability_price_type', 'all');
                })
                ->where('i.status', 'active')
                ->where(function($query) {
                    $query->whereNotNull('ia_outlet.id')
                          ->orWhereNotNull('ia_region.id')
                          ->orWhereNotNull('ia_all.id');
                })
                ->select(
                    'i.*',
                    DB::raw('COALESCE(ip_outlet.price, ip_region.price, ip_all.price) as price'),
                    DB::raw('COALESCE(ip_outlet.region_id, ip_region.region_id, ip_all.region_id) as region_id')
                )
                ->distinct();

            $total = $itemsQuery->count();
            $items = $itemsQuery->skip(($page - 1) * $perPage)
                               ->take($perPage)
                               ->get();

            // Format items - include semua field seperti di syncItems.js yang lama
            $formattedItems = array_map(function($item) {
                return [
                    'id' => $item->id,
                    'category_id' => $item->category_id ?? null,
                    'sub_category_id' => $item->sub_category_id ?? null,
                    'sku' => $item->sku ?? null,
                    'type' => $item->type ?? null,
                    'name' => $item->name ?? '',
                    'description' => $item->description ?? null,
                    'specification' => $item->specification ?? null,
                    'small_unit_id' => $item->small_unit_id ?? null,
                    'medium_unit_id' => $item->medium_unit_id ?? null,
                    'large_unit_id' => $item->large_unit_id ?? null,
                    'medium_conversion_qty' => $item->medium_conversion_qty ?? null,
                    'small_conversion_qty' => $item->small_conversion_qty ?? null,
                    'status' => $item->status ?? 'active',
                    'price' => $item->price ?? 0,
                    'region_id' => $item->region_id ?? null,
                    'code' => $item->code ?? null,
                    'unit_id' => $item->unit_id ?? null,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at
                ];
            }, $items->toArray());

            return response()->json([
                'success' => true,
                'data' => $formattedItems,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Items Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync items: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync modifiers
     */
    public function syncModifiers(Request $request)
    {
        try {
            $modifiers = DB::table('modifiers')->get();

            $formattedModifiers = array_map(function($modifier) {
                return [
                    'id' => $modifier->id,
                    'name' => $modifier->name ?? '',
                    'created_at' => $modifier->created_at,
                    'updated_at' => $modifier->updated_at
                ];
            }, $modifiers->toArray());

            return response()->json([
                'success' => true,
                'data' => $formattedModifiers,
                'count' => count($formattedModifiers)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Modifiers Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync modifiers: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync modifier_options
     */
    public function syncModifierOptions(Request $request)
    {
        try {
            $modifierOptions = DB::table('modifier_options')->get();

            $formattedModifierOptions = array_map(function($option) {
                return [
                    'id' => $option->id,
                    'modifier_id' => $option->modifier_id,
                    'name' => $option->name ?? '',
                    'created_at' => $option->created_at,
                    'updated_at' => $option->updated_at
                ];
            }, $modifierOptions->toArray());

            return response()->json([
                'success' => true,
                'data' => $formattedModifierOptions,
                'count' => count($formattedModifierOptions)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Modifier Options Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync modifier_options: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync item_modifier_options (item_modifiers in local)
     */
    public function syncItemModifierOptions(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet'); // tetap divalidasi, tapi tidak dipakai sebagai filter lagi

            // Get ALL item_modifier_options for active items (tanpa filter outlet/availability)
            // Permintaan user: "jangan beri filter untuk item modifier, langsung tarik aja semua data nya"
            $itemModifierQuery = DB::table('item_modifier_options')
                ->join('modifier_options', 'item_modifier_options.modifier_option_id', '=', 'modifier_options.id')
                ->join('items', 'item_modifier_options.item_id', '=', 'items.id')
                ->where('items.status', 'active')
                ->select(
                    'item_modifier_options.id',
                    'item_modifier_options.item_id',
                    'modifier_options.modifier_id',
                    'item_modifier_options.modifier_option_id',
                    'item_modifier_options.created_at',
                    'item_modifier_options.updated_at'
                )
                ->distinct();

            $itemModifierOptions = $itemModifierQuery->get();

            \Log::info('POS Sync: Item Modifier Options - Query Result', [
                'kode_outlet' => $kodeOutlet,
                'item_modifier_options_count' => $itemModifierOptions->count()
            ]);

            $formatted = array_map(function($imo) {
                return [
                    'id' => $imo->id,
                    'item_id' => $imo->item_id,
                    'modifier_id' => $imo->modifier_id,
                    'modifier_option_id' => $imo->modifier_option_id,
                    'created_at' => $imo->created_at,
                    'updated_at' => $imo->updated_at
                ];
            }, $itemModifierOptions->toArray());

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'count' => count($formatted)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Item Modifier Options Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync item_modifier_options: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync item_images
     */
    public function syncItemImages(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');

            // Get outlet ID and region ID
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet not found',
                    'data' => []
                ], 404);
            }

            $idOutlet = $outlet->id_outlet;
            $regionId = $outlet->region_id ?? null;

            // Get item images for available items (sama seperti syncItems.js yang lama)
            // Ambil item_images hanya untuk item yang available untuk outlet ini
            $itemImages = DB::table('item_images')
                ->join('items', 'item_images.item_id', '=', 'items.id')
                ->leftJoin('item_availabilities as ia_outlet', function($join) use ($idOutlet) {
                    $join->on('ia_outlet.item_id', '=', 'items.id')
                         ->where('ia_outlet.availability_type', 'outlet')
                         ->where('ia_outlet.outlet_id', $idOutlet);
                })
                ->leftJoin('item_availabilities as ia_region', function($join) use ($regionId) {
                    $join->on('ia_region.item_id', '=', 'items.id')
                         ->where('ia_region.availability_type', 'region')
                         ->where('ia_region.region_id', $regionId);
                })
                ->leftJoin('item_availabilities as ia_all', function($join) {
                    $join->on('ia_all.item_id', '=', 'items.id')
                         ->where('ia_all.availability_type', 'all');
                })
                ->where('items.status', 'active')
                ->where(function($query) {
                    $query->whereNotNull('ia_outlet.id')
                          ->orWhereNotNull('ia_region.id')
                          ->orWhereNotNull('ia_all.id');
                })
                ->select('item_images.*')
                ->distinct()
                ->get();

            $formatted = array_map(function($img) {
                return [
                    'id' => $img->id,
                    'item_id' => $img->item_id,
                    'path' => $img->image_path ?? $img->path ?? '', // Gunakan path seperti di syncItems.js yang lama
                    'created_at' => $img->created_at,
                    'updated_at' => $img->updated_at
                ];
            }, $itemImages->toArray());

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'count' => count($formatted)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Item Images Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync item_images: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync item_prices
     */
    public function syncItemPrices(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');

            // Get outlet ID and region ID
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet not found',
                    'data' => []
                ], 404);
            }

            $idOutlet = $outlet->id_outlet;
            $regionId = $outlet->region_id ?? null;

            // Get item prices with priority: outlet > region > all
            $itemPrices = DB::table('item_prices')
                ->join('items', 'item_prices.item_id', '=', 'items.id')
                ->where('items.status', 'active')
                ->where(function($query) use ($idOutlet, $regionId) {
                    $query->where(function($q) use ($idOutlet) {
                        $q->where('item_prices.availability_price_type', 'outlet')
                          ->where('item_prices.outlet_id', $idOutlet);
                    })->orWhere(function($q) use ($regionId) {
                        $q->where('item_prices.availability_price_type', 'region')
                          ->where('item_prices.region_id', $regionId);
                    })->orWhere(function($q) {
                        $q->where('item_prices.availability_price_type', 'all');
                    });
                })
                ->select('item_prices.*')
                ->get();

            $formatted = array_map(function($price) {
                return [
                    'id' => $price->id,
                    'item_id' => $price->item_id,
                    'price' => $price->price ?? 0,
                    'availability_price_type' => $price->availability_price_type ?? 'all',
                    'region_id' => $price->region_id ?? null,
                    'outlet_id' => $price->outlet_id ?? null,
                    'created_at' => $price->created_at,
                    'updated_at' => $price->updated_at
                ];
            }, $itemPrices->toArray());

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'count' => count($formatted)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Item Prices Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync item_prices: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync promos
     */
    public function syncPromos(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');

            // Get outlet ID and region ID
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet not found',
                    'data' => []
                ], 404);
            }

            $idOutlet = $outlet->id_outlet;
            $regionId = $outlet->region_id ?? null;

            // Get active promos available for this outlet/region
            // Use promo_outlets and promo_regions tables (not promo_availabilities)
            $promos = DB::table('promos')
                ->where('status', 'active')
                ->where(function($query) use ($idOutlet, $regionId) {
                    $query->whereExists(function($q) use ($idOutlet) {
                        $q->select(DB::raw(1))
                          ->from('promo_outlets')
                          ->whereColumn('promo_outlets.promo_id', 'promos.id')
                          ->where('promo_outlets.outlet_id', $idOutlet);
                    })
                    ->orWhereExists(function($q) use ($regionId) {
                        $q->select(DB::raw(1))
                          ->from('promo_regions')
                          ->whereColumn('promo_regions.promo_id', 'promos.id')
                          ->where('promo_regions.region_id', $regionId);
                    });
                })
                ->get();

            $formatted = array_map(function($promo) {
                return [
                    'id' => $promo->id,
                    'name' => $promo->name ?? '',
                    'code' => $promo->code ?? null,
                    'type' => $promo->type ?? null,
                    'value' => $promo->value ?? 0,
                    'max_discount' => $promo->max_discount ?? null,
                    'is_multiple' => $promo->is_multiple ?? 'No',
                    'min_transaction' => $promo->min_transaction ?? null,
                    'max_transaction' => $promo->max_transaction ?? null,
                    'start_date' => $promo->start_date ?? null,
                    'end_date' => $promo->end_date ?? null,
                    'start_time' => $promo->start_time ?? null,
                    'end_time' => $promo->end_time ?? null,
                    'days' => $promo->days ?? null, // JSON field untuk hari
                    'status' => $promo->status ?? 'active',
                    'description' => $promo->description ?? null,
                    'terms' => $promo->terms ?? null,
                    'banner' => $promo->banner ?? null,
                    'need_member' => $promo->need_member ?? 'No',
                    'created_at' => $promo->created_at,
                    'updated_at' => $promo->updated_at
                ];
            }, $promos->toArray());

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'count' => count($formatted)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Promos Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync promos: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync payment_types
     */
    public function syncPaymentTypes(Request $request)
    {
        try {
            $paymentTypes = DB::table('payment_types')
                ->where('status', 'active')
                ->get();

            $formatted = array_map(function($pt) {
                return [
                    'id' => $pt->id,
                    'name' => $pt->name ?? '',
                    'code' => $pt->code ?? '',
                    'status' => $pt->status ?? 'active',
                    'created_at' => $pt->created_at,
                    'updated_at' => $pt->updated_at
                ];
            }, $paymentTypes->toArray());

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'count' => count($formatted)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Payment Types Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync payment_types: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync reservations
     */
    public function syncReservations(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');

            // Get outlet ID
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet not found',
                    'data' => []
                ], 404);
            }

            $idOutlet = $outlet->id_outlet;
            $today = Carbon::today()->toDateString();

            // Get reservations from today onwards
            $reservations = DB::table('reservations')
                ->where('outlet_id', $idOutlet)
                ->where('reservation_date', '>=', $today)
                ->get();

            $formatted = array_map(function($res) {
                return [
                    'id' => $res->id,
                    'name' => $res->name ?? '',
                    'phone' => $res->phone ?? '',
                    'email' => $res->email ?? null,
                    'outlet_id' => $res->outlet_id,
                    'reservation_date' => $res->reservation_date,
                    'reservation_time' => $res->reservation_time,
                    'number_of_guests' => $res->number_of_guests ?? 0,
                    'special_requests' => $res->special_requests ?? null,
                    'status' => $res->status ?? 'pending',
                    'smoking_preference' => $res->smoking_preference ?? null,
                    'created_at' => $res->created_at,
                    'updated_at' => $res->updated_at
                ];
            }, $reservations->toArray());

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'count' => count($formatted)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Reservations Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync reservations: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync investors
     */
    public function syncInvestors(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');

            // Get outlet ID
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet not found',
                    'data' => []
                ], 404);
            }

            $idOutlet = $outlet->id_outlet;

            // Get investors for this outlet
            $investors = DB::table('investors')
                ->join('investor_outlet', 'investors.id', '=', 'investor_outlet.investor_id')
                ->where('investor_outlet.outlet_id', $idOutlet)
                ->select('investors.*')
                ->distinct()
                ->get();

            $formatted = array_map(function($inv) {
                return [
                    'id' => $inv->id,
                    'name' => $inv->name ?? '',
                    'email' => $inv->email ?? null,
                    'phone' => $inv->phone ?? null,
                    'created_at' => $inv->created_at,
                    'updated_at' => $inv->updated_at
                ];
            }, $investors->toArray());

            // Get investor_outlet relationships
            $investorOutlets = DB::table('investor_outlet')
                ->where('outlet_id', $idOutlet)
                ->get();

            $formattedInvestorOutlets = array_map(function($io) {
                return [
                    'id' => $io->id,
                    'investor_id' => $io->investor_id,
                    'outlet_id' => $io->outlet_id,
                    'created_at' => $io->created_at,
                    'updated_at' => $io->updated_at
                ];
            }, $investorOutlets->toArray());

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'investor_outlets' => $formattedInvestorOutlets,
                'count' => count($formatted)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Investors Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync investors: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync officer_checks
     */
    public function syncOfficerChecks(Request $request)
    {
        try {
            $officerChecks = DB::table('officer_checks')
                ->get();

            $formatted = array_map(function($oc) {
                return [
                    'id' => $oc->id,
                    'user_id' => $oc->user_id ?? null,
                    'user_name' => $oc->user_name ?? '',
                    'nilai' => $oc->nilai ?? 0,
                    'created_at' => $oc->created_at,
                    'updated_at' => $oc->updated_at
                ];
            }, $officerChecks->toArray());

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'count' => count($formatted)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Officer Checks Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync officer_checks: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Sync retail_food
     */
    public function syncRetailFood(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');

            // Get outlet ID
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet not found',
                    'data' => []
                ], 404);
            }

            $idOutlet = $outlet->id_outlet;

            // Get retail_food for this outlet (approved and not deleted)
            $retailFoods = DB::table('retail_food')
                ->where('outlet_id', $idOutlet)
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            $formatted = array_map(function($rf) {
                return [
                    'id' => $rf->id,
                    'retail_number' => $rf->retail_number ?? '',
                    'outlet_id' => $rf->outlet_id,
                    'warehouse_outlet_id' => $rf->warehouse_outlet_id ?? null,
                    'created_by' => $rf->created_by ?? null,
                    'transaction_date' => $rf->transaction_date,
                    'total_amount' => $rf->total_amount ?? 0,
                    'notes' => $rf->notes ?? null,
                    'status' => $rf->status ?? 'approved',
                    'deleted_at' => $rf->deleted_at ?? null,
                    'created_at' => $rf->created_at,
                    'updated_at' => $rf->updated_at
                ];
            }, $retailFoods->toArray());

            // Get retail_food_items
            $retailFoodIds = $retailFoods->pluck('id')->toArray();
            $retailFoodItems = [];
            
            if (count($retailFoodIds) > 0) {
                $items = DB::table('retail_food_items')
                    ->whereIn('retail_food_id', $retailFoodIds)
                    ->get();
                
                $retailFoodItems = array_map(function($item) {
                    return [
                        'id' => $item->id,
                        'retail_food_id' => $item->retail_food_id,
                        'item_id' => $item->item_id ?? null,
                        'item_name' => $item->item_name ?? '',
                        'qty' => $item->qty ?? 0,
                        'unit' => $item->unit ?? '',
                        'price' => $item->price ?? 0,
                        'subtotal' => $item->subtotal ?? 0,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at
                    ];
                }, $items->toArray());
            }

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'items' => $retailFoodItems,
                'count' => count($formatted)
            ]);

        } catch (\Exception $e) {
            Log::error('POS Sync: Sync Retail Food Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync retail_food: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
}
