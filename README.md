# LSR Core

`lsr/core` provides the Laser framework application runtime: Nette dependency injection, HTTP dispatch, sessions, Latte templating, translations and integration with the LSR routing, request, database, cache and ORM packages. Its namespace is `Lsr\Core`.

## Requirements

- PHP `>=8.4`.
- PHP extensions: `fileinfo`, `gettext`, `simplexml`, `ctype`, `mbstring` and `pdo_sqlite`.
- Nette DI `^3.2`, Latte `^3.0`, PHP dotenv `^5.6` and Nette PHP Generator `^4.1`.
- LSR interfaces, logging, routing (`^0.4`), request, DB, serializer, cache and ORM dependencies; see [composer.json](composer.json) for exact constraints and transitive platform requirements.
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
