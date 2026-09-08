<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\App;
use Lsr\Core\Http\Lifecycle\RequestOperation;
use Lsr\Core\Http\Lifecycle\RouteResolutionEvent;
use Lsr\Core\Routing\Router;
use Lsr\Enums\RequestMethod;
use Lsr\Interfaces\RequestInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AppRouteLifecycleTest extends TestCase
{
    protected function tearDown(): void {
        (new Router())->unregisterAll();
    }

    public function test_route_resolution_reports_only_the_first_matched_resolution(): void {
        $app = $this->createApp('/observed');
        $hook = new RecordingRouteResolutionHook();
        $operationHook = new RecordingRequestOperationHook();
        $app->setRouteResolutionHook($hook);
        $app->setRequestOperationLifecycleHook($operationHook);

        $params = [];
        $route = $app->getRoute($params);
        $app->getRoute($params);

        self::assertNotNull($route);
        self::assertCount(1, $hook->events);
        self::assertSame($route, $hook->events[0]->route);
        self::assertSame(RequestMethod::GET, $hook->events[0]->method);
        self::assertSame(RouteResolutionEvent::MATCHED, $hook->events[0]->outcome());
        self::assertGreaterThanOrEqual(0.0, $hook->events[0]->durationSeconds);
        self::assertSame(RequestOperation::RouteResolution, $operationHook->begun[0]['operation']);
        self::assertSame('GET', $operationHook->begun[0]['attributes']['http.request.method']);
        self::assertSame(RequestOperation::RouteResolution, $operationHook->completed[0]['operation']);
        self::assertNull($operationHook->completed[0]['exception']);
        self::assertCount(1, $operationHook->begun);
        self::assertCount(1, $operationHook->completed);
    }

    public function test_hook_failure_does_not_affect_route_resolution(): void {
        $app = $this->createApp('/observed');
        $hook = new RecordingRouteResolutionHook();
        $hook->fail = true;
        $app->setRouteResolutionHook($hook);

        $params = [];
        self::assertNotNull($app->getRoute($params));
    }

    public function test_operation_hook_failures_do_not_affect_route_resolution(): void {
        $beginFailure = new RecordingRequestOperationHook();
        $beginFailure->failBegin = true;
        $app = $this->createApp('/begin-failure');
        $app->setRequestOperationLifecycleHook($beginFailure);
        $params = [];
        self::assertNotNull($app->getRoute($params));

        $completionFailure = new RecordingRequestOperationHook();
        $completionFailure->failComplete = true;
        $app = $this->createApp('/completion-failure');
        $app->setRequestOperationLifecycleHook($completionFailure);
        $params = [];
        self::assertNotNull($app->getRoute($params));
    }

    private function createApp(string $path): App {
        $router = new Router();
        $router->unregisterAll();
        $router->get($path, static fn () => null);

        $request = $this->createStub(RequestInterface::class);
        $request->method('getType')->willReturn(RequestMethod::GET);
        $request->method('getPath')->willReturn(explode('/', trim($path, '/')));

        $app = (new ReflectionClass(App::class))->newInstanceWithoutConstructor();
        $app->setRequest($request);
        return $app;
    }
}
