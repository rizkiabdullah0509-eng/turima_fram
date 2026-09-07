<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ScheduleController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ScheduleWeekDateTest extends TestCase
{
    public function testSundayFromTheLegacyClientResolvesToTheFollowingMonday(): void
    {
        $method = new ReflectionMethod(ScheduleController::class, 'normalizeWeekStart');
        $controller = new ScheduleController;

        self::assertSame('2026-08-31', $method->invoke($controller, '2026-08-30'));
    }

    public function testMondayRemainsTheSameWeekStart(): void
    {
        $method = new ReflectionMethod(ScheduleController::class, 'normalizeWeekStart');
        $controller = new ScheduleController;

        self::assertSame('2026-08-31', $method->invoke($controller, '2026-08-31'));
    }
}
