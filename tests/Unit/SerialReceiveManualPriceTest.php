<?php

namespace Tests\Unit;

use App\Support\ItemUnitCost;
use PHPUnit\Framework\TestCase;

/**
 * Konversi harga manual large → tampilan per Pcs (beef 150gr: large / 150).
 */
class SerialReceiveManualPriceTest extends TestCase
{
    public function test_manual_large_price_converts_to_expected_pcs_display(): void
    {
        $item = (object) [
            'small_unit_id' => 1,
            'medium_unit_id' => 2,
            'large_unit_id' => 3,
            'small_conversion_qty' => 150,
            'medium_conversion_qty' => 1,
        ];

        $priceLarge = 58400.0;
        $costSmall = round($priceLarge / 150, 4);
        $pcsPrice = ItemUnitCost::priceForUnit($costSmall, $item, $item->small_unit_id);

        $this->assertEqualsWithDelta(389.3333, $costSmall, 0.0001);
        $this->assertEqualsWithDelta(389.3333, $pcsPrice, 0.0001);
    }
}
