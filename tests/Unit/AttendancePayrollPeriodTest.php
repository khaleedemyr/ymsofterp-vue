<?php

namespace Tests\Unit;

use App\Support\AttendancePayrollPeriod;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AttendancePayrollPeriodTest extends TestCase
{
    public function test_scan_query_end_includes_day_after_period_for_cross_day_out(): void
    {
        $period = AttendancePayrollPeriod::forMonth(8, 2026);

        $this->assertSame('2026-08-25', $period['end']);
        $this->assertSame('2026-08-27 00:00:00', AttendancePayrollPeriod::scanQueryEndExclusive($period['end']));
    }

    public function test_running_stays_on_calendar_month_before_the_26th(): void
    {
        $period = AttendancePayrollPeriod::running(new DateTimeImmutable('2026-08-25'));

        $this->assertSame(8, $period['bulan']);
        $this->assertSame('2026-07-26', $period['start']);
        $this->assertSame('2026-08-25', $period['end']);
    }

    public function test_running_rolls_to_next_cycle_from_the_26th(): void
    {
        $period = AttendancePayrollPeriod::running(new DateTimeImmutable('2026-08-26'));

        $this->assertSame(9, $period['bulan']);
        $this->assertSame('2026-08-26', $period['start']);
        $this->assertSame('2026-09-25', $period['end']);
    }

    public function test_hrd_queue_on_aug_27_includes_leave_dated_aug_26(): void
    {
        $queue = AttendancePayrollPeriod::forHrdApprovalQueue(new DateTimeImmutable('2026-08-27'));

        $this->assertSame('2026-07-26', $queue['start']);
        $this->assertSame('2026-09-25', $queue['end']);
        $this->assertTrue($queue['start'] <= '2026-08-26' && '2026-08-26' <= $queue['end']);
    }
}
