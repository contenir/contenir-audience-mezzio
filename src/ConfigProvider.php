<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio;

use Contenir\Audience\Mezzio\Container\SubscriptionsFactory;

/**
 * The Subscriptions service, for a Mezzio site's sign-up handlers.
 *
 * @api
 */
final class ConfigProvider
{
    /**
     * @return array{factories: array<class-string, class-string>}
     */
    public function getDependencies(): array
    {
        return [
            'factories' => [
                Subscriptions::class => SubscriptionsFactory::class,
            ],
        ];
    }

    /**
     * @return array{dependencies: array{factories: array<class-string, class-string>}}
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }
}
