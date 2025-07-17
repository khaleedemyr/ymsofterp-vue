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

    public function collection(Collection $rows)
    {
        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                // Skip baris yang semua kolomnya kosong
                if (collect($row)->filter()->isEmpty()) {
                    continue;
                }

                try {
                    // Validasi data
                    if (empty($row['item_id']) || empty($row['sku']) || empty($row['item_name'])) {
                        throw new \Exception('Item ID, SKU, dan Item Name wajib diisi');
                    }

                    // Validasi hash untuk memastikan data tidak berubah
                    $expectedHash = md5($row['item_id'] . $row['sku'] . $row['item_name']);
                    if ($row['validation_jangan_diubah'] !== $expectedHash) {
                        throw new \Exception('Data item telah berubah, silakan download template terbaru');
                    }

                    // Cek item
                    $item = Item::where('id', $row['item_id'])
                        ->where('sku', $row['sku'])
                        ->where('name', $row['item_name'])
                        ->where('status', 'active')
                        ->first();
                    
                    if (!$item) {
                        throw new \Exception('Item tidak ditemukan atau tidak aktif');
                    }

                    // Validasi new price - jika kosong, skip item ini
                    if (empty($row['new_price']) || $row['new_price'] === '' || $row['new_price'] === null) {
                        $this->successCount++;
                        $this->updatedItems[] = [
                            'row' => $index + 2,
                            'name' => $row['item_name'],
                            'status' => 'success',
                            'message' => 'Skipped (no price change)',
                        ];
                        continue; // Skip item yang tidak diupdate
                    }

                    // Validasi format new price
                    if (!is_numeric($row['new_price']) || $row['new_price'] < 0) {
                        throw new \Exception('New Price harus berupa angka positif');
                    }

                    $newPrice = (float) $row['new_price'];
                    $currentPrice = (float) ($row['current_price'] ?? 0);
                    $priceType = $row['price_type'] ?? 'all';
                    $regionOutlet = $row['region_outlet'] ?? 'All';

                    // Jika harga tidak berubah, skip
                    if ($newPrice == $currentPrice) {
                        $this->successCount++;
                        $this->updatedItems[] = [
                            'row' => $index + 2,
                            'name' => $row['item_name'],
                            'status' => 'success',
                            'message' => 'Skipped (same price)',
                        ];
                        continue;
                    }

                    // Update atau create price
                    $this->updateItemPrice($item, $newPrice, $priceType, $regionOutlet);

                    // Log perubahan
                    $this->logPriceChange($item, $currentPrice, $newPrice, $priceType, $regionOutlet);

                    $this->successCount++;
                    $this->updatedItems[] = [
                        'row' => $index + 2,
                        'name' => $row['item_name'],
                        'status' => 'success',
                        'message' => 'Successfully updated',
                    ];

                } catch (\Exception $e) {
                    $this->errorCount++;
                    $this->errors[] = [
                        'row' => $index + 2,
                        'item' => $row['item_name'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function updateItemPrice($item, $newPrice, $priceType, $regionOutlet)
    {
        // Tentukan region_id dan outlet_id berdasarkan price type dan region/outlet
        $regionId = null;
        $outletId = null;
        $availabilityPriceType = 'all';

        if ($priceType === 'region' && $regionOutlet !== 'All') {
            $region = \DB::table('regions')->where('name', $regionOutlet)->first();
            if ($region) {
                $regionId = $region->id;
                $availabilityPriceType = 'region';
            }
        } elseif ($priceType === 'outlet' && $regionOutlet !== 'All') {
            $outlet = \DB::table('tbl_data_outlet')->where('nama_outlet', $regionOutlet)->first();
            if ($outlet) {
                $outletId = $outlet->id_outlet;
                $availabilityPriceType = 'outlet';
            }
        }

        // Update atau create price
        ItemPrice::updateOrCreate(
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