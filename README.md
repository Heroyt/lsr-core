# LSR Core

`lsr/core` provides the Laser framework application runtime: Nette dependency injection, HTTP dispatch, sessions, Latte templating, translations and integration with the LSR routing, request, database, cache and ORM packages. Its namespace is `Lsr\Core`.

## Requirements

- PHP `>=8.4`.
- PHP extensions: `fileinfo`, `gettext`, `simplexml`, `ctype`, `mbstring` and `pdo_sqlite`.
- Nette DI `^3.2.4` (native lazy logger services), Latte `^3.0`, PHP dotenv `^5.6` and Nette PHP Generator `^4.1`.
- Core **0.6 (unreleased)** directly supports PSR-3 `psr/log ^1.0 || ^2.0 || ^3.0` and ORM `^0.3 || ^0.4`; using Core's logger seam does not require ORM 0.4.
- LSR interfaces, logging, routing (`^0.5`), request, DB, serializer, cache and ORM dependencies; see [composer.json](composer.json) for exact constraints and transitive platform requirements.
- An application-owned bootstrap and service configuration, writable temporary/cache and log locations, and database/cache configuration appropriate to the application. This is a framework library, not an application skeleton.

## Installation

```sh
composer require lsr/core
```

## Application integration

The application owns the following pieces of bootstrap configuration:

1. Load Composer's autoloader and define the filesystem/environment constants used by the enabled framework components. In particular, `App::setupDi()` reads `ROOT . 'config/services.php'` and writes the compiled container under `TMP_DIR . 'di/'`; these directory constants need trailing separators. [tests/bootstrap.php](tests/bootstrap.php) shows the wider set of constants used by the package's test environment, not a production bootstrap to copy verbatim.
2. Make the application's `config/services.php` return an array of service configuration filenames. [App::setupDi()](src/App.php) loads these files through Nette's compiler. The repository's [config/services.php](config/services.php) is test-oriented and references application/test files; provide your own list.
3. Register `Lsr\Core\DI\LsrExtension` in Nette's `extensions` section together with the request, routing, serializer, DB and cache integrations needed by your application. The core extension does not replace their configuration.
4. Supply the core extension's required `appDir` and `tempDir` options. Both must refer to existing directories. Optional configuration covers `latte.tempDir`, `translations.defaultLang`, `translations.supportedLanguages`, `translations.domains`, `links.modifiers`, and HTTP exception/after-response handlers. See the authoritative [configuration schema and service definitions](src/DI/LsrExtension.php).

After the application constants and service configuration are in place, the FPM entry point can finish bootstrapping with:

```php
use Lsr\Core\App;
use Lsr\Core\FpmHandler;

App::setupDi();
$handler = App::getServiceByType(FpmHandler::class);
if (!$handler instanceof FpmHandler) {
    throw new RuntimeException('The application must register the LSR core extension.');
}
$handler->run();
```

[`FpmHandler`](src/FpmHandler.php) creates the request, invokes the application, handles dispatch-break responses and configured exception handlers, and finishes the response lifecycle. [`RouteHandler`](src/RouteHandler.php) performs controller/handler dispatch. [`LsrExtension`](src/DI/LsrExtension.php) wires the session, translation, link, menu and Latte services; use it as the integration reference rather than manually reproducing the service graph.

## Application logger selection

**Logger selection is published in `lsr/core 0.5.1`; the PSR-3 contract below is
Core 0.6 (unreleased), not part of that compatibility patch.** Check the installed
version before using either API.

Core owns a dedicated, non-autowired `<extension>.logger` service (`lsr.logger` when the
extension is named `lsr`). With `logger: null` or no option, it lazily creates
`Lsr\Logging\Logger(LOG_DIR, 'app')`, preserving the daily `app-YYYY-MM-DD.log` output.
`LOG_DIR` is resolved at runtime when the logger initializes, not while compiling
the container. A global `@logger` is never selected implicitly.

Select an existing logger with a native Nette service reference:

```neon
services:
    logger: Lsr\Logging\Logger(%logDir%, application)
    coreLogger:
        factory: Lsr\Logging\Logger(%logDir%, core)
        autowired: false

lsr:
    appDir: %appDir%
    tempDir: %tempDir%
    logger: @coreLogger
```

