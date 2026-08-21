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

    public function test_early_check_in_is_not_overtime(): void
    {
        $hours = $this->service->calculateOvertimeHoursFromShiftOut(
            '2026-08-14 17:00:00',
            '08:00:00',
            '17:00:00',
            '2026-08-14'
        );

        $this->assertSame(0, $hours);
    }

    public function test_overtime_is_from_shift_out_not_shift_in(): void
    {
        $hours = $this->service->calculateOvertimeHoursFromShiftOut(
            '2026-08-14 22:00:00',
            '08:00:00',
            '17:00:00',
            '2026-08-14'
        );

        $this->assertSame(5, $hours);
    }

    public function test_checkout_before_shift_end_is_zero_overtime(): void
    {
        $hours = $this->service->calculateOvertimeHoursFromShiftOut(
            '2026-08-14 16:00:00',
            '08:00:00',
            '17:00:00',
            '2026-08-14'
        );

        $this->assertSame(0, $hours);
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

        $nextDay = $this->service->processDay($all['10_2026-07-02'], $all);
        $this->assertFalse(AttendanceWorkTimelineService::hasOwnCheckIn($nextDay));
        $this->assertNull($nextDay['jam_masuk']);
        $this->assertNull($nextDay['jam_keluar']);
        $this->assertSame(0, $nextDay['work_minutes']);
    }

    public function test_grouped_days_use_live_array_so_leftover_out_is_dropped(): void
    {
        $all = [
            '10_2026-08-08' => [
                'tanggal' => '2026-08-08',
                'user_id' => 10,
                'nama_lengkap' => 'Yayan Triana',
                'scans' => [
                    ['scan_date' => '2026-08-08 11:52:30', 'inoutmode' => 1, 'outlet_id' => 7],
                ],
            ],
            '10_2026-08-09' => [
                'tanggal' => '2026-08-09',
                'user_id' => 10,
                'nama_lengkap' => 'Yayan Triana',
                'scans' => [
                    ['scan_date' => '2026-08-09 00:00:29', 'inoutmode' => 2, 'outlet_id' => 7],
                ],
            ],
        ];

        $kept = [];
        foreach (array_keys($all) as $key) {
            $result = $this->service->processDay($all[$key], $all);
            if (AttendanceWorkTimelineService::hasOwnCheckIn($result)) {
                $kept[] = $result;
            }
        }

        $this->assertCount(1, $kept);
        $this->assertSame('2026-08-08', $kept[0]['tanggal']);
        $this->assertTrue($kept[0]['is_cross_day']);
        $this->assertSame('2026-08-09 00:00:29', $kept[0]['jam_keluar']);
        $this->assertEmpty($all['10_2026-08-09']['scans']);
    }

    public function test_leftover_morning_out_is_not_own_check_in(): void
    {
        $this->assertFalse(AttendanceWorkTimelineService::hasOwnCheckIn([
            'jam_masuk' => null,
            'jam_keluar' => '2026-08-09 00:00:29',
        ]));
        $this->assertTrue(AttendanceWorkTimelineService::hasOwnCheckIn([
            'jam_masuk' => '2026-08-08 11:52:30',
            'jam_keluar' => '2026-08-09 00:00:29',
        ]));
    }

    public function test_leave_range_ignores_leftover_cross_day_out(): void
    {
        $this->assertFalse(AttendanceWorkTimelineService::dateRangeHasOwnCheckIn(
            [],
            '2026-08-13',
            '2026-08-13'
        ));

        $this->assertFalse(AttendanceWorkTimelineService::dateRangeHasOwnCheckIn(
            [
                '2026-08-13' => [
                    'first_in' => null,
                    'last_out' => '01:00',
                ],
            ],
            '2026-08-13',
            '2026-08-13'
        ));

        $this->assertTrue(AttendanceWorkTimelineService::dateRangeHasOwnCheckIn(
            [
                '2026-08-13' => [
                    'first_in' => '08:00',
                    'last_out' => '17:00',
                ],
            ],
            '2026-08-13',
            '2026-08-13'
        ));
    }

    public function test_leftover_second_out_is_not_used_as_checkout_for_new_shift(): void
    {
        $all = [
            '10_2026-08-08' => [
                'tanggal' => '2026-08-08',
                'user_id' => 10,
                'scans' => [
                    ['scan_date' => '2026-08-08 13:45:00', 'inoutmode' => 1, 'outlet_id' => 23],
                ],
            ],
            '10_2026-08-09' => [
                'tanggal' => '2026-08-09',
                'user_id' => 10,
                'scans' => [
                    ['scan_date' => '2026-08-09 00:02:51', 'inoutmode' => 2, 'outlet_id' => 23],
                    ['scan_date' => '2026-08-09 00:28:00', 'inoutmode' => 2, 'outlet_id' => 23],
                    ['scan_date' => '2026-08-09 12:55:25', 'inoutmode' => 1, 'outlet_id' => 23],
                ],
            ],
        ];

        $day8 = $this->service->processDay($all['10_2026-08-08'], $all);
        $this->assertTrue($day8['is_cross_day']);
        $this->assertSame('2026-08-09 00:02:51', $day8['jam_keluar']);

        $day9 = $this->service->processDay($all['10_2026-08-09'], $all);
        $this->assertSame('2026-08-09 12:55:25', $day9['jam_masuk']);
        $this->assertNull($day9['jam_keluar']);
        $this->assertFalse($day9['is_cross_day']);
        $this->assertTrue($day9['has_no_checkout']);
    }

    public function test_day_shift_forgot_checkout_does_not_steal_next_day_in_out(): void
    {
        $all = [
            '451_2026-07-27' => [
                'tanggal' => '2026-07-27',
                'user_id' => 451,
                'scans' => [
                    ['scan_date' => '2026-07-27 08:09:23', 'inoutmode' => 1, 'outlet_id' => 1],
                ],
            ],
            '451_2026-07-28' => [
                'tanggal' => '2026-07-28',
                'user_id' => 451,
                'scans' => [
                    ['scan_date' => '2026-07-28 08:02:59', 'inoutmode' => 1, 'outlet_id' => 1],
                    ['scan_date' => '2026-07-28 17:19:29', 'inoutmode' => 2, 'outlet_id' => 1],
                ],
            ],
        ];

        $day27 = $this->service->processDay($all['451_2026-07-27'], $all);
        $this->assertSame('2026-07-27 08:09:23', $day27['jam_masuk']);
        $this->assertNull($day27['jam_keluar']);
        $this->assertFalse($day27['is_cross_day']);
        $this->assertTrue($day27['has_no_checkout']);

        $day28 = $this->service->processDay($all['451_2026-07-28'], $all);
        $this->assertSame('2026-07-28 08:02:59', $day28['jam_masuk']);
        $this->assertSame('2026-07-28 17:19:29', $day28['jam_keluar']);
        $this->assertFalse($day28['is_cross_day']);
        $this->assertTrue(AttendanceWorkTimelineService::hasOwnCheckIn($day28));
    }

    public function test_open_in_does_not_consume_next_day_in_and_kembali(): void
    {
        $all = [
            '451_2026-07-31' => [
                'tanggal' => '2026-07-31',
                'user_id' => 451,
                'scans' => [
                    ['scan_date' => '2026-07-31 07:55:01', 'inoutmode' => 1, 'outlet_id' => 1],
                ],
            ],
            '451_2026-08-01' => [
                'tanggal' => '2026-08-01',
                'user_id' => 451,
                'scans' => [
                    ['scan_date' => '2026-08-01 10:45:43', 'inoutmode' => 1, 'outlet_id' => 2],
                    ['scan_date' => '2026-08-01 18:29:40', 'inoutmode' => 4, 'outlet_id' => 1],
                ],
            ],
        ];

        $day31 = $this->service->processDay($all['451_2026-07-31'], $all);
        $this->assertSame('2026-07-31 07:55:01', $day31['jam_masuk']);
        $this->assertNull($day31['jam_keluar']);
        $this->assertTrue($day31['has_no_checkout']);

        $day1 = $this->service->processDay($all['451_2026-08-01'], $all);
        $this->assertSame('2026-08-01 10:45:43', $day1['jam_masuk']);
        $this->assertTrue(AttendanceWorkTimelineService::hasOwnCheckIn($day1));
    }
}
