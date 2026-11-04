<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Tests\Unit\Clock;

use Contenir\Audience\Mezzio\Clock\SystemClock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function time;

#[Group('unit')]
final class SystemClockTest extends TestCase
{
    #[Test]
    public function tellsTheSystemsTime(): void
    {
        $before = time();
        $now    = (new SystemClock())->now()->getTimestamp();

        static::assertSame([true, true], [$now >= $before, $now <= time()]);
    }
}
