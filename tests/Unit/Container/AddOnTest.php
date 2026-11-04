<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Tests\Unit\Container;

use Contenir\Audience\Email;
use Contenir\Audience\EmailHasher;
use Contenir\Audience\Mezzio\Container\AddOn;
use Contenir\Audience\Mezzio\Container\InvalidConfiguration;
use Contenir\Audience\Mezzio\Tests\TestAsset\Factory\SiteConfig;
use Contenir\Audience\Testing\InMemoryStores;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class AddOnTest extends TestCase
{
    /**
     * @return array<string, array{array<array-key, mixed>, string}>
     */
    public static function missingBlocks(): array
    {
        return [
            'no extensions'         => [[], 'The application has no extensions.campaign-monitor configuration'],
            'extensions not a list' => [
                ['extensions' => 'x'],
                'The application has no extensions.campaign-monitor configuration',
            ],
            'no block of its own'   => [
                ['extensions' => ['mailchimp' => []]],
                'The application has no extensions.campaign-monitor configuration',
            ],
            'a block not a list'    => [
                ['extensions' => ['campaign-monitor' => 'x']],
                'The application has no extensions.campaign-monitor configuration',
            ],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function prefixes(): array
    {
        return [
            'a single word'            => ['mailchimp', 'ext_mailchimp_'],
            'a handle with a dash'     => ['campaign-monitor', 'ext_campaign_monitor_'],
            'a handle with two dashes' => ['a-b-c', 'ext_a_b_c_'],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusablePeppers(): array
    {
        return [
            'none'      => [null],
            'not text'  => [123],
            'too short' => ['31 bytes, one short of the min.'],
        ];
    }

    #[Test]
    public function hashesWithItsPepper(): void
    {
        $email = Email::fromString('ada@example.com');

        static::assertSame(
            (new EmailHasher(InMemoryStores::PEPPER))->email($email),
            AddOn::named(SiteConfig::HANDLE)->hasher(SiteConfig::make())->email($email),
        );
    }

    #[Test]
    #[DataProvider('prefixes')]
    public function keepsItsTablesUnderTheExtensionApisPrefix(string $handle, string $prefix): void
    {
        static::assertSame([$handle, $prefix], [AddOn::named($handle)->handle, AddOn::named($handle)->tables->prefix]);
    }

    #[Test]
    public function readsItsBindingsFromItsOwnBlock(): void
    {
        $bindings = AddOn::named(SiteConfig::HANDLE)->bindings(SiteConfig::make());

        static::assertSame(
            [['newsletter', 'a1b2c3', SiteConfig::WORDING]],
            array_map(
                static fn($binding): array => [$binding->name, $binding->list, $binding->consent],
                $bindings->all(),
            ),
        );
    }

    #[Test]
    public function refusesAHandleNoAddOnCouldHave(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage(
            'contenir_audience.handle is not an add-on\'s handle: An audience table prefix is lowercase letters',
        );

        AddOn::named('Campaign Monitor');
    }

    #[Test]
    #[DataProvider('unusablePeppers')]
    public function refusesAPepperTheHasherCannotUse(mixed $pepper): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage(
            "extensions.campaign-monitor.pepper must be the add-on's pepper, at least 32 bytes, the same as the CMS uses",
        );

        AddOn::named(SiteConfig::HANDLE)->hasher(['extensions' => ['campaign-monitor' => ['pepper' => $pepper]]]);
    }

    #[Test]
    public function refusesBindingsThatAreNotAList(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage("extensions.campaign-monitor.bindings must list the add-on's bindings");

        AddOn::named(SiteConfig::HANDLE)->bindings(['extensions' => ['campaign-monitor' => ['bindings' => 'x']]]);
    }

    #[Test]
    #[DataProvider('missingBlocks')]
    public function refusesToReadBindingsWithoutItsBlock(array $config, string $message): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage($message);

        AddOn::named(SiteConfig::HANDLE)->bindings($config);
    }

    #[Test]
    #[DataProvider('missingBlocks')]
    public function refusesToReadThePepperWithoutItsBlock(array $config, string $message): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage($message);

        AddOn::named(SiteConfig::HANDLE)->hasher($config);
    }
}
