<?php

namespace Tests\Unit;

use App\Services\AttendanceWorkTimelineService;
use PHPUnit\Framework\TestCase;

class AttendanceWorkTimelineServiceTest extends TestCase
{
    private AttendanceWorkTimelineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AttendanceWorkTimelineService();
    }

    public function test_pulang_kembali_excludes_break_gap(): void
    {
        $minutes = $this->service->calculateWorkMinutes([
            ['scan_date' => '2026-07-01 08:00:00', 'inoutmode' => 1, 'outlet_id' => 1],
            ['scan_date' => '2026-07-01 12:00:00', 'inoutmode' => 2, 'outlet_id' => 1],
            ['scan_date' => '2026-07-01 14:30:00', 'inoutmode' => 4, 'outlet_id' => 1],
            ['scan_date' => '2026-07-01 17:00:00', 'inoutmode' => 2, 'outlet_id' => 1],
        ]);

        $this->assertSame(390, $minutes); // 4h + 2.5h
    }

    public function test_outlet_transfer_includes_travel_gap(): void
    {
        $minutes = $this->service->calculateWorkMinutes([
            ['scan_date' => '2026-07-01 08:00:00', 'inoutmode' => 1, 'outlet_id' => 1],
            ['scan_date' => '2026-07-01 11:00:00', 'inoutmode' => 2, 'outlet_id' => 1],
            ['scan_date' => '2026-07-01 11:25:00', 'inoutmode' => 1, 'outlet_id' => 2],
            ['scan_date' => '2026-07-01 17:00:00', 'inoutmode' => 2, 'outlet_id' => 2],
        ]);

        $this->assertSame(540, $minutes); // 3h + 25m transfer + 5h35m
    }

    public function test_overtime_from_work_minutes_vs_shift(): void
    {
        $workMinutes = 600; // 10 jam
        $hours = $this->service->calculateOvertimeHours($workMinutes, '08:00:00', '17:00:00');

        $this->assertSame(1, $hours); // 10 - 9 = 1 jam lembur
    }

    public function test_process_day_sets_last_outlet_and_cross_day(): void
    {
        $all = [
            '10_2026-07-01' => [
                'tanggal' => '2026-07-01',
                'user_id' => 10,
                'scans' => [
                    ['scan_date' => '2026-07-01 22:00:00', 'inoutmode' => 1, 'outlet_id' => 3],
                ],
            ],
            '10_2026-07-02' => [
                'tanggal' => '2026-07-02',
                'user_id' => 10,
                'scans' => [
                    ['scan_date' => '2026-07-02 06:00:00', 'inoutmode' => 2, 'outlet_id' => 3],
                ],
            ],
        ];

        $result = $this->service->processDay($all['10_2026-07-01'], $all);

        $this->assertTrue($result['is_cross_day']);
        $this->assertSame(480, $result['work_minutes']);
        $this->assertSame(3, $result['last_outlet_id']);
        $this->assertEmpty($all['10_2026-07-02']['scans']);
    }
}
