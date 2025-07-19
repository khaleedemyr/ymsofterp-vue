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
        if (is_object($row)) {
            return $row->$key ?? $default;
        }
        return $row[$key] ?? $default;
    }

    public function collection(Collection $rows)
    {
        \Log::info('PriceUpdateImport@collection - Starting import', [
            'total_rows' => $rows->count()
        ]);

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                // Skip baris yang semua kolomnya kosong
                if (collect($row)->filter()->isEmpty()) {
                    \Log::info('PriceUpdateImport@collection - Skipping empty row', ['row_index' => $index + 2]);
                    continue;
                }

                \Log::info('PriceUpdateImport@collection - Processing row', [
                    'row_index' => $index + 2,
                    'row_data' => is_object($row) ? (array) $row : $row
                ]);

                try {
                    // Validasi data
                    if (empty($this->getRowValue($row, 'item_id')) || empty($this->getRowValue($row, 'sku')) || empty($this->getRowValue($row, 'item_name'))) {
                        throw new \Exception('Item ID, SKU, dan Item Name wajib diisi');
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
                    
                    // Cek berbagai kemungkinan nama kolom untuk new price
                    if (!empty($this->getRowValue($row, 'new_price'))) {
                        $newPrice = $this->getRowValue($row, 'new_price');
                    } elseif (!empty($this->getRowValue($row, 'new_price_kosongkan_jika_tidak_diupdate'))) {
                        $newPrice = $this->getRowValue($row, 'new_price_kosongkan_jika_tidak_diupdate');
                    }
                    
                    \Log::info('PriceUpdateImport@collection - New price extraction', [
                        'item_name' => $this->getRowValue($row, 'item_name'),
                        'new_price_direct' => $this->getRowValue($row, 'new_price', 'NOT_FOUND'),
                        'new_price_long' => $this->getRowValue($row, 'new_price_kosongkan_jika_tidak_diupdate', 'NOT_FOUND'),
                        'extracted_new_price' => $newPrice
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
                    $priceType = $this->getRowValue($row, 'price_type', 'all');
                    $regionOutlet = $this->getRowValue($row, 'region_outlet', 'All');

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
                        'item_name' => $this->getRowValue($row, 'item_name'),
                        'old_price' => $currentPrice,
                        'new_price' => $newPrice,
                        'update_result_id' => $updateResult->id
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
                'error_count' => $this->errorCount
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