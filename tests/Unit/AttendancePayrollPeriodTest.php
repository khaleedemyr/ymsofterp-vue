<?php

namespace Tests\Unit;

use App\Support\AttendancePayrollPeriod;
use PHPUnit\Framework\TestCase;

class AttendancePayrollPeriodTest extends TestCase
{
    public function test_scan_query_end_includes_day_after_period_for_cross_day_out(): void
    {
        $period = AttendancePayrollPeriod::forMonth(8, 2026);

        $this->assertSame('2026-08-25', $period['end']);
        $this->assertSame('2026-08-27 00:00:00', AttendancePayrollPeriod::scanQueryEndExclusive($period['end']));
    }
}
