<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Tests\Unit;

use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\Consent;
use Contenir\Audience\ConsentSource;
use Contenir\Audience\Email;
use Contenir\Audience\Exception\InvalidBinding;
use Contenir\Audience\Exception\InvalidEmail;
use Contenir\Audience\Exception\UnknownBinding;
use Contenir\Audience\Mezzio\SignUp;
use Contenir\Audience\Mezzio\Subscriptions;
use Contenir\Audience\Mezzio\Tests\TestAsset\Factory\SiteConfig;
use Contenir\Audience\Outbox\Action;
use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Audience\Suppression\SuppressionReason;
use Contenir\Audience\Testing\InMemoryStores;
use Contenir\Audience\Testing\MutableClock;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_values;

#[Group('unit')]
final class SubscriptionsTest extends TestCase
{
    private const string EMAIL = 'ada@example.com';

    private MutableClock $clock;

    private InMemoryStores $kit;

    /**
     * @return array<string, array{string|null}>
     */
    public static function missingWording(): array
    {
        return [
            'none'  => [null],
            'blank' => ['   '],
        ];
    }

    /**
     * @return array<string, array{SuppressionReason}>
     */
    public static function permanentSuppressions(): array
    {
        return [
            'a complaint' => [SuppressionReason::Complaint],
            'an erasure'  => [SuppressionReason::Forgotten],
        ];
    }

    /**
     * @return array<string, array{SuppressionReason}>
     */
    public static function suppressionsConsentLifts(): array
    {
        return [
            'an unsubscribe' => [SuppressionReason::Unsubscribed],
            'a bounce'       => [SuppressionReason::Cleaned],
        ];
    }

    #[Test]
    public function checksSuppressionByTheNormalisedAddress(): void
    {
        $this->suppress(self::EMAIL, SuppressionReason::Complaint);

        static::assertSame(SignUp::Withheld, $this->subscriptions()->subscribe('newsletter', ' ADA@example.com'));
    }

    #[Test]
    public function checksSuppressionForTheBindingSignedUpTo(): void
    {
        $this->kit->suppression->suppress(
            'members',
            $this->hash(self::EMAIL),
            SuppressionReason::Complaint,
            $this->clock->now(),
        );

        static::assertSame(SignUp::Queued, $this->subscriptions()->subscribe('newsletter', self::EMAIL));
    }

    #[Test]
    public function keepsTheAddressItWasGivenOverOneAmongTheValues(): void
    {
        $this->subscriptions($this->bindings(['fields' => ['EmailAddress' => 'email']]))->subscribe(
            'newsletter',
            ' Ada@Example.com ',
            ['email' => 'someone-else@example.com'],
        );

        static::assertSame(['EmailAddress' => self::EMAIL], $this->onlyRow()->values);
    }

    #[Test]
    public function leavesTheLedgerToTheWorker(): void
    {
        $this->subscriptions()->subscribe('newsletter', self::EMAIL);

        static::assertSame([], $this->kit->consents->records);
    }

    #[Test]
    public function putsTheAddressInTheFieldTheBindingNamesForIt(): void
    {
        $this->subscriptions($this->bindings(['email' => 'address', 'fields' => ['EmailAddress' => 'address']]))
            ->subscribe('newsletter', self::EMAIL);

        static::assertSame(['EmailAddress' => self::EMAIL], $this->onlyRow()->values);
    }

    #[Test]
    #[DataProvider('suppressionsConsentLifts')]
    public function queuesSomeoneFreshConsentBringsBack(SuppressionReason $reason): void
    {
        $this->suppress(self::EMAIL, $reason);

        static::assertSame(SignUp::Queued, $this->subscriptions()->subscribe('newsletter', self::EMAIL));
    }

    #[Test]
    public function queuesTheSignUpInTheOutbox(): void
    {
        static::assertSame(SignUp::Queued, $this->subscriptions()->subscribe('newsletter', self::EMAIL));
    }

    #[Test]
    public function recordsTheWordingTheFormShowedOverTheBindings(): void
    {
        $this->subscriptions()->subscribe('newsletter', self::EMAIL, wording: '  Yes, send me the newsletter ');

        static::assertSame('Yes, send me the newsletter', $this->onlyRow()->consent?->wording);
    }

