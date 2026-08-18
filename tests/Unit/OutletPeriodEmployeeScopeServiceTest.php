<?php

namespace Tests\Unit;

use App\Services\OutletPeriodEmployeeScopeService;
use PHPUnit\Framework\TestCase;

class OutletPeriodEmployeeScopeServiceTest extends TestCase
{
    private OutletPeriodEmployeeScopeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OutletPeriodEmployeeScopeService();
    }

    public function test_from_role_keeps_days_before_effective_date(): void
    {
        $scope = [
            'include_user_ids' => [10],
            'mutation_map' => [
                10 => [
                    'effective_date' => '2026-08-10',
                    'role' => 'from',
                ],
            ],
            'resignations' => [],
        ];

        $segment = $this->service->segmentForUser(10, '2026-07-26', '2026-08-25', $scope);

        $this->assertSame('2026-07-26', $segment['start']);
        $this->assertSame('2026-08-09', $segment['end']);
        $this->assertTrue($this->service->dateInScope(10, '2026-08-09', '2026-07-26', '2026-08-25', $scope));
        $this->assertFalse($this->service->dateInScope(10, '2026-08-10', '2026-07-26', '2026-08-25', $scope));
    }

    public function test_to_role_keeps_days_from_effective_date(): void
    {
        $scope = [
            'include_user_ids' => [10],
            'mutation_map' => [
                10 => [
                    'effective_date' => '2026-08-10',
                    'role' => 'to',
                ],
            ],
            'resignations' => [],
        ];

        $segment = $this->service->segmentForUser(10, '2026-07-26', '2026-08-25', $scope);

        $this->assertSame('2026-08-10', $segment['start']);
        $this->assertSame('2026-08-25', $segment['end']);
        $this->assertFalse($this->service->dateInScope(10, '2026-08-09', '2026-07-26', '2026-08-25', $scope));
        $this->assertTrue($this->service->dateInScope(10, '2026-08-10', '2026-07-26', '2026-08-25', $scope));
    }

    public function test_resignation_cuts_segment_end(): void
    {
        $scope = [
            'include_user_ids' => [11],
            'mutation_map' => [],
            'resignations' => [
                11 => '2026-08-05',
            ],
        ];

        $segment = $this->service->segmentForUser(11, '2026-07-26', '2026-08-25', $scope);

        $this->assertSame('2026-07-26', $segment['start']);
        $this->assertSame('2026-08-05', $segment['end']);
        $this->assertFalse($this->service->dateInScope(11, '2026-08-06', '2026-07-26', '2026-08-25', $scope));
    }

    public function test_regular_employee_keeps_full_period(): void
    {
        $scope = [
            'include_user_ids' => [],
            'mutation_map' => [],
            'resignations' => [],
        ];

        $segment = $this->service->segmentForUser(99, '2026-07-26', '2026-08-25', $scope);

        $this->assertSame('2026-07-26', $segment['start']);
        $this->assertSame('2026-08-25', $segment['end']);
        $this->assertTrue($this->service->dateInScope(99, '2026-08-20', '2026-07-26', '2026-08-25', $scope));
    }
}
