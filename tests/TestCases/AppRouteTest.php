<?php
declare(strict_types=1);

namespace TestCases;

use Lsr\Core\App;
use Lsr\Core\Routing\Router;
use Lsr\Enums\RequestMethod;
use Lsr\Interfaces\RequestInterface;
use PHPUnit\Framework\TestCase;

class AppRouteTest extends TestCase
{
    public function testCachedRouteKeepsResolvedLocaleParameters(): void
    {
        $app = (new \ReflectionClass(App::class))->newInstanceWithoutConstructor();
        $router = new Router();
        $router->unregisterAll();
        $router
            ->get('/ochrana-osobnich-udaju', static fn() => null)
            ->localize('cs')
            ->localize('en', '/en/privacy');
        $request = $this->createStub(RequestInterface::class);
        $request->method('getType')->willReturn(RequestMethod::GET);
        $request->method('getPath')->willReturn(['en', 'privacy']);
        $app->setRequest($request);

        $firstParams = [];
        $app->getRoute($firstParams);
        $cachedParams = [];
        $app->getRoute($cachedParams);

        self::assertSame(['lang' => 'en'], $firstParams);
        self::assertSame($firstParams, $cachedParams);
        $router->unregisterAll();
    }
}
