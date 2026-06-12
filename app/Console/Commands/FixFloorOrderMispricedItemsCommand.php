<?php

namespace App\Console\Commands;

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixFloorOrderMispricedItemsCommand extends Command
{
    protected $signature = 'floor-order:fix-mispriced-items
                            {--dry-run : Tampilkan perubahan tanpa menulis ke DB}';

    protected $description = 'Perbaiki master + baris RO (draft/submitted) khusus Hands Glove Rubber & Onion saja';

    /** @var array<int, int> item asset duplikat => pengganti non-asset */
    private array $assetReplacements = [
        76936 => 52732, // Hand Glove Rubber -> Hands Glove Rubber
    ];

    /** Hanya item ini yang harga baris RO-nya diperbaiki (selain remap duplikat). */
    private array $priceFixItemIds = [
        53111, // Onion
        52732, // Hands Glove Rubber
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->deactivateDuplicateAssetItems($dryRun);
        $this->fixOnionMasterPrice($dryRun);
        $this->fixOpenFloorOrderLines($dryRun);

        $this->info($dryRun ? 'Dry-run selesai.' : 'Perbaikan selesai.');

        return self::SUCCESS;
    }

    private function deactivateDuplicateAssetItems(bool $dryRun): void
    {
        foreach ($this->assetReplacements as $wrongId => $correctId) {
            $wrong = DB::table('items')->where('id', $wrongId)->first(['id', 'name', 'status']);
            $correct = DB::table('items')->where('id', $correctId)->first(['id', 'name']);
            if (! $wrong || ! $correct) {
                $this->warn("Skip deaktivasi: item {$wrongId} atau {$correctId} tidak ditemukan.");

                continue;
            }
            if ($wrong->status === 'inactive') {
                $this->line("Item {$wrong->name} ({$wrongId}) sudah inactive.");

                continue;
            }
            $this->info("Nonaktifkan duplikat: {$wrong->name} ({$wrongId}) → gunakan {$correct->name} ({$correctId})");
            if (! $dryRun) {
                DB::table('items')->where('id', $wrongId)->update([
                    'status' => 'inactive',
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function fixOnionMasterPrice(bool $dryRun): void
    {
        $itemId = 53111;
        $autoLarge = FloorOrderItemPriceResolver::resolvePriceLarge($itemId, DB::table('item_prices')->where('item_id', $itemId)->orderByDesc('id')->first());
        $priceMedium = FloorOrderItemPriceResolver::roundUpToHundred(
            FloorOrderItemPriceResolver::largeToMediumPrice(
                $autoLarge,
                DB::table('items')->where('id', $itemId)->first(),
            ),
        );

        $this->info("Onion ({$itemId}): set pricing auto, harga medium Rp {$priceMedium}");

        if (! $dryRun) {
            DB::table('item_prices')
                ->where('item_id', $itemId)
                ->where('availability_price_type', 'all')
                ->whereNull('region_id')
                ->whereNull('outlet_id')
                ->update([
                    'pricing_mode' => 'auto',
                    'price' => round($autoLarge, 2),
                    'updated_at' => now(),
                ]);
        }
    }

    private function fixOpenFloorOrderLines(bool $dryRun): void
    {
        $orderIds = DB::table('food_floor_orders')
            ->whereIn('status', ['draft', 'submitted'])
            ->pluck('id');

        foreach ($orderIds as $orderId) {
            $order = DB::table('food_floor_orders')->where('id', $orderId)->first(['id', 'order_number', 'id_outlet', 'fo_mode']);
            if (! $order || in_array($order->fo_mode, ['RO Khusus', 'RO Supplier'], true)) {
                continue;
            }

            $regionId = DB::table('tbl_data_outlet')->where('id_outlet', $order->id_outlet)->value('region_id');
            $regionId = $regionId ? (int) $regionId : null;

            $lines = DB::table('food_floor_order_items')->where('floor_order_id', $orderId)->get();

            foreach ($lines as $line) {
                $itemId = (int) $line->item_id;

                if (isset($this->assetReplacements[$itemId])) {
                    $this->remapAssetLine($order, $line, $this->assetReplacements[$itemId], $regionId, $dryRun);

                    continue;
                }

                if (! in_array($itemId, $this->priceFixItemIds, true)) {
                    continue;
                }

                $expected = FloorOrderItemPriceResolver::resolveMediumUnitPrice(
                    $itemId,
                    $regionId,
                    (string) $order->id_outlet,
                );

                if ($expected <= 0 || abs((float) $line->price - $expected) < 0.01) {
                    continue;
                }

                $qty = (float) $line->qty;
                $subtotal = round($qty * $expected, 2);
                $this->line("RO {$order->order_number}: {$line->item_name} harga {$line->price} → {$expected}");

                if (! $dryRun) {
                    DB::table('food_floor_order_items')->where('id', $line->id)->update([
                        'price' => $expected,
                        'subtotal' => $subtotal,
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function remapAssetLine(object $order, object $line, int $newItemId, ?int $regionId, bool $dryRun): void
    {
        $newItem = DB::table('items')->where('id', $newItemId)->first();
        if (! $newItem) {
            return;
        }

        $unit = DB::table('units')->where('id', $newItem->medium_unit_id)->value('name')
            ?? DB::table('units')->where('id', $newItem->small_unit_id)->value('name')
            ?? $line->unit;

        $price = FloorOrderItemPriceResolver::resolveMediumUnitPrice(
            $newItemId,
            $regionId,
            (string) $order->id_outlet,
            $newItem,
        );
        $qty = (float) $line->qty;
        $subtotal = round($qty * $price, 2);

        $this->info("RO {$order->order_number}: ganti {$line->item_name} ({$line->item_id}) → {$newItem->name} ({$newItemId}), harga {$price}");

        if ($dryRun) {
            return;
        }

        $duplicate = DB::table('food_floor_order_items')
            ->where('floor_order_id', $order->id)
            ->where('item_id', $newItemId)
            ->where('id', '!=', $line->id)
            ->first();

        if ($duplicate) {
            $mergedQty = (float) $duplicate->qty + $qty;
            $mergedSubtotal = (float) $duplicate->subtotal + $subtotal;
            DB::table('food_floor_order_items')->where('id', $duplicate->id)->update([
                'qty' => $mergedQty,
                'subtotal' => $mergedSubtotal,
                'price' => $mergedQty > 0 ? round($mergedSubtotal / $mergedQty, 4) : $price,
                'updated_at' => now(),
            ]);
            DB::table('food_floor_order_items')->where('id', $line->id)->delete();
        } else {
            DB::table('food_floor_order_items')->where('id', $line->id)->update([
                'item_id' => $newItemId,
                'item_name' => $newItem->name,
                'unit' => $unit,
                'price' => $price,
                'subtotal' => $subtotal,
                'category_id' => $newItem->category_id,
                'warehouse_division_id' => $newItem->warehouse_division_id,
                'updated_at' => now(),
            ]);
        }
    }
}
