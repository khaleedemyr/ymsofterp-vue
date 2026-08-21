<?php

namespace Tests\Unit;

use App\Services\OvertimeSubmissionFilterService;
use PHPUnit\Framework\TestCase;

class OvertimeSubmissionFilterServiceTest extends TestCase
{
    private OvertimeSubmissionFilterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OvertimeSubmissionFilterService();
    }

    public function test_approved_submission_does_not_cap_actual_hours(): void
    {
        $this->assertFalse(OvertimeSubmissionFilterService::CAP_BY_SUBMISSION);

        $result = $this->service->applyToDay(5, 0, ['hours' => 2, 'reason' => 'Event']);

        $this->assertSame(5.0, $result['lembur']);
        $this->assertSame(5.0, $result['total_lembur']);
        $this->assertSame(2.0, $result['overtime_submission_hours']);
        $this->assertSame('Event', $result['overtime_submission_reason']);
    }

    public function test_cap_hours_keeps_attendance_when_submission_cap_is_off(): void
    {
        $this->assertSame(5.0, $this->service->capHours(5, 2));
        $this->assertSame(3.0, $this->service->capHours(3, null));
    }

    public function test_actual_one_hour_stays_one_when_submission_is_two(): void
    {
        $result = $this->service->applyToDay(1, 0, ['hours' => 2, 'reason' => 'Event']);

        $this->assertSame(1.0, $result['lembur']);
        $this->assertSame(1.0, $result['total_lembur']);
        $this->assertSame(2.0, $result['overtime_submission_hours']);
    }

    public function test_without_submission_keeps_actual_hours(): void
    {
        $result = $this->service->applyToDay(3, 0, null);

        $this->assertSame(3.0, $result['lembur']);
        $this->assertSame(3.0, $result['total_lembur']);
        $this->assertNull($result['overtime_submission_hours']);
    }
}
