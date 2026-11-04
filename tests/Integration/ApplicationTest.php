<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Tests\Integration;

use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\Email;
use Contenir\Audience\EmailHasher;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Mezzio\ConfigProvider;
use Contenir\Audience\Mezzio\SignUp;
use Contenir\Audience\Mezzio\Subscriptions;
use Contenir\Audience\Mezzio\Tests\TestAsset\Database\TestDatabase;
use Contenir\Audience\Mezzio\Tests\TestAsset\Factory\SiteConfig;
use Contenir\Audience\Mezzio\Tests\Trait\TestDatabaseTrait;
use Contenir\Audience\Outbox\Action;
use Contenir\Audience\Storage\PhpDbConsentLedger;
use Contenir\Audience\Storage\PhpDbOutboxStore;
use Contenir\Audience\Storage\PhpDbSubjectStore;
use Contenir\Audience\Storage\PhpDbSuppressionStore;
use Contenir\Audience\Stores;
use Contenir\Audience\Suppression\SuppressionReason;
use Contenir\Audience\Testing\InMemoryService;
use Contenir\Audience\Testing\InMemoryStores;
use Contenir\Audience\Testing\MutableClock;
use Contenir\Audience\Worker\OutboxWorker;
use Laminas\ServiceManager\ServiceManager;
use Override;
use PhpDb\Adapter\Adapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function array_map;

/**
 * The package in a real service container, writing to the add-on's tables in the site's
 * database, and the CMS's worker sending what it wrote.
 */
#[Group('integration')]
final class ApplicationTest extends TestCase
{
    use TestDatabaseTrait;

    private const string EMAIL = 'ada@example.com';

    private MutableClock $clock;

    private static function hash(): string
    {
        return (new EmailHasher(InMemoryStores::PEPPER))->email(Email::fromString(self::EMAIL));
    }

    #[Test]
    public function failsThePageOnlyWhenTheOutboxCannotBeWritten(): void
    {
        $this->adapter->query('DROP TABLE ' . TestDatabase::tables()->outbox(), Adapter::QUERY_MODE_EXECUTE);

        $this->expectException(StorageException::class);

        $this->subscriptions()->subscribe('newsletter', self::EMAIL);
    }

    #[Test]
    public function theCmsWorkerSendsItAndRecordsTheConsent(): void
    {
        $this->subscriptions()->subscribe('newsletter', self::EMAIL, ['name' => 'Ada']);
        $service = new InMemoryService();

        $report = $this->worker()->drain($service);

        static::assertSame(
            [1, [[SiteConfig::WORDING, 'form', self::hash()]]],
            [$report->sent, $this->ledger()],
        );
    }

    #[Test]
    public function timesSignUpsByTheSystemsClockUnlessGivenOne(): void
    {
        $subscriptions = $this->subscriptions(['contenir_audience' => ['clock' => null]]);

        static::assertSame(SignUp::Queued, $subscriptions->subscribe('newsletter', self::EMAIL));
    }

    #[Test]
    public function withholdsSomeoneSuppressedForAComplaint(): void
    {
        $this->stores()->suppression->suppress(
            'newsletter',
            self::hash(),
            SuppressionReason::Complaint,
            $this->clock->now(),
        );

        static::assertSame(
            [SignUp::Withheld, []],
            [
                $this->subscriptions()->subscribe('newsletter', self::EMAIL),
                $this->stores()->outbox->due(10, $this->clock->now()),
            ],
        );
    }

    #[Test]
    public function writesASignUpToTheAddOnsOutbox(): void
    {
        $this->subscriptions()->subscribe('newsletter', self::EMAIL, ['name' => 'Ada'], ip: '203.0.113.9');

        $row = $this->stores()->outbox->due(10, $this->clock->now())[0] ?? null;
        static::assertEquals(
            ['newsletter', Action::Subscribe, self::EMAIL, ['Name' => 'Ada'], 'form', '203.0.113.9'],
            [
                $row?->binding,
                $row?->action,
                $row?->email?->value,
                $row?->values,
                $row?->consent?->source->value,
                $row?->consent?->ip,
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->clock = new MutableClock();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function container(array $config): ServiceManager
    {
        return new ServiceManager([
            ...(new ConfigProvider())()['dependencies'],
            'services' => ['config' => $config, 'db' => $this->adapter, 'clock' => $this->clock],
        ]);
    }

    /**
     * Each consent the ledger holds: its wording, source and email hash.
     *
     * @return list<array{mixed, mixed, mixed}>
     */
    private function ledger(): array
    {
        $result = $this->adapter->query(
            'SELECT wording, source, email_hash FROM ' . TestDatabase::tables()->consent(),
            Adapter::QUERY_MODE_EXECUTE,
        );
        $rows = [];
        foreach ($result as $row) {
            $rows[] = (array) $row;
        }

        return array_map(static fn(array $row): array => [$row['wording'], $row['source'], $row['email_hash']], $rows);
    }

    /**
     * The add-on's stores as the CMS builds them, on the same database.
     */
    private function stores(): Stores
    {
        $tables = TestDatabase::tables();

        return new Stores(
            new PhpDbOutboxStore($this->adapter, $tables),
            new PhpDbSubjectStore($this->adapter, $tables),
            new PhpDbSuppressionStore($this->adapter, $tables),
            new PhpDbConsentLedger($this->adapter, $tables),
            new EmailHasher(InMemoryStores::PEPPER),
        );
    }

    /**
     * @param array<array-key, mixed> $overrides
     */
    private function subscriptions(array $overrides = []): Subscriptions
    {
        $subscriptions = $this->container(SiteConfig::make($overrides))->get(Subscriptions::class);
        static::assertInstanceOf(Subscriptions::class, $subscriptions);

        return $subscriptions;
    }

    /**
     * The CMS's worker, reading the bindings from the same configuration as the site.
     */
    private function worker(): OutboxWorker
    {
        return new OutboxWorker(
            Bindings::fromConfig(SiteConfig::bindings()),
            $this->stores(),
            $this->clock,
            new NullLogger(),
        );
    }
}
