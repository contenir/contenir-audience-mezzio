<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Container;

use Contenir\Audience\Exception\InvalidBinding;
use Contenir\Audience\Mezzio\Clock\SystemClock;
use Contenir\Audience\Mezzio\Subscriptions;
use Contenir\Audience\Storage\PhpDbConsentLedger;
use Contenir\Audience\Storage\PhpDbOutboxStore;
use Contenir\Audience\Storage\PhpDbSubjectStore;
use Contenir\Audience\Storage\PhpDbSuppressionStore;
use Contenir\Audience\Stores;
use PhpDb\Adapter\AdapterInterface;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function is_array;

/**
 * Builds the site's Subscriptions from the application's configuration (see AudienceOptions):
 * the add-on's bindings, and its stores in the site's database under the add-on's prefix.
 *
 * @api
 */
final class SubscriptionsFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidConfiguration
     * @throws InvalidBinding
     *
     * @mago-expect analysis:mixed-assignment The application's config is untyped; AudienceOptions checks it.
     */
    public function __invoke(ContainerInterface $container): Subscriptions
    {
        $config  = $container->get('config');
        $options = AudienceOptions::fromAppConfig(is_array($config) ? $config : []);
        $adapter = Service::get($container, AdapterInterface::class, $options->adapter);
        $clock   = null === $options->clock
            ? new SystemClock()
            : Service::get($container, ClockInterface::class, $options->clock);

        return new Subscriptions(
            $options->bindings,
            new Stores(
                new PhpDbOutboxStore($adapter, $options->tables),
                new PhpDbSubjectStore($adapter, $options->tables),
                new PhpDbSuppressionStore($adapter, $options->tables),
                new PhpDbConsentLedger($adapter, $options->tables),
                $options->hasher,
            ),
            $clock,
        );
    }
}
