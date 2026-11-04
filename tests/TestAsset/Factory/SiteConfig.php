<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Tests\TestAsset\Factory;

use Contenir\Audience\Testing\InMemoryStores;

use function array_replace_recursive;

/**
 * A site's configuration as Studio Esteta's would be on the Campaign Monitor add-on: one
 * "newsletter" binding, sending the name too, with the wording its form shows.
 */
final class SiteConfig
{
    public const string HANDLE = 'campaign-monitor';

    public const string WORDING = 'Signing up to the newsletter';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function bindings(): array
    {
        return [
            'newsletter' => [
                'list'    => 'a1b2c3',
                'fields'  => ['Name' => 'name'],
                'consent' => self::WORDING,
                'options' => ['consent_to_track' => 'Yes'],
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $overrides Laid over the configuration, key by key
     *
     * @return array<array-key, mixed>
     */
    public static function make(array $overrides = []): array
    {
        return array_replace_recursive(
            [
                'contenir_audience' => ['handle' => self::HANDLE, 'adapter' => 'db', 'clock' => 'clock'],
                'extensions'        => [
                    self::HANDLE => ['bindings' => self::bindings(), 'pepper' => InMemoryStores::PEPPER],
                ],
            ],
            $overrides,
        );
    }
}
