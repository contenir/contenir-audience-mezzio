<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio\Container;

use InvalidArgumentException;

/**
 * The application's audience configuration can't be used as it stands.
 *
 * @api
 */
final class InvalidConfiguration extends InvalidArgumentException {}
