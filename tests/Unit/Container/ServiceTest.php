<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Tests\Unit\Container;

use Contenir\Audience\Mezzio\Container\InvalidConfiguration;
use Contenir\Audience\Mezzio\Container\Service;
use Contenir\Audience\Mezzio\Tests\TestAsset\ArrayContainer;
use Contenir\Audience\Testing\MutableClock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use stdClass;

#[Group('unit')]
final class ServiceTest extends TestCase
{
    #[Test]
    public function givesTheNamedServiceWhenItIsTheTypeNeeded(): void
    {
        $clock = new MutableClock();

        static::assertSame(
            $clock,
            Service::get(new ArrayContainer(['clock' => $clock]), ClockInterface::class, 'clock'),
        );
    }

    #[Test]
    public function refusesANamedServiceOfAnotherType(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('The service clock is not a Psr\Clock\ClockInterface');

        Service::get(new ArrayContainer(['clock' => new stdClass()]), ClockInterface::class, 'clock');
    }
}
