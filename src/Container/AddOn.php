<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Container;

use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\EmailHasher;
use Contenir\Audience\Exception\InvalidBinding;
use Contenir\Audience\Storage\Tables;
use InvalidArgumentException;

use function is_array;
use function is_string;
use function str_replace;

/**
 * The CMS add-on the site signs people up through: its tables, and its bindings and pepper read
 * from extensions.<handle>, where the add-on reads them too.
 *
 * @internal
 */
final readonly class AddOn
{
    private function __construct(
        public string $handle,
        public Tables $tables,
    ) {}

    /**
     * The add-on with $handle, its tables under the prefix the extension API gives every add-on:
     * ext_<handle>_, with each dash in the handle written as an underscore.
     *
     * @throws InvalidConfiguration When $handle can't be an add-on's.
     */
    public static function named(string $handle): self
    {
        try {
            return new self($handle, new Tables('ext_' . str_replace('-', replace: '_', subject: $handle) . '_'));
        } catch (InvalidArgumentException $e) {
            throw new InvalidConfiguration(
                AudienceOptions::KEY . ".handle is not an add-on's handle: {$e->getMessage()}",
            );
        }
    }

    /**
     * @param array<array-key, mixed> $config The application's merged configuration
     *
     * @throws InvalidConfiguration
     * @throws InvalidBinding
     *
     * @mago-expect analysis:mixed-assignment The application's config is untyped; the bindings are checked as a list.
     */
    public function bindings(array $config): Bindings
    {
        $bindings = $this->block($config)['bindings'] ?? null;
        if (! is_array($bindings)) {
            throw new InvalidConfiguration("extensions.{$this->handle}.bindings must list the add-on's bindings");
        }

        return Bindings::fromConfig($bindings);
    }

    /**
     * The hasher keyed by the add-on's pepper, which must be the one the CMS uses, or the site's
     * hashes would never match the CMS's.
     *
     * @param array<array-key, mixed> $config The application's merged configuration
     *
     * @throws InvalidConfiguration
     *
     * @mago-expect analysis:mixed-assignment The application's config is untyped; the pepper is checked as text.
     */
    public function hasher(array $config): EmailHasher
    {
        $pepper = $this->block($config)['pepper'] ?? null;
        try {
            return new EmailHasher(is_string($pepper) ? $pepper : '');
        } catch (InvalidArgumentException) {
            throw new InvalidConfiguration(
                "extensions.{$this->handle}.pepper must be the add-on's pepper, at least "
                    . EmailHasher::MIN_PEPPER_BYTES
                    . ' bytes, the same as the CMS uses',
            );
        }
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidConfiguration
     *
     * @mago-expect analysis:mixed-assignment The application's config is untyped; each level is checked here.
     */
    private function block(array $config): array
    {
        $extensions = $config['extensions'] ?? null;
        $block      = is_array($extensions) ? $extensions[$this->handle] ?? null : null;

        return is_array($block)
            ? $block
            : throw new InvalidConfiguration("The application has no extensions.{$this->handle} configuration");
    }
}