Use `logger: @logger` instead to share the exact application logger instance.
Referencing a separate service keeps its output separate. The package service
does not add another autowiring candidate for the application's global logger.
The extension injects the selected service through `App::setLogger()`, so existing
`lsr.app` class overrides and their constructor arguments stay intact. Native
service alias chains and setups on the selected Core logger retain the exact
shared instance rather than constructing a copy.
As with other Nette service setups, explicitly resetting a service's setup list
also removes this injection and leaves that override responsible for its logger.

Direct `App` construction is unchanged. Without a setter call, `getLogger()` still
creates the default logger only on first access; call `setLogger($logger)` to
select one explicitly, including after the fallback has been used.

### Published 0.5.1 compatibility patch

In 0.5.1, the protected `$logger` property, `App::getLogger(): Lsr\Logging\Logger`
and `App::setLogger(Lsr\Logging\Logger $logger): void` remain concrete, including
the `exception()` and `logDb()` convenience methods. Configured services must be
`Lsr\Logging\Logger` instances (subclasses are supported); ordinary PSR-3-only
loggers are not accepted by that release.

### Core 0.6 migration (unreleased)

Core 0.6 changes the protected property to `Psr\Log\LoggerInterface`, the getter
to `App::getLogger(): Psr\Log\LoggerInterface`, and the setter to
`App::setLogger(Psr\Log\LoggerInterface $logger): void`. The configured service
must implement that interface; a plain PSR logger can be selected by reference
or passed directly to the setter without subclassing or wrapping it. Invalid
references/types still fail container configuration. No constructor changes,
driver aliases, forwarding adapters, buffering or exception interception are
introduced. The default remains the concrete LSR logger and its existing files,
record order, levels, messages, contexts and synchronous failures are unchanged.

Before opting a consumer into 0.6:

1. Remove any redeclaration of the inherited protected `$logger` property, or
   change its type to exactly `LoggerInterface`. Mutable PHP property types are
   invariant: retaining `protected Logger $logger` in an `App` subclass is not
   compatible. Update setter overrides to accept `LoggerInterface` (or a wider
   type), never only the concrete LSR logger. A covariant concrete getter is
   valid only if that subclass actually guarantees a concrete return value even
   when callers inject an arbitrary PSR logger.
2. Audit consumers of `App::getLogger()`, including chained calls. Standard PSR
   methods (`error()`, `debug()`, `warning()`, etc.) need no migration.
   `exception()` and `logDb()` are **not** PSR methods. They remain public on
   `Lsr\Logging\Logger`; callers that need those helpers must deliberately inject
   or retrieve a concrete LSR logger separately instead of assuming the Core
   getter returns one. Do not remove the helpers from the logging package or
   silently skip them when a plain logger is configured.
3. Alternatively, migrate each helper call to standard PSR records while
   preserving its behavior. For `exception($exception)`, use the same two
   records in this order, with empty contexts:

   ```php
   $logger = App::getInstance()->getLogger();
   $logger->error('Thrown Exception (' . $exception->getCode() . '): ' . $exception->getMessage());
   $logger->debug($exception->getTraceAsString());
   ```

   A single `error($message, ['exception' => $exception])` is not equivalent:
   it changes the message/context and removes the separate debug trace record.
   For `logDb($event)`, preserve its Dibi exception guard, optional nonzero
   `(code) ` prefix on the error message, and following `debug('SQL: ' . $sql)`
   only for nonempty exception SQL. Successful events emit nothing. Preserve
   empty contexts and let logging failures propagate; do not add catch/retry or
   flushing behavior as part of this migration.
4. Update the consumer's Composer constraint deliberately, rebuild compiled DI
   containers and restart long-running workers. Existing `^0.3` and `^0.5`
   Core constraints do not opt into 0.6. Applications can migrate and deploy
   independently; this unreleased package change does not update their source
   or lock files. ORM's corresponding PSR provider contract is separately
   versioned as ORM 0.4 (unreleased); Core also supports published ORM 0.3.

## Exact-host routing and links

Core 0.5 passes the current request URI host to `lsr/routing` 0.5. Routes on that exact normalized host take priority over unrestricted routes; unrestricted routes remain the fallback. Hosts are case-insensitive and a terminal DNS dot is ignored. The host constraint does not select a scheme or port. Requests with no URI host only match unrestricted routes.

