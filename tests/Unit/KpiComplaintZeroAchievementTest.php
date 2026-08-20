<?php

namespace Tests\Unit;

use App\Services\KpiEvaluationService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class KpiComplaintZeroAchievementTest extends TestCase
{
    public function test_zero_divided_by_zero_counts_as_zero_ratio(): void
    {
        $result = $this->invoke('evaluateFormula', ['D054 / D055 * 100', [
            'D054' => 0.0,
            'D055' => 0.0,
        ]]);

        $this->assertSame(0.0, $result);
    }

    public function test_nonzero_divided_by_zero_stays_null(): void
    {
        $result = $this->invoke('evaluateFormula', ['D054 / D055 * 100', [
            'D054' => 2.0,
            'D055' => 0.0,
        ]]);

        $this->assertNull($result);
    }

    public function test_zero_complaints_over_reviews_is_zero_percent(): void
    {
        $result = $this->invoke('evaluateFormula', ['D054 / D055 * 100', [
            'D054' => 0.0,
            'D055' => 40.0,
        ]]);

        $this->assertSame(0.0, $result);
    }

    public function test_zero_hours_meets_max_24_hour_target(): void
    {
        $scoring = $this->invoke('scoreItem', [
            0.0,
            'lower_better',
            ['meeting_min' => 85, 'exceeding_min' => 100],
            '<= 24 hours',
        ]);

        $this->assertSame('exceeding', $scoring['level']);
        $this->assertSame(100.0, $scoring['score']);
    }

    public function test_zero_ratio_meets_max_half_percent_target(): void
    {
        $scoring = $this->invoke('scoreItem', [
            0.0,
            'lower_better',
            ['meeting_min' => 85, 'exceeding_min' => 100],
            '<= 0.50%',
        ]);

        $this->assertSame('exceeding', $scoring['level']);
        $this->assertSame(100.0, $scoring['score']);
    }

    public function test_zero_beverage_complaints_over_zero_orders_is_zero_percent(): void
    {
        $result = $this->invoke('evaluateFormula', ['D040 / D011 * 100', [
            'D040' => 0.0,
            'D011' => 0.0,
        ]]);

        $this->assertSame(0.0, $result);
    }

    public function test_zero_food_complaints_over_zero_orders_is_zero_percent(): void
    {
        $result = $this->invoke('evaluateFormula', ['D042 / D011 * 100', [
            'D042' => 0.0,
            'D011' => 0.0,
        ]]);

        $this->assertSame(0.0, $result);
    }

    public function test_zero_service_complaint_count_meets_lower_better_target(): void
    {
        $scoring = $this->invoke('scoreItem', [
            0.0,
            'lower_better',
            ['meeting_min' => 85, 'exceeding_min' => 100],
            '<= 0.50%',
        ]);

        $this->assertSame('exceeding', $scoring['level']);
        $this->assertSame(100.0, $scoring['score']);
    }

    /**
     * @param  list<mixed>  $args
     */
    private function invoke(string $method, array $args): mixed
    {
        $ref = new ReflectionClass(KpiEvaluationService::class);
        $service = $ref->newInstanceWithoutConstructor();
        $fn = $ref->getMethod($method);
        $fn->setAccessible(true);

        return $fn->invoke($service, ...$args);
    }
}
