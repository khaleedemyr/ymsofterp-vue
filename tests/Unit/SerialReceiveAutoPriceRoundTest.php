<?php

namespace Tests\Unit;

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FoodGrLastPurchaseForItem;
use App\Support\ItemUnitCost;
use App\Support\SerialReceiveItemPriceResolver;
use PHPUnit\Framework\TestCase;

class SerialReceiveAutoPriceRoundTest extends TestCase
{
    public function test_suggested_selling_price_rounds_up_to_nearest_hundred(): void
    {
        $rounded = FloorOrderItemPriceResolver::roundUpToHundred(11256.78);

        $this->assertSame(11300.0, $rounded);
    }

    public function test_auto_cost_small_yields_unit_price_in_hundred_increments(): void
    {
        $item = (object) [
            'id' => 1,
            'small_unit_id' => 10,
            'medium_unit_id' => 20,
            'large_unit_id' => 30,
            'small_conversion_qty' => 150,
            'medium_conversion_qty' => 1,
        ];

        $serial = (object) [
            'unit_id' => 10,
            'cost_small' => 0,
            'source_type' => 'good_receive',
        ];

        $priceLarge = FloorOrderItemPriceResolver::roundUpToHundred(10050 * 1.12);
        $costSmall = SerialReceiveItemPriceResolver::itemPriceLargeToCostSmall($priceLarge, $item);
        $unitPrice = ItemUnitCost::priceForUnit($costSmall, $item, $serial->unit_id);
        $roundedUnit = FloorOrderItemPriceResolver::roundUpToHundred($unitPrice);
        $finalCostSmall = ItemUnitCost::costSmallFromUnitPrice($roundedUnit, $item, $serial->unit_id);
        $displayPrice = ItemUnitCost::priceForUnit($finalCostSmall, $item, $serial->unit_id);

        $this->assertSame(0.0, fmod($displayPrice, 100));
        $this->assertGreaterThanOrEqual($unitPrice, $displayPrice);
    }
}
