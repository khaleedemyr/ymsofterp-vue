<?php

namespace Tests\Unit;

use App\Services\KpiParameterResolverService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

class KpiInductionOnTimeTest extends TestCase
{
    public function test_backdated_start_uses_created_at_so_week_one_can_be_on_time(): void
    {
        $week1 = $this->submission(1, 'approved', '2026-08-12 10:52:23', '2026-08-12 19:20:58');

        [$onTime, $total] = $this->score(
            Carbon::parse('2026-08-07')->startOfDay(),
            2,
            8,
            [$week1],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-08-31')->endOfDay(),
            Carbon::parse('2026-08-20 10:30:00'),
        );

        $this->assertSame(1, $onTime);
        $this->assertSame(2, $total);
    }

    public function test_locked_future_weeks_are_not_counted_as_missed(): void
    {
        [$onTime, $total] = $this->score(
            Carbon::parse('2026-07-21')->startOfDay(),
            1,
            8,
            [],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-08-31')->endOfDay(),
            Carbon::parse('2026-08-20 10:30:00'),
        );

        $this->assertSame(0, $onTime);
        $this->assertSame(1, $total);
    }

    public function test_week_not_yet_due_is_skipped(): void
    {
        [$onTime, $total] = $this->score(
            Carbon::parse('2026-08-17')->startOfDay(),
            1,
            8,
            [],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-08-31')->endOfDay(),
            Carbon::parse('2026-08-20 10:30:00'),
        );

        $this->assertSame(0, $onTime);
        $this->assertSame(0, $total);
    }

    /**
     * @param  list<object>  $submissions
     * @return array{0: int, 1: int}
     */
    private function score(
        Carbon $clockStart,
        int $unlockedWeek,
        int $totalWeeks,
        array $submissions,
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $now,
    ): array {
        $ref = new ReflectionClass(KpiParameterResolverService::class);
        $fn = $ref->getMethod('scoreInductionWeeksOnTime');
        $fn->setAccessible(true);

        return $fn->invoke(null, $clockStart, $unlockedWeek, $totalWeeks, $submissions, $periodStart, $periodEnd, $now);
    }

    private function submission(int $week, string $status, string $submittedAt, string $approvedAt): stdClass
    {
        $row = new stdClass;
        $row->week_number = $week;
        $row->status = $status;
        $row->submitted_at = $submittedAt;
        $row->approved_at = $approvedAt;

        return $row;
    }
}
