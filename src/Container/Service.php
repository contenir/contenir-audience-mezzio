<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * A service from the container, checked to be the type its user needs.
 *
 * @internal
 */
final class Service
{
    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     *
     * @throws ContainerExceptionInterface
     * @throws InvalidConfiguration When the service is something else.
     *
     * @mago-expect analysis:mixed-assignment Container services are untyped; the type is checked here.
     */
    public static function get(ContainerInterface $container, string $type, string $name): object
    {
        $service = $container->get($name);

        return $service instanceof $type
            ? $service
            : throw new InvalidConfiguration("The service {$name} is not a {$type}");
    }
}
