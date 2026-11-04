<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Tests\TestAsset\Schema;

use Contenir\Audience\Mezzio\Tests\TestAsset\Database\TestDatabase;
use Contenir\Audience\Schema\AbstractCreateOutbox;
use Override;

/**
 * The Campaign Monitor add-on's outbox table, made the way an add-on subclasses the library's migration.
 *
 * @mago-expect analysis:missing-constructor AbstractMigration sets its adapter and inspector in up() and preview().
 */
final class Version20261105000100CreateOutbox extends AbstractCreateOutbox
{
    #[Override]
    public function getVersion(): string
    {
        return '20261105000100';
    }

    #[Override]
    protected function prefix(): string
    {
        return TestDatabase::PREFIX;
    }
}
