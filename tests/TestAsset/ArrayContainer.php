<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Tests\TestAsset;

use LogicException;
use Override;
use Psr\Container\ContainerInterface;

use function array_key_exists;

/**
 * A PSR-11 container over an array of services.
 */
final readonly class ArrayContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $services
     */
    public function __construct(
        private array $services,
    ) {}

    #[Override]
    public function get(string $id): mixed
    {
        if (! array_key_exists($id, $this->services)) {
            throw new LogicException("No service {$id}");
        }

        return $this->services[$id];
    }

    #[Override]
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
