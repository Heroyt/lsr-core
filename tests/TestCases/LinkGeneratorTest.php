<?php

namespace TestCases;

use Lsr\Core\App;
use Lsr\Core\Links\Generator;
use Lsr\Core\Routing\Route;
use Lsr\Core\Routing\Router;
use Lsr\Caching\Cache;
use Lsr\Enums\RequestMethod;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;

class LinkGeneratorTest extends TestCase
{
    private Router $router;
    private Generator $generator;

    protected function setUp(): void
    {
        Router::$availableRoutes = [];
        Router::$namedRoutes = [];
        App::prettyUrl();

        $app = $this->createStub(App::class);
        $app->method('getBaseUrlObject')->willReturn(new Uri('https://arena.test/'));

        $cache = $this->createStub(Cache::class);
        $this->router = new Router($cache);
        $this->generator = new Generator($this->router, $app);

        $route = Route::create(RequestMethod::GET, 'play', static fn() => null);
        $route->setName('play');
        $this->router->register($route);
        $this->router->registerNamed($route);
    }

    public function testLocalArrayLinkReturnsPathAndQueryOnly(): void
    {
        self::assertSame('/play/round?tab=score', $this->generator->getLink(['play', 'round', 'tab' => 'score']));
    }

    public function testNamedRouteReturnsPathOnly(): void
    {
        self::assertSame('/play', $this->generator->getLink('play'));
    }

    public function testExplicitLocalStringReturnsPathOnly(): void
    {
        self::assertSame('/admin', $this->generator->getLink('/admin'));
    }

    public function testExternalStringStaysAbsolute(): void
    {
        self::assertSame('https://x.test', $this->generator->getLink('https://x.test'));
    }

    public function testAbsoluteLinkOptInIncludesHost(): void
    {
        self::assertSame('https://arena.test/play/round', $this->generator->getAbsoluteLink(['play', 'round']));
    }
}
