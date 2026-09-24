<?php

namespace Tests\Unit;

use App\Services\KpiEvaluationService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class KpiPersonTargetScoringTest extends TestCase
{
    public function test_plain_person_target_without_gte_prefix_is_parsed(): void
    {
        $bounds = $this->invoke('parseTargetValue', ['12 Person & 100% on Time']);

        $this->assertSame('gte', $bounds['comparator']);
        $this->assertSame(12.0, $bounds['min']);
    }

    public function test_twelve_persons_exceeds_jng_target(): void
    {
        $scoring = $this->invoke('scoreItem', [
            12.0,
            'higher_better',
            ['meeting_min' => 85, 'exceeding_min' => 100],
            '12 Person & 100% on Time',
        ]);

        $this->assertSame('exceeding', $scoring['level']);
        $this->assertSame(100.0, $scoring['score']);
    }

    public function test_four_persons_against_twelve_stays_below(): void
    {
        $scoring = $this->invoke('scoreItem', [
            4.0,
            'higher_better',
            ['meeting_min' => 85, 'exceeding_min' => 100],
            '12 Person & 100% on Time',
        ]);

        $this->assertSame('below', $scoring['level']);
        $this->assertGreaterThan(0.0, $scoring['score']);
        $this->assertLessThan(85.0, $scoring['score']);
    }

    public function test_gte_person_target_still_works(): void
    {
        $scoring = $this->invoke('scoreItem', [
            2.0,
            'higher_better',
            ['meeting_min' => 85, 'exceeding_min' => 100],
            '>= 2 Person & 100% on Time',
        ]);

        $this->assertSame('exceeding', $scoring['level']);
        $this->assertSame(100.0, $scoring['score']);
    }

    public function test_empty_jng_achievement_is_below_zero(): void
    {
        $scoring = $this->invoke('scoreItem', [
            null,
            'higher_better',
            ['meeting_min' => 85, 'exceeding_min' => 100],
            '12 Person & 100% on Time',
        ]);

        $this->assertSame('below', $scoring['level']);
        $this->assertSame(0.0, $scoring['score']);
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
