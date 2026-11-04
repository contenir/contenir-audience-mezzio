<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Tests\Unit;

use Contenir\Audience\Mezzio\ConfigProvider;
use Contenir\Audience\Mezzio\Container\SubscriptionsFactory;
use Contenir\Audience\Mezzio\Subscriptions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function givesItsDependenciesApart(): void
    {
        $provider = new ConfigProvider();

        static::assertSame($provider()['dependencies'], $provider->getDependencies());
    }

    #[Test]
    public function registersTheSubscriptionsService(): void
    {
        static::assertSame(
            ['dependencies' => ['factories' => [Subscriptions::class => SubscriptionsFactory::class]]],
            (new ConfigProvider())(),
        );
    }
}
