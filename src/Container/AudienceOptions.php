<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Container;

use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\EmailHasher;
use Contenir\Audience\Exception\InvalidBinding;
use Contenir\Audience\Storage\Tables;
use PhpDb\Adapter\AdapterInterface;

use function is_array;
use function is_string;

/**
 * The application's audience configuration, read once and checked. The site names the add-on
 * whose outbox it writes to, and reads that add-on's bindings and pepper from the same keys the
 * CMS add-on does, so both share one definition:
 *
 * ```php
 * 'contenir_audience' => [
 *     'handle'  => 'campaign-monitor',        // the add-on: its tables are ext_campaign_monitor_*
 *     'adapter' => AdapterInterface::class,   // the site's database, as a php-db adapter service
 *     'clock'   => null,                      // a PSR-20 service, default the system's
 * ],
 * 'extensions' => [
 *     'campaign-monitor' => [
 *         'bindings' => ['newsletter' => ['list' => 'a1b2c3', 'consent' => 'Signing up to the newsletter']],
 *         'pepper'   => '…at least 32 bytes, in a .local.php…',
 *     ],
 * ],
 * ```
 *
 * @internal
 */
final readonly class AudienceOptions
{
    public const string KEY = 'contenir_audience';

    private function __construct(
        public Bindings $bindings,
        public Tables $tables,
        public EmailHasher $hasher,
        public string $adapter,
        public ?string $clock,
    ) {}

    /**
     * @param array<array-key, mixed> $config The application's merged configuration
     *
     * @throws InvalidConfiguration
     * @throws InvalidBinding
     *
     * @mago-expect analysis:mixed-assignment The application's config is untyped; each part is checked here.
     */
    public static function fromAppConfig(array $config): self
    {
        $options = $config[self::KEY] ?? null;
        if (! is_array($options)) {
            throw new InvalidConfiguration('The application has no ' . self::KEY . ' configuration');
        }

        $addOn = AddOn::named(
            self::text($options, 'handle') ?? throw new InvalidConfiguration(
                self::KEY . '.handle must name the add-on the site signs people up through',
            ),
        );

        return new self(
            $addOn->bindings($config),
            $addOn->tables,
            $addOn->hasher($config),
            self::text($options, 'adapter') ?? AdapterInterface::class,
            self::text($options, 'clock'),
        );
    }

    /**
     * @param array<array-key, mixed> $options
     *
     * @mago-expect analysis:mixed-assignment The application's config is untyped; the value is checked here.
     */
    private static function text(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }
}
