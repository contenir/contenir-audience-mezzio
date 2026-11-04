# contenir/contenir-audience-mezzio

[![Continuous Integration](https://github.com/contenir/contenir-audience-mezzio/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/contenir-audience-mezzio/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/contenir-audience-mezzio/graph/badge.svg)](https://codecov.io/gh/contenir/contenir-audience-mezzio)

The site half of [contenir/contenir-audience](https://github.com/contenir/contenir-audience) for a Mezzio site. A
sign-up handler hands its form to `Subscriptions`, which writes it to the newsletter add-on's outbox in the site's
own database. The CMS's queue worker sends it to Campaign Monitor or Mailchimp later, so a slow or failing service
never reaches the visitor: the only thing that can fail on the page is writing that one row.

- **One binding definition.** The site reads the add-on's bindings from `extensions.<handle>.bindings`, the key the
  CMS add-on reads, so the site and the CMS share one definition of which list a form feeds and which fields go
  where.
- **Consent goes with the sign-up.** Each message carries the wording the visitor agreed to, the time and their
  address. The worker records it in the add-on's consent ledger once the person is on the list, and applies the
  library's consent policy and suppression as it sends.
- **Nobody who complained or was erased is queued again.** Their sign-up is withheld before any row is written, so
  their address never sits in the outbox. Someone who unsubscribed or bounced is queued, and the form's consent
  counts as fresh.
- **The visitor is always thanked.** `subscribe()` says whether it queued or withheld a sign-up, but the page
  should say the same either way, or the form would tell anyone which addresses once complained.

There's no laminas-mvc variant yet: one follows when a Laminas site needs it.

## Requirements

- PHP 8.3, 8.4 or 8.5
- php-db/phpdb 0.6, with the site's database as a php-db adapter service
- The newsletter add-on enabled in the CMS, which creates its tables in the site's database

## Install

```bash
composer require contenir/contenir-audience-mezzio
```

Neither this package nor contenir/contenir-audience is on Packagist yet, so the site's `composer.json` lists both
repositories:

```json
"repositories": [
    {"type": "vcs", "url": "https://github.com/contenir/contenir-audience"},
    {"type": "vcs", "url": "https://github.com/contenir/contenir-audience-mezzio"}
]
```

php-db 0.6 has no stable release yet, so the site also needs `"minimum-stability": "dev"` with
`"prefer-stable": true`. With laminas-component-installer, the `ConfigProvider` is added for you; otherwise add
`Contenir\Audience\Mezzio\ConfigProvider::class` to `config/config.php`.

## Configure

The CMS add-on keeps its bindings and pepper in `config/autoload/extensions/<handle>.global.php`, with
`<handle>.local.php` laid over it. The site reads the same two files, so there is one definition. Mezzio's default
glob doesn't reach that folder, so add a provider for it after the site's own `autoload` one in
`config/config.php`:

```php
new PhpFileProvider(realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
new PhpFileProvider(realpath(__DIR__) . '/autoload/extensions/{{,*.}global,{,*.}local}.php'),
```

The add-on's file holds its bindings:

```php
// config/autoload/extensions/campaign-monitor.global.php
return ['extensions' => ['campaign-monitor' => ['bindings' => [
    'newsletter' => [
        'list'    => 'a1b2c3',
        'fields'  => ['Name' => 'name'],
        'consent' => 'Signing up to the newsletter',
        'options' => ['consent_to_track' => 'Yes'],
    ],
]]]];
```

```php
// config/autoload/extensions/campaign-monitor.local.php, not committed
return ['extensions' => ['campaign-monitor' => ['pepper' => '…the add-on\'s pepper…']]];
```

And the site names the add-on it signs people up through:

```php
// config/autoload/audience.global.php
return [
    'contenir_audience' => [
        'handle'  => 'campaign-monitor',             // the add-on: its tables are ext_campaign_monitor_*
        'adapter' => PhpDb\Adapter\Adapter::class,   // the site's database; default AdapterInterface::class
        'clock'   => null,                           // a PSR-20 clock service; default the system's time
    ],
];
```

The pepper must be the one the CMS add-on uses, at least 32 bytes. Everything kept after a message is sent is keyed
by a peppered hash of the email, so with another pepper the site's hashes would never match the CMS's. That would
let a sign-up through for someone suppressed for good.

A binding's keys are the library's (see `BindingReader`). An unknown key is refused, so a typo can't pass unnoticed.

## Use

Call `subscribe()` from the site's own handler once its form is valid:

```php
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Mezzio\Subscriptions;

final class SubscribeHandler implements RequestHandlerInterface
{
    public function __construct(private Subscriptions $subscriptions, /* … */) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // … validate the form …
        try {
            $this->subscriptions->subscribe(
                'newsletter',
                $data['email'],
                ['name' => $data['name'] ?? ''],
                ip: $request->getServerParams()['REMOTE_ADDR'] ?? null,
            );
        } catch (StorageException) {
            // the outbox row couldn't be written: the one failure to show the visitor
        }

        // … render "thanks" …
    }
}
```

- `$values` holds the form's other fields. The binding's `fields` decide which are sent and how each is changed on
  the way. The email given replaces any field of the same name.
- `wording` is the text the form showed beside its button. If it's left out, the binding's `consent` is used. A
  sign-up with neither is refused, as the policy would treat it as an update.
- `subscribe()` throws `UnknownBinding` when the site has no binding by that name, and `InvalidBinding` when no
  wording can be found. Both are mistakes in the site's set-up. It throws `InvalidEmail` for an address the form
  should already have refused, and `StorageException` when the row can't be written.

## Development

```bash
composer check
```

runs formatting, lint and static analysis (Mago), the unit and integration suites, and mutation testing (Infection).
The integration suite runs on SQLite, then on MySQL when `CONTENIR_TEST_DB_*` names one.
