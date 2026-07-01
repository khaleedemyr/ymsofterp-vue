<?php

namespace Tests\Unit;

use App\Support\OutletInventoryCostResolver;
use PHPUnit\Framework\TestCase;

class OutletInventoryCostResolverTest extends TestCase
{
    public function test_sanitize_mac_replaces_corrupt_old_mac(): void
    {
        $sanitized = OutletInventoryCostResolver::sanitizeMacForWeightedAverage(32_419.87, 51.8209);

        $this->assertEqualsWithDelta(51.8209, $sanitized, 0.0001);
    }

    public function test_sanitize_mac_keeps_reasonable_old_mac(): void
    {
        $sanitized = OutletInventoryCostResolver::sanitizeMacForWeightedAverage(52.0, 51.8209);

        $this->assertEqualsWithDelta(52.0, $sanitized, 0.0001);
    }

    public function test_weighted_average_converges_toward_inbound_cost(): void
    {
        $mac = OutletInventoryCostResolver::weightedAverageMacPerSmall(32_495, 32_419.87, 335, 51.8209);

        $this->assertLessThan(1000, $mac);
        $this->assertGreaterThan(51.0, $mac);
    }

    public function test_mac_anomaly_detection(): void
    {
        $this->assertTrue(OutletInventoryCostResolver::macLooksAnomalousVsAnchor(32_419.87, 51.8209));
        $this->assertFalse(OutletInventoryCostResolver::macLooksAnomalousVsAnchor(52.0, 51.8209));
    }

    public function test_mac_rates_per_small_medium_large(): void
    {
        $itemMaster = (object) [
            'small_conversion_qty' => 10,
            'medium_conversion_qty' => 5,
        ];

        [$small, $medium, $large] = OutletInventoryCostResolver::macRatesPerSmallMediumLarge(2.5, $itemMaster);

        $this->assertEqualsWithDelta(2.5, $small, 0.0001);
        $this->assertEqualsWithDelta(25.0, $medium, 0.0001);
        $this->assertEqualsWithDelta(125.0, $large, 0.0001);
    }
}
