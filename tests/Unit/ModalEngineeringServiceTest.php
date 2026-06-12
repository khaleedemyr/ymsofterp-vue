<?php

namespace Tests\Unit;

use App\Services\ModalEngineeringService;
use PHPUnit\Framework\TestCase;

class ModalEngineeringServiceTest extends TestCase
{
    public function test_empty_totals_shape(): void
    {
        $service = new ModalEngineeringService;
        $totals = $service->totalsForPeriod(0, '2026-06-01', '2026-06-30');

        $this->assertSame(0.0, $totals['stock_cut']);
        $this->assertSame(0.0, $totals['total_modal']);
        $this->assertNull($totals['modal_x_engineering_pct']);
    }
}