    #[Test]
    public function refusesABindingTheSiteDoesNotHave(): void
    {
        $this->expectException(UnknownBinding::class);
        $this->expectExceptionMessage('There is no audience binding "members"');

        $this->subscriptions()->subscribe('members', self::EMAIL);
    }

    #[Test]
    public function refusesAMissingWordingBeforeLookingAtTheAddress(): void
    {
        $this->expectException(InvalidBinding::class);

        $this->subscriptions($this->bindings(['consent' => null]))->subscribe('newsletter', 'not an address');
    }

    #[Test]
    #[DataProvider('missingWording')]
    public function refusesASignUpWithNoWordingToRecord(?string $wording): void
    {
        $this->expectException(InvalidBinding::class);
        $this->expectExceptionMessage(
            'The audience binding "newsletter" has no consent wording: give it a "consent", or pass the wording the form showed',
        );

        $this->subscriptions($this->bindings(['consent' => null]))->subscribe('newsletter', self::EMAIL, [], $wording);
    }

    #[Test]
    public function refusesBlankWordingEvenWhenTheBindingHasSome(): void
    {
        $this->expectException(InvalidBinding::class);

        $this->subscriptions()->subscribe('newsletter', self::EMAIL, wording: '');
    }

    #[Test]
    public function refusesSomethingThatIsNotAnAddress(): void
    {
        $this->expectException(InvalidEmail::class);
        $this->expectExceptionMessage('"not an address" is not an email address');

        $this->subscriptions()->subscribe('newsletter', 'not an address');
    }

    #[Test]
    public function sendsTheFieldsTheBindingMaps(): void
    {
        $this->subscriptions()->subscribe('newsletter', self::EMAIL, ['name' => 'Ada Lovelace', 'spam' => 'x']);

        static::assertSame(['Name' => 'Ada Lovelace'], $this->onlyRow()->values);
    }

    #[Test]
    public function timesTheConsentByTheClock(): void
    {
        $this->clock->advance('+90 minutes');
        $this->subscriptions()->subscribe('newsletter', self::EMAIL);

        static::assertEquals(
            [$this->clock->now(), $this->clock->now()],
            [$this->onlyRow()->consent?->at, $this->onlyRow()->createdAt],
        );
    }

    #[Test]
    #[DataProvider('permanentSuppressions')]
    public function withholdsSomeoneNoConsentBringsBack(SuppressionReason $reason): void
    {
        $this->suppress(self::EMAIL, $reason);

        static::assertSame(
            [SignUp::Withheld, []],
            [$this->subscriptions()->subscribe('newsletter', self::EMAIL), $this->kit->outbox->rows],
        );
    }

    #[Test]
    public function writesAMessageToSubscribeWithTheFormsConsent(): void
    {
        $this->subscriptions()->subscribe('newsletter', self::EMAIL, ip: '203.0.113.9');

        $row = $this->onlyRow();
        static::assertEquals(
            [
                'newsletter',
                Action::Subscribe,
                Email::fromString(self::EMAIL),
                $this->hash(self::EMAIL),
                new Consent(
                    SiteConfig::WORDING,
                    ConsentSource::Form,
                    $this->clock->now(),
                    '203.0.113.9',
                ),
            ],
            [$row->binding, $row->action, $row->email, $row->emailHash, $row->consent],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MutableClock();
        $this->kit   = new InMemoryStores();
    }

    /**
     * The site's bindings, with $changes made to its "newsletter".
     *
     * @param array<string, mixed> $changes
     */
    private function bindings(array $changes = []): Bindings
    {
        $config               = SiteConfig::bindings();
        $config['newsletter'] = [...$config['newsletter'], ...$changes];

        return Bindings::fromConfig($config);
    }

    private function hash(string $email): string
    {
        return $this->kit->hasher->email(Email::fromString($email));
    }

    private function onlyRow(): OutboxRow
    {
        $rows = array_values($this->kit->outbox->rows);
        static::assertCount(1, $rows);

        return $rows[0];
    }

    private function subscriptions(?Bindings $bindings = null): Subscriptions
    {
        return new Subscriptions($bindings ?? $this->bindings(), $this->kit->stores, $this->clock);
    }

    private function suppress(string $email, SuppressionReason $reason): void
    {
        $this->kit->suppression->suppress('newsletter', $this->hash($email), $reason, $this->clock->now());
    }
}
