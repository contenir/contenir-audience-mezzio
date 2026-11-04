<?php

declare(strict_types=1);

namespace Contenir\Audience\Mezzio;

/**
 * What became of a sign-up. Either way the visitor is thanked: telling them they can't be
 * subscribed would tell anyone who types an address that its owner once complained.
 *
 * @api
 */
enum SignUp
{
    /** Written to the outbox, for the CMS's worker to send */
    case Queued;

    /** Not written, as the person complained or was erased and no consent brings them back */
    case Withheld;
}
