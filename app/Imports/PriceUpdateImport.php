<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\ActivityLog;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PriceUpdateImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected $errors = [];
    protected $successCount = 0;
    protected $errorCount = 0;
    protected $updatedItems = [];
    
    private function getRowValue($row, $key, $default = null)
    {
        // Handle null or invalid row
        if (empty($row) || (!is_object($row) && !is_array($row))) {
            return $default;
        }
        
        if (is_object($row)) {
            // Handle different object types
            if (method_exists($row, 'get')) {
                return $row->get($key, $default);
            } elseif (property_exists($row, $key)) {
                return $row->$key ?? $default;
            } else {
                return $default;
            }
        }
        
        if (is_array($row)) {
            // Check if data is nested in 'items' property (from Excel import)
            // Handle both normal 'items' and unicode prefixed 'items'
            $itemsKey = 'items';
            $unicodeItemsKey = "\x00*\x00items";
            
            // Handle normal items (object)
            if (isset($row[$itemsKey])) {
                $items = $row[$itemsKey];
                if (is_object($items) && property_exists($items, $key)) {
                    return $items->$key ?? $default;
                } elseif (is_array($items) && isset($items[$key])) {
                    return $items[$key] ?? $default;
                }
            }
            
            // Handle unicode items (array or object)
            if (isset($row[$unicodeItemsKey])) {
                $items = $row[$unicodeItemsKey];
                if (is_object($items) && property_exists($items, $key)) {
                    return $items->$key ?? $default;
                } elseif (is_array($items) && isset($items[$key])) {
                    return $items[$key] ?? $default;
                }
            }
            
            return $row[$key] ?? $default;
        }
        
        return $default;
    }

    public function collection(Collection $rows)
    {
        \Log::info('PriceUpdateImport@collection - Starting import', [
            'total_rows' => $rows->count(),
            'first_row_type' => $rows->first() ? gettype($rows->first()) : 'no_rows',
            'first_row_class' => $rows->first() && is_object($rows->first()) ? get_class($rows->first()) : 'not_object',
            'first_row_keys' => $rows->first() ? (is_object($rows->first()) ? array_keys((array) $rows->first()) : array_keys($rows->first())) : []
        ]);

        // Normalize rows to ensure consistent structure
        $normalizedRows = $rows->map(function ($row) {
            if (is_object($row)) {
                return (array) $row;
            }
            return $row;
        });

        \Log::info('PriceUpdateImport@collection - After normalization', [
            'normalized_count' => $normalizedRows->count(),
            'first_normalized_type' => $normalizedRows->first() ? gettype($normalizedRows->first()) : 'no_rows',
            'first_normalized_keys' => $normalizedRows->first() ? array_keys($normalizedRows->first()) : []
        ]);

        DB::beginTransaction();
        try {
                    foreach ($normalizedRows as $index => $row) {
            // Skip baris yang semua kolomnya kosong
            if (collect($row)->filter()->isEmpty()) {
                \Log::info('PriceUpdateImport@collection - Skipping empty row', ['row_index' => $index + 2]);
                continue;
            }

            // Validasi struktur row data
            if (!is_object($row) && !is_array($row)) {
                \Log::warning('PriceUpdateImport@collection - Invalid row structure', [
                    'row_index' => $index + 2,
                    'row_type' => gettype($row),
                    'row_data' => $row
                ]);
                $this->errorCount++;
                $this->errors[] = [
                    'row' => $index + 2,
                    'item' => 'Unknown',
                    'error' => 'Invalid row structure'
                ];
                continue;
            }

                \Log::info('PriceUpdateImport@collection - Processing row', [
                    'row_index' => $index + 2,
                    'row_type' => gettype($row),
                    'row_class' => is_object($row) ? get_class($row) : 'not_object',
                    'row_data' => is_object($row) ? (array) $row : $row,
                    'available_keys' => is_object($row) ? array_keys((array) $row) : array_keys($row),
                    'has_items_property' => isset($row['items']),
                    'has_unicode_items_property' => isset($row["\x00*\x00items"]),
                    'items_type' => isset($row['items']) ? gettype($row['items']) : 'none',
                    'unicode_items_type' => isset($row["\x00*\x00items"]) ? gettype($row["\x00*\x00items"]) : 'none',
                    'items_keys' => isset($row['items']) ? (is_object($row['items']) ? array_keys((array) $row['items']) : (is_array($row['items']) ? array_keys($row['items']) : [])) : [],
                    'unicode_items_keys' => isset($row["\x00*\x00items"]) ? (is_object($row["\x00*\x00items"]) ? array_keys((array) $row["\x00*\x00items"]) : (is_array($row["\x00*\x00items"]) ? array_keys($row["\x00*\x00items"]) : [])) : [],
                    'unicode_items_sample' => isset($row["\x00*\x00items"]) ? (is_array($row["\x00*\x00items"]) ? array_slice($row["\x00*\x00items"], 0, 3) : 'not_array') : 'none'
                ]);

                try {
                    // Debug: cek apakah data bisa diakses
                    $itemId = $this->getRowValue($row, 'item_id');
                    $sku = $this->getRowValue($row, 'sku');
                    $itemName = $this->getRowValue($row, 'item_name');
                    
                    \Log::info('PriceUpdateImport@collection - Data access test', [
                        'row_index' => $index + 2,
                        'item_id' => $itemId,
                        'sku' => $sku,
                        'item_name' => $itemName,
                        'item_id_type' => gettype($itemId),
                        'sku_type' => gettype($sku),
                        'item_name_type' => gettype($itemName)
                    ]);
                    
                    // Validasi struktur data yang diperlukan
                    $requiredFields = ['item_id', 'sku', 'item_name'];
                    $missingFields = [];
                    
                    foreach ($requiredFields as $field) {
                        if (empty($this->getRowValue($row, $field))) {
                            $missingFields[] = $field;
                        }
                    }
                    
                    if (!empty($missingFields)) {
                        throw new \Exception('Kolom wajib tidak diisi: ' . implode(', ', $missingFields));
                    }

                    // Validasi hash untuk memastikan data tidak berubah
                    $expectedHash = md5($this->getRowValue($row, 'item_id') . $this->getRowValue($row, 'sku') . $this->getRowValue($row, 'item_name'));
                    if ($this->getRowValue($row, 'validation_jangan_diubah') !== $expectedHash) {
                        \Log::warning('PriceUpdateImport@collection - Hash mismatch, trying fallback lookup', [
                            'item_id' => $this->getRowValue($row, 'item_id'),
                            'template_sku' => $this->getRowValue($row, 'sku'),
                            'item_name' => $this->getRowValue($row, 'item_name'),
                            'expected_hash' => $expectedHash,
                            'actual_hash' => $this->getRowValue($row, 'validation_jangan_diubah')
                        ]);
                        
                        // Fallback: cari item berdasarkan ID dan nama saja (ignore SKU mismatch)
                        $item = Item::where('id', $this->getRowValue($row, 'item_id'))
                            ->where('name', $this->getRowValue($row, 'item_name'))
                            ->where('status', 'active')
                            ->first();
                        
                        if (!$item) {
                            throw new \Exception('Item tidak ditemukan atau tidak aktif (hash mismatch)');
                        }
                        
                        \Log::info('PriceUpdateImport@collection - Fallback lookup successful', [
                            'item_id' => $item->id,
                            'item_name' => $item->name,
                            'db_sku' => $item->sku,
                            'template_sku' => $this->getRowValue($row, 'sku')
                        ]);
                    } else {
                        // Cek item dengan hash yang match
                        $item = Item::where('id', $this->getRowValue($row, 'item_id'))
                            ->where('sku', $this->getRowValue($row, 'sku'))
                            ->where('name', $this->getRowValue($row, 'item_name'))
                            ->where('status', 'active')
                            ->first();
                        
                        if (!$item) {
                            throw new \Exception('Item tidak ditemukan atau tidak aktif');
                        }
                    }

                    \Log::info('PriceUpdateImport@collection - Item found', [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'item_sku' => $item->sku
                    ]);

                    // Validasi new price - jika kosong, skip item ini
                    $newPrice = null;
                    $priceType = 'all';
                    $regionOutlet = 'All';
                    
                    // Cek berbagai kemungkinan nama kolom untuk new price
                    $newPriceRaw = null;
                    if (!empty($this->getRowValue($row, 'new_price'))) {
                        $newPriceRaw = $this->getRowValue($row, 'new_price');
                    } elseif (!empty($this->getRowValue($row, 'new_price_kosongkan_jika_tidak_diupdate'))) {
                        $newPriceRaw = $this->getRowValue($row, 'new_price_kosongkan_jika_tidak_diupdate');
                    }
                    
                    // Parse new price dan price type dari format "14152.94 region" atau "14152.94 all"
                    if ($newPriceRaw) {
                        // Cek apakah new_price_raw sudah dalam format "price type" (e.g., "39200.00 all")
                        if (is_string($newPriceRaw) && strpos($newPriceRaw, ' ') !== false) {
                            $parts = explode(' ', trim($newPriceRaw));
                            if (count($parts) >= 2) {
                                $newPrice = $parts[0];
                                $priceType = $parts[1];
                                
                                \Log::info('PriceUpdateImport@collection - Parsed from combined field', [
                                    'item_name' => $this->getRowValue($row, 'item_name'),
                                    'new_price_raw' => $newPriceRaw,
                                    'parsed_price' => $newPrice,
                                    'parsed_type' => $priceType
                                ]);
                            } else {
                                $newPrice = $newPriceRaw;
                            }
                        } else {
                            // Jika bukan format combined, gunakan kolom terpisah
                            $newPrice = $newPriceRaw;
                            $priceType = $this->getRowValue($row, 'price_type', 'all');
                        }
                        
                        // Ambil region/outlet dari kolom terpisah
                        if (!empty($this->getRowValue($row, 'region_outlet'))) {
                            $regionOutlet = $this->getRowValue($row, 'region_outlet');
                        } elseif (!empty($this->getRowValue($row, 'regionoutlet'))) {
                            $regionOutlet = $this->getRowValue($row, 'regionoutlet');
                        }
                    }
                    
                    \Log::info('PriceUpdateImport@collection - New price extraction', [
                        'item_name' => $this->getRowValue($row, 'item_name'),
                        'new_price_raw' => $newPriceRaw,
                        'parsed_new_price' => $newPrice,
                        'parsed_price_type' => $priceType,
                        'parsed_region_outlet' => $regionOutlet,
                        'new_price_direct' => $this->getRowValue($row, 'new_price', 'NOT_FOUND'),
                        'new_price_long' => $this->getRowValue($row, 'new_price_kosongkan_jika_tidak_diupdate', 'NOT_FOUND'),
                        'price_type_column' => $this->getRowValue($row, 'price_type', 'NOT_FOUND'),
                        'region_outlet_column' => $this->getRowValue($row, 'region_outlet', 'NOT_FOUND'),
                        'regionoutlet_column' => $this->getRowValue($row, 'regionoutlet', 'NOT_FOUND')
                    ]);
                    
                    if (empty($newPrice) || $newPrice === '' || $newPrice === null) {
                        \Log::info('PriceUpdateImport@collection - Skipping empty new_price', [
                            'item_name' => $this->getRowValue($row, 'item_name')
                        ]);
                        $this->successCount++;
                        $this->updatedItems[] = [
                            'row' => $index + 2,
                            'name' => $this->getRowValue($row, 'item_name'),
                            'status' => 'success',
                            'message' => 'Skipped (no price change)',
                        ];
                        continue; // Skip item yang tidak diupdate
                    }

                    // Validasi format new price
                    if (!is_numeric($newPrice) || $newPrice < 0) {
                        throw new \Exception('New Price harus berupa angka positif');
                    }

                    $newPrice = (float) $newPrice;
                    $currentPrice = (float) ($this->getRowValue($row, 'current_price', 0));
                    
                    // Gunakan priceType dan regionOutlet yang sudah di-parse dari new_price
                    // Jika tidak ada di new_price, gunakan dari kolom terpisah
                    if ($priceType === 'all' && !empty($this->getRowValue($row, 'price_type'))) {
                        $priceType = $this->getRowValue($row, 'price_type');
                    }
                    if ($regionOutlet === 'All' && !empty($this->getRowValue($row, 'region_outlet'))) {
                        $regionOutlet = $this->getRowValue($row, 'region_outlet');
                    }

                    \Log::info('PriceUpdateImport@collection - Price validation', [
                        'item_name' => $this->getRowValue($row, 'item_name'),
                        'new_price' => $newPrice,
                        'template_current_price' => $currentPrice,
                        'price_type' => $priceType,
                        'region_outlet' => $regionOutlet
                    ]);

                    // Validasi harga tidak boleh 0 (kecuali memang gratis)
                    if ($newPrice == 0) {
                        \Log::warning('PriceUpdateImport@collection - Zero price detected', [
                            'item_name' => $this->getRowValue($row, 'item_name'),
                            'new_price' => $newPrice
                        ]);
                        // Bisa di-comment jika memang ada item gratis
                        // throw new \Exception('New Price tidak boleh 0 (kecuali item gratis)');
                    }

                    // Ambil harga aktual dari database untuk perbandingan yang akurat
                    $actualCurrentPrice = 0;
                    if ($item) {
                        $dbPrice = ItemPrice::where('item_id', $item->id)
                            ->where('availability_price_type', $priceType)
                            ->where(function($q) use ($priceType, $regionOutlet) {
                                if ($priceType === 'region' && $regionOutlet !== 'All') {
                                    $region = \DB::table('regions')->where('name', $regionOutlet)->first();
                                    if ($region) {
                                        $q->where('region_id', $region->id);
                                    }
                                } elseif ($priceType === 'outlet' && $regionOutlet !== 'All') {
                                    $outlet = \DB::table('tbl_data_outlet')->where('nama_outlet', $regionOutlet)->first();
                                    if ($outlet) {
                                        $q->where('outlet_id', $outlet->id_outlet);
                                    }
                                } else {
                                    $q->whereNull('region_id')->whereNull('outlet_id');
                                }
                            })
                            ->first();
                        
                        $actualCurrentPrice = $dbPrice ? (float)$dbPrice->price : 0;
                    }

                    \Log::info('PriceUpdateImport@collection - Database price check', [
                        'item_name' => $this->getRowValue($row, 'item_name'),
                        'template_current_price' => $currentPrice,
                        'actual_current_price' => $actualCurrentPrice,
                        'new_price' => $newPrice
                    ]);

                    // Jika harga tidak berubah (bandingkan dengan database, bukan template)
                    if ($newPrice == $actualCurrentPrice) {
                        \Log::info('PriceUpdateImport@collection - Skipping same price (vs database)', [
                            'item_name' => $this->getRowValue($row, 'item_name'),
                            'new_price' => $newPrice,
                            'actual_current_price' => $actualCurrentPrice
                        ]);
                        $this->successCount++;
                        $this->updatedItems[] = [
                            'row' => $index + 2,
                            'name' => $this->getRowValue($row, 'item_name'),
                            'status' => 'success',
                            'message' => 'Skipped (same price)',
                        ];
                        continue;
                    }

                    // Update atau create price
                    $updateResult = $this->updateItemPrice($item, $newPrice, $priceType, $regionOutlet);

                    // Log perubahan
                    $this->logPriceChange($item, $currentPrice, $newPrice, $priceType, $regionOutlet);

                    \Log::info('PriceUpdateImport@collection - Price updated successfully', [
                        'row_index' => $index + 2,
                        'item_name' => $this->getRowValue($row, 'item_name'),
                        'price_type' => $priceType,
                        'region_outlet' => $regionOutlet,
                        'old_price' => $currentPrice,
                        'new_price' => $newPrice,
                        'update_result_id' => $updateResult->id,
                        'was_created' => $updateResult->wasRecentlyCreated
                    ]);

                    $this->successCount++;
                    $this->updatedItems[] = [
                        'row' => $index + 2,
                        'name' => $this->getRowValue($row, 'item_name'),
                        'status' => 'success',
                        'message' => 'Successfully updated',
                    ];

                } catch (\Exception $e) {
                    \Log::error('PriceUpdateImport@collection - Error processing row', [
                        'row_index' => $index + 2,
                        'row_type' => gettype($row),
                        'row_class' => is_object($row) ? get_class($row) : 'not_object',
                        'row_data' => is_object($row) ? (array) $row : $row,
                        'item_name' => $this->getRowValue($row, 'item_name', 'Unknown'),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->errorCount++;
                    $this->errors[] = [
                        'row' => $index + 2,
                        'item' => $this->getRowValue($row, 'item_name', 'Unknown'),
                        'error' => $e->getMessage()
                    ];
                }
            }

            \Log::info('PriceUpdateImport@collection - Import completed', [
                'success_count' => $this->successCount,
                'error_count' => $this->errorCount,
                'total_rows_processed' => $normalizedRows->count(),
                'summary' => [
                    'total_price_updates' => $this->successCount,
                    'total_errors' => $this->errorCount,
                    'multiple_price_types_supported' => true
                ]
            ]);

            DB::commit();
        } catch (\Exception $e) {
            \Log::error('PriceUpdateImport@collection - Transaction error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            DB::rollBack();
            throw $e;
        }
    }

    private function updateItemPrice($item, $newPrice, $priceType, $regionOutlet)
    {
        \Log::info('PriceUpdateImport@updateItemPrice - Starting update', [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'new_price' => $newPrice,
            'price_type' => $priceType,
            'region_outlet' => $regionOutlet
        ]);

        // Tentukan region_id dan outlet_id berdasarkan price type dan region/outlet
        $regionId = null;
        $outletId = null;
        $availabilityPriceType = 'all';

        if ($priceType === 'region' && $regionOutlet !== 'All') {
            $region = \DB::table('regions')->where('name', $regionOutlet)->first();
            \Log::info('PriceUpdateImport@updateItemPrice - Region lookup', [
                'region_outlet' => $regionOutlet,
                'region_found' => $region ? true : false,
                'region_id' => $region ? $region->id : null
            ]);
            if ($region) {
                $regionId = $region->id;
                $availabilityPriceType = 'region';
            }
        } elseif ($priceType === 'outlet' && $regionOutlet !== 'All') {
            $outlet = \DB::table('tbl_data_outlet')->where('nama_outlet', $regionOutlet)->first();
            \Log::info('PriceUpdateImport@updateItemPrice - Outlet lookup', [
                'region_outlet' => $regionOutlet,
                'outlet_found' => $outlet ? true : false,
                'outlet_id' => $outlet ? $outlet->id_outlet : null
            ]);
            if ($outlet) {
                $outletId = $outlet->id_outlet;
                $availabilityPriceType = 'outlet';
            }
        }

        \Log::info('PriceUpdateImport@updateItemPrice - Before updateOrCreate', [
            'item_id' => $item->id,
            'region_id' => $regionId,
            'outlet_id' => $outletId,
            'availability_price_type' => $availabilityPriceType,
            'new_price' => $newPrice
        ]);

        // Cek existing price sebelum update
        $existingPrice = ItemPrice::where('item_id', $item->id)
            ->where('region_id', $regionId)
            ->where('outlet_id', $outletId)
            ->first();
        
        \Log::info('PriceUpdateImport@updateItemPrice - Existing price check', [
            'existing_price_found' => $existingPrice ? true : false,
            'existing_price' => $existingPrice ? $existingPrice->price : null
        ]);

        // Update atau create price
        $result = ItemPrice::updateOrCreate(
            [
                'item_id' => $item->id,
                'region_id' => $regionId,
                'outlet_id' => $outletId,
            ],
            [
                'price' => $newPrice,
                'availability_price_type' => $availabilityPriceType,
            ]
        );

        \Log::info('PriceUpdateImport@updateItemPrice - After updateOrCreate', [
            'result_id' => $result->id,
            'result_price' => $result->price,
            'was_recently_created' => $result->wasRecentlyCreated
        ]);

        return $result;
    }

    private function logPriceChange($item, $oldPrice, $newPrice, $priceType, $regionOutlet)
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'item_prices',
            'description' => "Update harga item: {$item->name} dari Rp " . number_format($oldPrice) . " ke Rp " . number_format($newPrice) . " ({$priceType}: {$regionOutlet})",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => ['price' => $oldPrice],
            'new_data' => ['price' => $newPrice, 'price_type' => $priceType, 'region_outlet' => $regionOutlet]
        ]);
    }

    public function rules(): array
    {
        return [
            'item_id' => 'required|integer',
            'sku' => 'required|string',
            'item_name' => 'required|string',
            'current_price' => 'nullable|numeric|min:0',
            'new_price' => 'nullable|numeric|min:0',
            'price_type' => 'required|in:all,region,outlet',
            'region_outlet' => 'nullable|string',
            'validation_jangan_diubah' => 'required|string',
        ];
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function getErrorCount()
    {
        return $this->errorCount;
    }

    public function getResults()
    {
        return $this->updatedItems;
    }
} 