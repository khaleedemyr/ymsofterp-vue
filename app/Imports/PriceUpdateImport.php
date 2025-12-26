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
                    $validationHash = $this->getRowValue($row, 'validation_hash_jangan_diubah') ?: $this->getRowValue($row, 'validation_jangan_diubah');
                    if ($validationHash && $validationHash !== $expectedHash) {
                        \Log::warning('PriceUpdateImport@collection - Hash mismatch, trying fallback lookup', [
                            'item_id' => $this->getRowValue($row, 'item_id'),
                            'template_sku' => $this->getRowValue($row, 'sku'),
                            'item_name' => $this->getRowValue($row, 'item_name'),
                            'expected_hash' => $expectedHash,
                            'actual_hash' => $validationHash
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
                        $itemId = $this->getRowValue($row, 'item_id');
                        $sku = $this->getRowValue($row, 'sku');
                        $itemName = $this->getRowValue($row, 'item_name');
                        
                        // Cari item - coba dengan ID, SKU, dan name dulu
                        $item = Item::where('id', $itemId)
                            ->where('sku', $sku)
                            ->where('name', $itemName)
                            ->where('status', 'active')
                            ->first();
                        
                        // Jika tidak ditemukan, coba dengan ID dan name saja (SKU mungkin berubah)
                        if (!$item) {
                            \Log::warning('PriceUpdateImport@collection - Item not found with exact match, trying fallback', [
                                'item_id' => $itemId,
                                'sku' => $sku,
                                'item_name' => $itemName
                            ]);
                            
                            $item = Item::where('id', $itemId)
                                ->where('name', $itemName)
                                ->where('status', 'active')
                                ->first();
                        }
                        
                        // Jika masih tidak ditemukan, coba dengan ID saja
                        if (!$item) {
                            \Log::warning('PriceUpdateImport@collection - Item not found with name match, trying ID only', [
                                'item_id' => $itemId,
                                'sku' => $sku,
                                'item_name' => $itemName
                            ]);
                            
                            $item = Item::where('id', $itemId)
                                ->where('status', 'active')
                                ->first();
                        }
                        
                        if (!$item) {
                            throw new \Exception("Item tidak ditemukan atau tidak aktif. ID: {$itemId}, SKU: {$sku}, Name: {$itemName}");
                        }
                        
                        \Log::info('PriceUpdateImport@collection - Item found', [
                            'item_id' => $item->id,
                            'item_name' => $item->name,
                            'db_sku' => $item->sku,
                            'template_sku' => $sku
                        ]);
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
                    } elseif (!empty($this->getRowValue($row, 'new_price_isi_untuk_update_kosongkan_jika_tidak_diubah'))) {
                        $newPriceRaw = $this->getRowValue($row, 'new_price_isi_untuk_update_kosongkan_jika_tidak_diubah');
                    } elseif (!empty($this->getRowValue($row, 'new_price_kosongkan_jika_tidak_diupdate'))) {
                        $newPriceRaw = $this->getRowValue($row, 'new_price_kosongkan_jika_tidak_diupdate');
                    }
                    
                    // Ambil region/outlet dari kolom - cek berbagai kemungkinan nama kolom
                    // Excel dengan WithHeadingRow biasanya mengubah header menjadi lowercase dengan spasi atau underscore
                    // "Region/Outlet Name" bisa menjadi: "region/outlet name", "region_outlet_name", dll
                    $possibleKeys = [
                        'regionoutlet_name',   // Format yang muncul di log (tanpa underscore di awal)
                        'region/outlet name',   // Lowercase dengan spasi (paling umum)
                        'Region/Outlet Name',   // Original dengan kapital
                        'region_outlet_name',  // Underscore untuk slash dan spasi
                        'region_outlet',     // Tanpa "name"
                        'regionoutlet',        // Tanpa spasi dan slash
                        'Region/Outlet',       // Tanpa "Name"
                    ];
                    
                    $regionOutlet = null;
                    foreach ($possibleKeys as $key) {
                        $value = $this->getRowValue($row, $key);
                        if (!empty($value) && $value !== 'All') {
                            $regionOutlet = $value;
                            break;
                        }
                    }
                    
                    // Jika masih kosong, coba akses langsung dari array dengan key yang mungkin
                    if (empty($regionOutlet)) {
                        // Coba akses langsung dari array jika row adalah array
                        if (is_array($row)) {
                            // Cek berbagai variasi key
                            $directKeys = ['regionoutlet_name', 'region_outlet_name', 'region/outlet name', 'Region/Outlet Name'];
                            foreach ($directKeys as $key) {
                                if (isset($row[$key]) && !empty($row[$key]) && $row[$key] !== 'All') {
                                    $regionOutlet = $row[$key];
                                    break;
                                }
                            }
                            
                            // Jika masih kosong, coba ambil dari array index (kolom ke-5, index 4)
                            if (empty($regionOutlet)) {
                                $rowArray = array_values($row);
                                // Kolom: Item ID(0), SKU(1), Item Name(2), Category(3), Region/Outlet Name(4), Current Price(5), New Price(6), Validation(7)
                                if (isset($rowArray[4]) && !empty($rowArray[4]) && $rowArray[4] !== 'All') {
                                    $regionOutlet = $rowArray[4];
                                }
                            }
                        }
                    }
                    
                    // Jika masih kosong, set ke 'All'
                    if (empty($regionOutlet)) {
                        $regionOutlet = 'All';
                    }
                    
                    \Log::info('PriceUpdateImport@collection - Region/Outlet parsing', [
                        'item_name' => $this->getRowValue($row, 'item_name'),
                        'parsed_region_outlet' => $regionOutlet,
                        'row_keys' => is_array($row) ? array_keys($row) : (is_object($row) ? array_keys((array)$row) : []),
                        'regionoutlet_name_direct' => is_array($row) && isset($row['regionoutlet_name']) ? $row['regionoutlet_name'] : 'NOT_FOUND'
                    ]);
                    
                    // Auto-detect price type berdasarkan region/outlet name
                    // Jika regionOutlet = 'All', maka priceType = 'all'
                    // Jika regionOutlet adalah nama region, maka priceType = 'region'
                    // Jika regionOutlet adalah nama outlet, maka priceType = 'outlet'
                    if ($regionOutlet && $regionOutlet !== 'All' && $regionOutlet !== '') {
                        // Cek apakah ini nama region
                        $region = \DB::table('regions')->where('name', $regionOutlet)->first();
                        if ($region) {
                            $priceType = 'region';
                            \Log::info('PriceUpdateImport@collection - Detected as region', [
                                'region_outlet' => $regionOutlet,
                                'region_id' => $region->id,
                                'item_name' => $this->getRowValue($row, 'item_name')
                            ]);
                        } else {
                            // Cek apakah ini nama outlet
                            $outlet = \DB::table('tbl_data_outlet')->where('nama_outlet', $regionOutlet)->first();
                            if ($outlet) {
                                $priceType = 'outlet';
                                \Log::info('PriceUpdateImport@collection - Detected as outlet', [
                                    'region_outlet' => $regionOutlet,
                                    'outlet_id' => $outlet->id_outlet,
                                    'item_name' => $this->getRowValue($row, 'item_name')
                                ]);
                            } else {
                                // Jika tidak ditemukan, default ke 'all'
                                $priceType = 'all';
                                \Log::warning('PriceUpdateImport@collection - Region/Outlet not found, defaulting to all', [
                                    'region_outlet' => $regionOutlet,
                                    'item_name' => $this->getRowValue($row, 'item_name')
                                ]);
                            }
                        }
                    } else {
                        // Jika regionOutlet kosong atau 'All', set ke 'all'
                        $priceType = 'all';
                        \Log::info('PriceUpdateImport@collection - Region/Outlet is All or empty, using all', [
                            'region_outlet' => $regionOutlet,
                            'item_name' => $this->getRowValue($row, 'item_name')
                        ]);
                    }
                    
                    // Parse new price
                    if ($newPriceRaw) {
                        // Cek apakah new_price_raw masih dalam format lama "price type" (e.g., "39200.00 all")
                        if (is_string($newPriceRaw) && strpos($newPriceRaw, ' ') !== false) {
                            $parts = explode(' ', trim($newPriceRaw));
                            if (count($parts) >= 2) {
                                // Format lama: "14152.94 region" - ambil harga saja, ignore type karena sudah di-detect dari region/outlet
                                $newPrice = $parts[0];
                            } else {
                                $newPrice = $newPriceRaw;
                            }
                        } else {
                            // Format baru: kolom terpisah
                            $newPrice = $newPriceRaw;
                        }
                    }
                    
                    \Log::info('PriceUpdateImport@collection - New price extraction', [
                        'item_name' => $this->getRowValue($row, 'item_name'),
                        'new_price_raw' => $newPriceRaw,
                        'parsed_new_price' => $newPrice,
                        'detected_price_type' => $priceType,
                        'parsed_region_outlet' => $regionOutlet,
                        'new_price_direct' => $this->getRowValue($row, 'new_price', 'NOT_FOUND'),
                        'new_price_long' => $this->getRowValue($row, 'new_price_isi_untuk_update_kosongkan_jika_tidak_diubah', 'NOT_FOUND'),
                        'region_outlet_name_column' => $this->getRowValue($row, 'region_outlet_name', 'NOT_FOUND'),
                        'Region/Outlet Name_column' => $this->getRowValue($row, 'Region/Outlet Name', 'NOT_FOUND'),
                        'region_outlet_column' => $this->getRowValue($row, 'region_outlet', 'NOT_FOUND'),
                        'regionoutlet_column' => $this->getRowValue($row, 'regionoutlet', 'NOT_FOUND'),
                        'all_row_keys' => is_array($row) ? array_keys($row) : (is_object($row) ? array_keys((array)$row) : [])
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
                    
                    // PriceType sudah di-detect otomatis dari region/outlet name

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

        // Normalize priceType
        $priceType = strtolower(trim($priceType));

        // Pastikan regionOutlet tidak kosong dan bukan 'All'
        // SELALU cari region/outlet berdasarkan nama, tidak peduli priceType yang dikirim
        if ($regionOutlet && $regionOutlet !== 'All' && $regionOutlet !== '' && trim($regionOutlet) !== '') {
            // SELALU coba cari region dulu
            $region = \DB::table('regions')->where('name', $regionOutlet)->first();
            if ($region) {
                $regionId = $region->id;
                $availabilityPriceType = 'region';
                \Log::info('PriceUpdateImport@updateItemPrice - Found as region', [
                    'region_outlet' => $regionOutlet,
                    'region_id' => $region->id,
                    'original_price_type' => $priceType
                ]);
            } else {
                // Jika bukan region, coba cari outlet
                $outlet = \DB::table('tbl_data_outlet')->where('nama_outlet', $regionOutlet)->first();
                if ($outlet) {
                    $outletId = $outlet->id_outlet;
                    $availabilityPriceType = 'outlet';
                    \Log::info('PriceUpdateImport@updateItemPrice - Found as outlet', [
                        'region_outlet' => $regionOutlet,
                        'outlet_id' => $outlet->id_outlet,
                        'original_price_type' => $priceType
                    ]);
                } else {
                    // Jika tidak ditemukan sama sekali, throw error
                    throw new \Exception("Region/Outlet '{$regionOutlet}' tidak ditemukan untuk item '{$item->name}'. Pastikan nama region/outlet sesuai dengan data master.");
                }
            }
        } else {
            // Jika regionOutlet kosong atau 'All', baru gunakan type 'all'
            $availabilityPriceType = 'all';
            \Log::info('PriceUpdateImport@updateItemPrice - Using all type', [
                'region_outlet' => $regionOutlet
            ]);
        }

        \Log::info('PriceUpdateImport@updateItemPrice - Before updateOrCreate', [
            'item_id' => $item->id,
            'region_id' => $regionId,
            'outlet_id' => $outletId,
            'availability_price_type' => $availabilityPriceType,
            'new_price' => $newPrice,
            'price_type_input' => $priceType,
            'region_outlet_input' => $regionOutlet
        ]);

        // Cari existing price berdasarkan item_id, region_id, outlet_id, dan availability_price_type
        // Ini penting untuk memastikan kita update price yang tepat, bukan membuat baru
        $existingPrice = ItemPrice::where('item_id', $item->id)
            ->where(function($q) use ($regionId, $outletId, $availabilityPriceType) {
                // Match berdasarkan region_id dan outlet_id
                if ($availabilityPriceType === 'region' && $regionId) {
                    $q->where('region_id', $regionId)
                      ->whereNull('outlet_id');
                } elseif ($availabilityPriceType === 'outlet' && $outletId) {
                    $q->where('outlet_id', $outletId)
                      ->whereNull('region_id');
                } else {
                    // Type 'all'
                    $q->whereNull('region_id')
                      ->whereNull('outlet_id');
                }
            })
            ->first();
        
        \Log::info('PriceUpdateImport@updateItemPrice - Existing price check', [
            'existing_price_found' => $existingPrice ? true : false,
            'existing_price' => $existingPrice ? $existingPrice->price : null,
            'existing_price_type' => $existingPrice ? $existingPrice->availability_price_type : null,
            'existing_region_id' => $existingPrice ? $existingPrice->region_id : null,
            'existing_outlet_id' => $existingPrice ? $existingPrice->outlet_id : null
        ]);

        // Update atau create price dengan kondisi yang tepat
        // Pastikan kita update price yang tepat berdasarkan region_id/outlet_id yang spesifik
        // JANGAN update type 'all' jika ada region/outlet yang spesifik
        if ($availabilityPriceType === 'region' && $regionId) {
            // Update price untuk region spesifik - cari existing dengan kondisi yang tepat
            $existing = ItemPrice::where('item_id', $item->id)
                ->where('region_id', $regionId)
                ->whereNull('outlet_id')
                ->first();
            
            if ($existing) {
                // Update existing
                $existing->update([
                    'price' => $newPrice,
                    'availability_price_type' => 'region',
                ]);
                $result = $existing;
            } else {
                // Create new
                $result = ItemPrice::create([
                    'item_id' => $item->id,
                    'region_id' => $regionId,
                    'outlet_id' => null,
                    'price' => $newPrice,
                    'availability_price_type' => 'region',
                ]);
            }
            
            \Log::info('PriceUpdateImport@updateItemPrice - Updated/Created region price', [
                'item_id' => $item->id,
                'region_id' => $regionId,
                'region_name' => $regionOutlet,
                'price' => $newPrice,
                'was_recently_created' => $result->wasRecentlyCreated ?? false
            ]);
        } elseif ($availabilityPriceType === 'outlet' && $outletId) {
            // Update price untuk outlet spesifik - cari existing dengan kondisi yang tepat
            $existing = ItemPrice::where('item_id', $item->id)
                ->where('outlet_id', $outletId)
                ->whereNull('region_id')
                ->first();
            
            if ($existing) {
                // Update existing
                $existing->update([
                    'price' => $newPrice,
                    'availability_price_type' => 'outlet',
                ]);
                $result = $existing;
            } else {
                // Create new
                $result = ItemPrice::create([
                    'item_id' => $item->id,
                    'outlet_id' => $outletId,
                    'region_id' => null,
                    'price' => $newPrice,
                    'availability_price_type' => 'outlet',
                ]);
            }
            
            \Log::info('PriceUpdateImport@updateItemPrice - Updated/Created outlet price', [
                'item_id' => $item->id,
                'outlet_id' => $outletId,
                'outlet_name' => $regionOutlet,
                'price' => $newPrice,
                'was_recently_created' => $result->wasRecentlyCreated ?? false
            ]);
        } else {
            // Type 'all' - HANYA jika memang regionOutlet = 'All' atau kosong
            // JANGAN buat type 'all' jika ada region/outlet name yang spesifik tapi tidak ditemukan
            if ($regionOutlet === 'All' || empty($regionOutlet) || trim($regionOutlet) === '') {
                $existing = ItemPrice::where('item_id', $item->id)
                    ->whereNull('region_id')
                    ->whereNull('outlet_id')
                    ->first();
                
                if ($existing) {
                    // Update existing
                    $existing->update([
                        'price' => $newPrice,
                        'availability_price_type' => 'all',
                    ]);
                    $result = $existing;
                } else {
                    // Create new
                    $result = ItemPrice::create([
                        'item_id' => $item->id,
                        'region_id' => null,
                        'outlet_id' => null,
                        'price' => $newPrice,
                        'availability_price_type' => 'all',
                    ]);
                }
                
                \Log::info('PriceUpdateImport@updateItemPrice - Updated/Created all price', [
                    'item_id' => $item->id,
                    'price' => $newPrice,
                    'was_recently_created' => $result->wasRecentlyCreated ?? false
                ]);
            } else {
                // Jika region/outlet name ada tapi tidak ditemukan, throw error
                throw new \Exception("Region/Outlet '{$regionOutlet}' tidak ditemukan untuk item '{$item->name}'. Pastikan nama region/outlet sesuai dengan data master.");
            }
        }

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
            'new_price_isi_untuk_update_kosongkan_jika_tidak_diubah' => 'nullable|numeric|min:0',
            'price_type' => 'nullable|in:all,region,outlet,All,Region,Outlet',
            'region_outlet_name' => 'nullable|string',
            'region_outlet' => 'nullable|string',
            'validation_hash_jangan_diubah' => 'nullable|string',
            'validation_jangan_diubah' => 'nullable|string',
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