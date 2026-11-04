<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Tests\TestAsset\Schema;

use Contenir\Audience\Mezzio\Tests\TestAsset\Database\TestDatabase;
use Contenir\Audience\Schema\AbstractCreateConsent;
use Override;

/**
 * The Campaign Monitor add-on's consent ledger table, made the way an add-on subclasses the library's migration.
 *
 * @mago-expect analysis:missing-constructor AbstractMigration sets its adapter and inspector in up() and preview().
 */
final class Version20261105000400CreateConsent extends AbstractCreateConsent
{
    #[Override]
    public function getVersion(): string
    {
        return '20261105000400';
    }

    #[Override]
    protected function prefix(): string
    {
        return TestDatabase::PREFIX;
    }
}
