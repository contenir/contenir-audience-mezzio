<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Tests\TestAsset\Database;

use Contenir\Audience\Storage\Tables;
use PDO;
use PDOException;
use PhpDb\Adapter\Adapter;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Metadata\MetadataInterface;
use PhpDb\Migration\MigrationRunner;
use PhpDb\Migration\MismatchStrategy;
use PhpDb\Mysql;
use PhpDb\Sqlite;
use PHPUnit\Framework\SkippedWithMessageException;

use function dirname;
use function getenv;
use function is_string;
use function sprintf;

/**
 * The site's database the integration tests run against: SQLite in memory by default, or MySQL
 * when CONTENIR_TEST_DB_PLATFORM is "mysql" (connection settings in CONTENIR_TEST_DB_HOST, _PORT,
 * _NAME, _USER and _PASSWORD). The tables are the Campaign Monitor add-on's, as the CMS would
 * have made them.
 */
final class TestDatabase
{
    public const string PREFIX = 'ext_campaign_monitor_';

    /**
     * Every table the test migrations create, plus the runner's own.
     *
     * @return list<string>
     */
    public static function allTables(): array
    {
        $tables = self::tables();

        return [$tables->outbox(), $tables->subject(), $tables->suppression(), $tables->consent(), 'migrations'];
    }

    public static function connect(): AdapterInterface
    {
        return self::env('PLATFORM', 'sqlite') === 'mysql' ? self::mysql() : self::sqlite();
    }

    public static function drop(AdapterInterface $adapter): void
    {
        foreach (self::allTables() as $table) {
            $adapter->query("DROP TABLE IF EXISTS {$table}", Adapter::QUERY_MODE_EXECUTE);
        }
    }

    /**
     * @mago-expect lint:no-literal-namespace-string The runner takes the namespace its migrations are in, not a class.
     */
    public static function migrations(AdapterInterface $adapter): MigrationRunner
    {
        return new MigrationRunner(
            $adapter,
            dirname(__DIR__) . '/Schema',
            'Contenir\\Audience\\Mezzio\\Tests\\TestAsset\\Schema',
            MismatchStrategy::Report,
            self::metadata($adapter),
        );
    }

    public static function tables(): Tables
    {
        return new Tables(self::PREFIX);
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv("CONTENIR_TEST_DB_{$name}");

        return is_string($value) && '' !== $value ? $value : $default;
    }

    private static function metadata(AdapterInterface $adapter): MetadataInterface
    {
        return $adapter->getPlatform()->getName() === 'MySQL'
            ? new Mysql\Metadata\Source($adapter)
            : new Sqlite\Metadata\Source($adapter);
    }

    private static function mysql(): AdapterInterface
    {
        try {
            $pdo = new PDO(
                sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    self::env('HOST', '127.0.0.1'),
                    self::env('PORT', '3306'),
                    self::env('NAME', 'contenir_audience_mezzio_test'),
                ),
                self::env('USER', 'contenir'),
                self::env('PASSWORD', 'contenir'),
            );
        } catch (PDOException $e) {
            throw new SkippedWithMessageException("MySQL is not reachable: {$e->getMessage()}");
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $driver = new Mysql\Pdo\Driver(new Mysql\Pdo\Connection($pdo));

        return new Adapter($driver, new Mysql\AdapterPlatform($driver));
    }

    private static function sqlite(): AdapterInterface
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $driver = new Sqlite\Pdo\Driver(connection: new Sqlite\Pdo\Connection($pdo));

        return new Adapter($driver, new Sqlite\AdapterPlatform($driver));
    }
}
