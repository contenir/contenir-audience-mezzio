<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Tests\Unit\Container;

use Contenir\Audience\Email;
use Contenir\Audience\EmailHasher;
use Contenir\Audience\Exception\InvalidBinding;
use Contenir\Audience\Mezzio\Container\AudienceOptions;
use Contenir\Audience\Mezzio\Container\InvalidConfiguration;
use Contenir\Audience\Mezzio\Tests\TestAsset\Factory\SiteConfig;
use Contenir\Audience\Testing\InMemoryStores;
use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AudienceOptionsTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function missingHandles(): array
    {
        return [
            'none'     => [null],
            'blank'    => [''],
            'not text' => [7],
        ];
    }

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function missingOptions(): array
    {
        return [
            'none'       => [[]],
            'not a list' => [['contenir_audience' => 'campaign-monitor']],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unnamedServices(): array
    {
        return [
            'none'     => [null],
            'blank'    => [''],
            'not text' => [false],
        ];
    }

    #[Test]
    #[DataProvider('unnamedServices')]
    public function defaultsToTheAdapterServiceAndTheSystemsClock(mixed $name): void
    {
        $options = AudienceOptions::fromAppConfig(SiteConfig::make([
            'contenir_audience' => ['adapter' => $name, 'clock' => $name],
        ]));

        static::assertSame([AdapterInterface::class, null], [$options->adapter, $options->clock]);
    }

    #[Test]
    public function readsTheAddOnsBindingsTablesAndPepper(): void
    {
        $options = AudienceOptions::fromAppConfig(SiteConfig::make());
        $email   = Email::fromString('ada@example.com');

        static::assertSame(
            ['a1b2c3', 'ext_campaign_monitor_', (new EmailHasher(InMemoryStores::PEPPER))->email($email)],
            [
                $options->bindings->get('newsletter')->list,
                $options->tables->prefix,
                $options->hasher->email($email),
            ],
        );
    }

    #[Test]
    public function readsTheServicesItIsGiven(): void
    {
        $options = AudienceOptions::fromAppConfig(SiteConfig::make());

        static::assertSame(['db', 'clock'], [$options->adapter, $options->clock]);
    }

    #[Test]
    public function refusesABindingTheLibraryCannotRead(): void
    {
        $this->expectException(InvalidBinding::class);
        $this->expectExceptionMessage('The audience binding "newsletter" has unknown keys: lists');

        AudienceOptions::fromAppConfig(SiteConfig::make([
            'extensions' => ['campaign-monitor' => ['bindings' => ['newsletter' => ['lists' => 'x']]]],
        ]));
    }

    #[Test]
    #[DataProvider('missingOptions')]
    public function refusesAnApplicationWithoutItsOptions(array $config): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('The application has no contenir_audience configuration');

        AudienceOptions::fromAppConfig($config);
    }

    #[Test]
    #[DataProvider('missingHandles')]
    public function refusesOptionsThatNameNoAddOn(mixed $handle): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('contenir_audience.handle must name the add-on the site signs people up through');

        AudienceOptions::fromAppConfig(SiteConfig::make(['contenir_audience' => ['handle' => $handle]]));
    }
}