In application route files, use the router's domain groups and optionally declare aliases after the routes:

```php
$this->domain('league')
    ->get('/results/{id}', [ResultsController::class, 'show'])
    ->name('league.results')
    ->localize('cs')
    ->localize('en', '/en/results/{id}');

$this->declareDomain('league.example.com', 'league');
```

Aliases resolve after all route sources load, using one lookup rather than recursive alias expansion. A reference with no declared alias is a literal host, including single-label hosts. Conflicting declarations fail, and alias declarations cannot change after resolution. For manually assembled routers, call `resolveDomains()` before routing or generating domain-bound links; normal `setup()`/`loadRoutes()` performs resolution.

`Links\Generator::route('league.results', ['id' => 42], locale: 'en')` uses the localized route's resolved domain. Links to another host are absolute; links to the current normalized host remain relative. The current request's scheme and port are preserved, including nonstandard ports: a domain constraint alone never upgrades HTTP to HTTPS or selects a destination-specific port. Configure that policy at the application/proxy layer.

Legacy `Generator::getLink('route.name')`, `getLinkObject('route.name')`, and `getAbsoluteLink('route.name')` also preserve the route domain and continue applying legacy path modifiers. `getRouteLink($route)` does the same for a route object. `App::redirect('route.name')` and `App::redirect($route)` preserve domain destinations; literal strings, URI objects and path arrays retain their previous behavior. The newer `route()` API selects exact localized variants and substitutes parameters; legacy calls and redirects keep their existing declared-path/modifier behavior rather than selecting a locale automatically.

The generator reads the current application's base URI for each call, so a shared generator can serve successive requests on different hosts without reusing the first request's host. Menu entries configured by route name retain their domain destination and only become active on the appropriate host; serialized named menu items refresh their URL and active state when restored. The Tracy routing panel shows the request host, the selected route's domain and the domain-specific routing trees.

Controller argument metadata caches include the resolved domain, so handlers sharing a method/path on different hosts can have different argument types. Request arguments accept compatible PSR request interfaces as well as the LSR request interface, including the PSR request used by redirect aliases.

### Trusted hosts, proxies and deployment

The request URI is the authority for both routing and generated URLs. Validate accepted hosts at the web server or trusted request-factory boundary; routing is not a host allowlist because unrestricted routes deliberately accept other hosts. Only trust forwarded host/scheme headers from explicitly configured proxies. Core does not read forwarded headers itself. Host declarations accept ASCII DNS/punycode names and IP literals, not schemes, paths, credentials, ports or wildcards; convert internationalized names to punycode before configuration.

Deploy core and routing 0.5 together in each consuming application and rebuild its compiled route cache whenever domain declarations or alias targets change. Compiled routes store resolved hosts, so changing environment-specific aliases without rebuilding the cache does not retarget cached routes. The routing 0.5 cache format rejects older cache payloads; use a cache path/version appropriate to each independently deployed application and restart long-running workers after deploying route changes.

## Development

CI runs the complete suite on PHP 8.4 and 8.5. Install the PHP extensions listed in [.github/workflows/ci.yml](.github/workflows/ci.yml), including the package's required extensions and Redis/ZIP for dependencies, then run:

```sh
composer install --prefer-dist --no-interaction --no-progress
composer cs
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit --no-coverage
```

The checkout must be writable; [tests/bootstrap.php](tests/bootstrap.php) defines the test filesystem constants and creates `tests/tmp/`. Tests construct their own services and container fixtures rather than booting an application container, so no MySQL or Redis server is needed. `composer test` and `composer phpstan` are also available. See [phpunit.xml](phpunit.xml) and [phpstan.neon](phpstan.neon).

Run `composer cs` to check PHP coding style and `composer cs:fix` (or `composer cbf`) to apply fixes with PHP CS Fixer. The rules and source paths are defined in [.php-cs-fixer.php](.php-cs-fixer.php).

## AI coding assistance

See [LSR Skills](https://github.com/Heroyt/lsr-skills) for AI agent skills for working with the LSR framework.

## License

Licensed under the [MIT License](LICENSE).
