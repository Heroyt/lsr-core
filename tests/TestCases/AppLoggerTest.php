<?php

declare(strict_types=1);

namespace TestCases;

use Closure;
use Dibi\Connection;
use Dibi\DriverException;
use Dibi\Event;
use Lsr\Core\App;
use Lsr\Core\Config;
use Lsr\Core\DI\LsrExtension;
use Lsr\Core\Http\TracyExceptionHandler;
use Lsr\Core\RouteHandler;
use Lsr\Core\Routing\Router;
use Lsr\Core\Translations;
use Lsr\Interfaces\ResponseFactoryInterface;
use Lsr\Interfaces\SessionInterface;
use Lsr\Logging\Logger;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\PhpGenerator;
use Nette\InvalidArgumentException;
use Nette\Schema\Processor;
use Nette\Schema\ValidationException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Stringable;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AppLoggerTest extends TestCase
{
    public function test_direct_construction_keeps_default_output_and_accepts_plain_psr_logger(): void {
        $app = new App(
            $this->createStub(Router::class),
            $this->createStub(RouteHandler::class),
            $this->createStub(SessionInterface::class),
            $this->createStub(Config::class),
            $this->createStub(Translations::class),
        );
        $fallback = $app->getLogger();
        self::assertInstanceOf(Logger::class, $fallback);
        self::assertSame($fallback, $app->getLogger());
        $this->assertExceptionOutput($fallback, LOG_DIR . 'app-' . date('Y-m-d') . '.log');

        $logger = new AppPsrRecordingLogger();
        $app->setLogger($logger);
        self::assertSame($logger, $app->getLogger());
        $exception = new RuntimeException('Application failure', 17);
        $message = new class implements Stringable {
            public function __toString(): string {
                return 'Application message';
            }
        };
        $context = ['exception' => $exception, 'request' => 42];
        $app->getLogger()->error($message, $context);
        self::assertSame([['error', $message, $context]], $logger->records);
    }

    public function test_default_output_is_isolated_from_global_logger(): void {
        $container = $this->container();
        $app = $container->getService('lsr.app');
        self::assertInstanceOf(App::class, $app);
        $logger = $app->getLogger();
        self::assertInstanceOf(Logger::class, $logger);
        self::assertSame($logger, $container->getService('lsr.logger'));
        self::assertNotSame($container->getService('logger'), $logger);
        self::assertSame($container->getService('logger'), $container->getByType(LoggerInterface::class));
        $this->assertExceptionOutput($logger, LOG_DIR . 'app-' . date('Y-m-d') . '.log');
    }

    public function test_explicit_shared_logger_preserves_identity_and_helpers(): void {
        $container = $this->container('@logger');
        $app = $container->getService('lsr.app');
        self::assertInstanceOf(App::class, $app);
        self::assertSame($container->getService('logger'), $app->getLogger());
        self::assertSame($container->getService('logger'), $container->getService('lsr.logger'));
        $logger = $app->getLogger();
        self::assertInstanceOf(Logger::class, $logger);
        $this->assertExceptionOutput($logger, TMP_DIR . 'app-global-' . date('Y-m-d') . '.log');
    }

    public function test_plain_psr_alias_chain_with_setup_keeps_shared_identity_and_autowiring(): void {
        $container = $this->container('@selectedLogger', static function (ContainerBuilder $builder): void {
            $shared = $builder->getDefinition('logger');
            self::assertInstanceOf(ServiceDefinition::class, $shared);
            $shared->setFactory(AppPsrRecordingLogger::class);
            $builder->addAlias('selectedLogger', 'loggerAlias');
            $builder->addDefinition('loggerAlias')->setFactory('@logger')->setAutowired(false);
            $selected = $builder->getDefinition('lsr.logger');
            self::assertInstanceOf(ServiceDefinition::class, $selected);
            $selected->addSetup('info', ['logger configured']);
        });
        $app = $container->getService('lsr.app');
        self::assertInstanceOf(App::class, $app);
        $shared = $container->getService('logger');
        self::assertInstanceOf(AppPsrRecordingLogger::class, $shared);
        $app->getLogger()->warning('after configuration');
        self::assertSame($shared, $app->getLogger());
        self::assertSame($shared, $container->getService('lsr.logger'));
        self::assertSame($shared, $container->getService('selectedLogger'));
        self::assertSame($shared, $container->getByType(LoggerInterface::class));
        self::assertSame([
            ['info', 'logger configured', []],
            ['warning', 'after configuration', []],
        ], $shared->records);
    }

    public function test_dedicated_logger_isolated_from_global_and_app_override_keeps_constructor(): void {
        $container = $this->container('@dedicated', static function (ContainerBuilder $builder): void {
            $builder->addDefinition('dedicated')->setFactory(Logger::class, [TMP_DIR, 'app-dedicated'])->setAutowired(false);
            $definition = $builder->getDefinition('lsr.app');
            self::assertInstanceOf(ServiceDefinition::class, $definition);
            $definition->setFactory(LoggerSelectionApp::class);
        });
        $app = $container->getService('lsr.app');
        self::assertInstanceOf(LoggerSelectionApp::class, $app);
        self::assertSame($container->getService('dedicated'), $app->getLogger());
        self::assertNotSame($container->getService('logger'), $app->getLogger());
        $logger = $app->getLogger();
        self::assertInstanceOf(Logger::class, $logger);
        $this->assertExceptionOutput($logger, TMP_DIR . 'app-dedicated-' . date('Y-m-d') . '.log');
    }

    public function test_non_reference_configuration_is_rejected(): void {
        $this->expectException(ValidationException::class);
        $this->container(Logger::class);
    }

    public function test_wrong_reference_type_is_rejected_during_configuration(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->container('@wrong', static function (ContainerBuilder $builder): void {
            $builder->addDefinition('wrong')->setFactory(stdClass::class);
        });
    }

    public function test_wrong_package_logger_override_is_rejected_during_configuration(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->container(null, static function (ContainerBuilder $builder): void {
            $definition = $builder->getDefinition('lsr.logger');
            self::assertInstanceOf(ServiceDefinition::class, $definition);
            $definition->setFactory(stdClass::class);
        });
    }

    /** @param (Closure(ContainerBuilder): void)|null $configure */
    private function container(?string $logger = null, ?Closure $configure = null): Container {
        $compiler = new Compiler();
        $extension = new LsrExtension();
        $compiler->addExtension('lsr', $extension);
        $config = (new Processor())->process($extension->getConfigSchema(), [
            'appDir' => ROOT,
            'tempDir' => TMP_DIR,
            'logger' => $logger,
            'http' => ['exceptionHandlers' => [TracyExceptionHandler::class]],
        ]);
        self::assertIsObject($config);
        $extension->setConfig($config);
        $extension->loadConfiguration();
        $builder = $compiler->getContainerBuilder();
        // Compile the real App/logger definitions without unrelated HTTP, DB or template dependencies.
        foreach ($builder->getAliases() as $alias => $target) {
            $builder->removeAlias($alias);
        }
        foreach ($builder->getDefinitions() as $name => $definition) {
            if ( ! in_array($name, [ContainerBuilder::ThisContainer, 'lsr.app', 'lsr.logger'], true)) {
                $builder->removeDefinition($name);
            }
        }
        $services = [
            'router' => $this->createStub(Router::class),
            'routeHandler' => $this->createStub(RouteHandler::class),
            'session' => $this->createStub(SessionInterface::class),
            'config' => $this->createStub(Config::class),
            'translations' => $this->createStub(Translations::class),
            'responseFactory' => $this->createStub(ResponseFactoryInterface::class),
        ];
        foreach ($services as $name => $service) {
            $builder->addImportedDefinition($name)->setType($service::class);
        }
        $builder->addDefinition('logger')->setFactory(Logger::class, [TMP_DIR, 'app-global']);
        $configure?->__invoke($builder);
        $builder->resolve();
        $extension->beforeCompile();
        $builder->complete();
        $generator = new PhpGenerator($builder);
        $class = 'AppLoggerContainer' . bin2hex(random_bytes(8));
        eval($generator->toString($generator->generate($class)));
        $container = new $class();
        self::assertInstanceOf(Container::class, $container);
        foreach ($services as $name => $service) {
            $container->addService($name, $service);
        }
        return $container;
    }

    private function assertExceptionOutput(Logger $logger, string $file): void {
        $previous = is_file($file) ? file_get_contents($file) : null;
        try {
            $exception = new RuntimeException('Core logger selection regression', 17);
            $logger->exception($exception);
            $event = new Event($this->createStub(Connection::class), Event::QUERY, 'SELECT regression');
            $event->result = new DriverException('Core database regression', 23, 'SELECT regression');
            $logger->logDb($event);
            $content = file_get_contents($file);
            self::assertIsString($content);
            $written = substr($content, is_string($previous) ? strlen($previous) : 0);
            self::assertStringContainsString('ERROR: Thrown Exception (17): Core logger selection regression', $written);
            self::assertStringContainsString('DEBUG: ' . $exception->getTraceAsString(), $written);
            self::assertStringContainsString('ERROR: (23) Core database regression', $written);
            self::assertStringContainsString('DEBUG: SQL: SELECT regression', $written);
        } finally {
            if (is_string($previous)) {
                file_put_contents($file, $previous);
            } elseif (is_file($file)) {
                unlink($file);
            }
        }
    }
}

final class LoggerSelectionApp extends App
{
    public function __construct() {
    }
}

final class AppPsrRecordingLogger extends AbstractLogger
{
    /** @var list<array{mixed, mixed, array<string, mixed>}> */
    public array $records = [];

    /**
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void {
        $this->records[] = [$level, $message, $context];
    }
}
