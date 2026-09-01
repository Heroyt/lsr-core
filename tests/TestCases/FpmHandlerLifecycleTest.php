<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\App;
use Lsr\Core\Config;
use Lsr\Core\RouteHandler;
use Lsr\Core\Routing\Router;
use Lsr\Core\Translations;
use Lsr\Interfaces\RequestFactoryInterface;
use Lsr\Interfaces\SessionInterface;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class FpmHandlerLifecycleTest extends TestCase
{
    public function testLifecycleCompletesBeforeAsyncFlush(): void
    {
        if (!defined('TMP_DIR')) {
            define('TMP_DIR', sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        }
        $appProperty = new \ReflectionProperty(App::class, 'instance');
        $hadOriginalApp = $appProperty->isInitialized();
        $originalApp = $hadOriginalApp ? $appProperty->getValue() : null;
        $events = new FpmLifecycleEvents();
        $response = new Response(204);
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())->method('getFlash')->willReturn(null);
        $session->expects(self::once())->method('close')->willReturnCallback(
            static function () use ($events): void {
                $events->record('session.close');
            },
        );
        $factory = $this->createStub(RequestFactoryInterface::class);
        $factory->method('getHttpRequest')->willReturn(new ServerRequest('GET', '/health'));

        try {
            new FpmLifecycleApp(
                $this->createStub(Router::class),
                $this->createStub(RouteHandler::class),
                $session,
                $this->createStub(Config::class),
                $this->createStub(Translations::class),
                $response,
            );
            $handler = new RecordingFpmHandler($factory, $session, $events);
            $handler
                ->setRequestLifecycleHook(new RecordingRequestHook($events))
                ->addAsyncHandler(new RecordingAsyncHandler($events));

            $handler->run();
        } finally {
            if ($hadOriginalApp) {
                $appProperty->setValue(null, $originalApp);
            }
        }

        self::assertSame(
            ['request.begin', 'response.send', 'session.close', 'request.complete', 'async.run'],
            $events->events,
        );
        self::assertSame($response, $events->response);
    }
}
