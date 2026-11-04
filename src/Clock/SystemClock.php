<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Clock;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * The system's own time, for a site with no PSR-20 clock of its own.
 *
 * @internal
 */
final readonly class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
