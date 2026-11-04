<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Tests\Unit\Container;

use Contenir\Audience\Mezzio\Container\InvalidConfiguration;
use Contenir\Audience\Mezzio\Container\SubscriptionsFactory;
use Contenir\Audience\Mezzio\Subscriptions;
use Contenir\Audience\Mezzio\Tests\TestAsset\ArrayContainer;
use Contenir\Audience\Mezzio\Tests\TestAsset\Factory\SiteConfig;
use Contenir\Audience\Testing\MutableClock;
use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SubscriptionsFactoryTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableConfigs(): array
    {
        return [
            'not a list' => ['contenir_audience'],
            'empty'      => [[]],
        ];
    }

    #[Test]
    public function buildsThemWithTheSystemsClockWhenNoneIsNamed(): void
    {
        $container = $this->container(SiteConfig::make(['contenir_audience' => ['clock' => null]]), clock: null);

        static::assertInstanceOf(Subscriptions::class, (new SubscriptionsFactory())($container));
    }

    #[Test]
    public function buildsTheSitesSubscriptions(): void
    {
        static::assertInstanceOf(Subscriptions::class, (new SubscriptionsFactory())($this->container()));
    }

    #[Test]
    public function refusesAClockServiceThatIsNotOne(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('The service db is not a Psr\Clock\ClockInterface');

        (new SubscriptionsFactory())($this->container(SiteConfig::make(['contenir_audience' => ['clock' => 'db']])));
    }

    #[Test]
    public function refusesAnAdapterServiceThatIsNotOne(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('The service clock is not a PhpDb\Adapter\AdapterInterface');

        (new SubscriptionsFactory())($this->container(SiteConfig::make(['contenir_audience' => [
            'adapter' => 'clock',
        ]])));
    }

    #[Test]
    #[DataProvider('unusableConfigs')]
    public function refusesAnApplicationConfigWithoutItsOptions(mixed $config): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('The application has no contenir_audience configuration');

        (new SubscriptionsFactory())($this->container($config));
    }

    private function container(mixed $config = null, ?MutableClock $clock = new MutableClock()): ArrayContainer
    {
        $services = ['config' => $config ?? SiteConfig::make(), 'db' => $this->createStub(AdapterInterface::class)];

        return new ArrayContainer(null === $clock ? $services : [...$services, 'clock' => $clock]);
    }
}
