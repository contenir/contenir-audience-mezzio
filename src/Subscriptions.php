<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio;

use Contenir\Audience\Audience;
use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\Consent;
use Contenir\Audience\ConsentSource;
use Contenir\Audience\Email;
use Contenir\Audience\Exception\InvalidBinding;
use Contenir\Audience\Exception\InvalidEmail;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Exception\UnknownBinding;
use Contenir\Audience\Stores;
use Psr\Clock\ClockInterface;

use function trim;

/**
 * The site's sign-ups, as a sign-up handler hands them over. Each becomes a message in the
 * add-on's outbox in the site's database, carrying the consent the form asked for; the CMS's
 * worker sends it, records the consent in the ledger once the person is on the list, and applies
 * the consent policy and suppression again as it does. Nothing here calls the service, so the
 * only failure a visitor can meet is the outbox row not being written.
 *
 * @api
 */
final readonly class Subscriptions
{
    private Audience $audience;

    public function __construct(
        private Bindings $bindings,
        private Stores $stores,
        private ClockInterface $clock,
    ) {
        $this->audience = new Audience($bindings, $stores, $clock);
    }

    /**
     * Signs $email up to the binding's list, agreeing to $wording: the words the form showed, or
     * when null the binding's own "consent" wording.
     *
     * @param array<string, mixed> $values The form's other fields, which the binding maps
     * @param string|null          $ip     The address the form was sent from, for the ledger
     *
     * @throws UnknownBinding When the site has no binding by that name.
     * @throws InvalidBinding When neither the form nor the binding gives the wording agreed to.
     * @throws InvalidEmail   When $email is not an email address: the form should refuse it first.
     * @throws StorageException When the outbox row can't be written.
     */
    public function subscribe(
        string $binding,
        string $email,
        array $values = [],
        ?string $wording = null,
        ?string $ip = null,
    ): SignUp {
        $bound  = $this->bindings->get($binding);
        $agreed = trim($wording ?? (string) $bound->consent);
        if ('' === $agreed) {
            throw InvalidBinding::because(
                $binding,
                'has no consent wording: give it a "consent", or pass the wording the form showed',
            );
        }

        $address = Email::fromString($email);

        $reason = $this->stores->suppression->reason($binding, $this->stores->hasher->email($address));
        if (null !== $reason && ! $reason->yieldsToConsent()) {
            return SignUp::Withheld;
        }

        $this->audience->subscribe(
            $binding,
            [...$values, $bound->emailField => $address->value],
            new Consent($agreed, ConsentSource::Form, $this->clock->now(), $ip),
        );

        return SignUp::Queued;
    }
}
