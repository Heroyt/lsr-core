<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\App;
use Lsr\Core\Config;
use Lsr\Core\Links\Generator;
use Lsr\Core\Links\LanguagePrefixer;
use Lsr\Core\Menu\MenuItem;
use Lsr\Core\Requests\Request;
use Lsr\Core\RouteHandler;
use Lsr\Core\Routing\Router;
use Lsr\Core\Tracy\RoutingTracyPanel;
use Lsr\Core\Translations;
use Lsr\Interfaces\SessionInterface;
use Nette\DI\Container;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** Isolate App's non-nullable static singletons, which cannot be reset to uninitialized in-process. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DomainRoutingIntegrationTest extends TestCase
{
    private Router $router;
    private App $app;
    private Generator $generator;

    protected function setUp(): void {
        App::prettyUrl();
        $this->router = new Router();
        $this->router->unregisterAll();
        $translations = $this->createStub(Translations::class);
        $translations->method('getLangId')->willReturn('cs');
        $this->app = new App(
            $this->router,
            $this->createStub(RouteHandler::class),
            $this->createStub(SessionInterface::class),
            $this->createStub(Config::class),
            $translations,
        );
        $this->router->unregisterAll();
        $this->request('https://arena.test:8443/play');
        $this->generator = new Generator($this->router, $this->app, translations: $translations);
        $container = new Container();
        $container->addService('links.generator', $this->generator);
        (new ReflectionClass(App::class))->getProperty('container')->setValue(null, $container);
    }

    protected function tearDown(): void {
        $this->router->unregisterAll();
    }

    public function test_app_selects_domain_routes_and_falls_back_across_successive_requests(): void {
        $public = $this->router->get('/play', static fn () => null)->name('public.play');
        $this->router->domain('ARENA.TEST.')->get('/play', static fn () => null)->name('arena.play');
        $this->router->domain('league')->get('/play', static fn () => null)->name('league.play');
        $this->router->declareDomain('league.test', 'league');
        $this->router->resolveDomains();
        $params = [];

        self::assertSame('arena.play', $this->app->getRoute($params)?->getName());
        $this->request('https://league.test/play');
        self::assertSame('league.play', $this->app->getRoute($params)?->getName());
        $this->request('https://unknown.test/play');
        self::assertSame($public, $this->app->getRoute($params));
        $this->request('/play');
        self::assertSame($public, $this->app->getRoute($params));
        $this->request('https://ARENA.TEST./play');
        self::assertSame('arena.play', $this->app->getRoute($params)?->getName());
    }

    public function test_named_links_and_redirects_preserve_cross_host_scheme_and_port(): void {
        $this->router->domain('league.test')->get('/play', static fn () => null)->name('league.play');
        $this->router->resolveDomains();
        $route = $this->router->getRouteByName('league.play');
        self::assertNotNull($route);
        $expected = 'https://league.test:8443/play';

        self::assertSame($expected, $this->generator->route('league.play'));
        self::assertSame($expected, $this->generator->getLink('league.play'));
        self::assertSame($expected, (string) $this->generator->getLinkObject('league.play'));
        self::assertSame($expected, $this->app->redirect('league.play')->getHeaderLine('Location'));
        self::assertSame($expected, $this->app->redirect($route)->getHeaderLine('Location'));

        $this->request('http://league.test:8080/play');
        self::assertSame('/play', $this->generator->route('league.play'));
        self::assertSame('/play', $this->generator->getLink('league.play'));
        self::assertSame('http://league.test:8080/play', (string) $this->generator->getLinkObject('league.play'));
        self::assertSame('/play', $this->app->redirect($route)->getHeaderLine('Location'));
    }

    public function test_reused_generator_does_not_leak_first_request_host(): void {
        $this->router->domain('league.test')->get('/play', static fn () => null)->name('league.play');
        $this->router->get('/health', static fn () => null)->name('health');
        $this->router->resolveDomains();

        foreach (['arena.test', 'league.test', 'arena.test'] as $host) {
            $this->request('https://' . $host . '/play');
            $expected = $host === 'league.test' ? '/play' : 'https://league.test/play';
            self::assertSame($expected, $this->generator->route('league.play'));
            self::assertSame($expected, $this->generator->getLink('league.play'));
            self::assertSame('https://' . $host . '/health', $this->generator->getAbsoluteLink('health'));
            self::assertSame('https://' . $host . '/', (string) $this->generator->getLinkObject());
            self::assertSame('/health', $this->generator->route('health'));
        }
    }

    public function test_localized_domain_links_encode_parameters_and_keep_query_and_fragment(): void {
        $this->router->domain('league.test')
            ->get('/vysledky/{id}', static fn () => null)
            ->name('results')
            ->localize('cs')
            ->localize('en', '/en/results/{id}');
        $this->router->resolveDomains();

        self::assertSame(
            'https://league.test:8443/en/results/night%20final?tab=score#teams',
            $this->generator->route('results', ['id' => 'night final', 'tab' => 'score'], 'en', 'teams'),
        );
        $this->request('https://LEAGUE.TEST.:8443/play');
        self::assertSame('/vysledky/42', $this->generator->route('results', ['id' => 42]));
        self::assertSame('/en/results/42', $this->generator->route('results', ['id' => 42], 'en'));
    }

    public function test_legacy_language_modifiers_keep_named_route_domain(): void {
        $this->router->domain('league.test')->get('/[lang]/play', static fn () => null)->name('league.play');
        $this->router->resolveDomains();
        $translations = $this->createStub(Translations::class);
        $translations->method('getLangId')->willReturn('en');
        $translations->method('getDefaultLangId')->willReturn('cs');
        $generator = new Generator($this->router, $this->app, [new LanguagePrefixer($translations)]);

        self::assertSame('https://league.test:8443/en/play', $generator->getLink('league.play'));
        self::assertSame('https://league.test:8443/en/play', (string) $generator->getLinkObject('league.play'));
    }

    public function test_domain_links_work_without_pretty_urls(): void {
        App::uglyUrl();
        $generator = new Generator($this->router, $this->app);
        $this->router->domain('league.test')->get('/play', static fn () => null)->name('league.play');
        $this->router->resolveDomains();

        self::assertSame(
            'https://league.test:8443/?p%5B0%5D=play&tab=score',
            $generator->route('league.play', ['tab' => 'score']),
        );
        self::assertSame('https://league.test:8443/?p%5B0%5D=play', $generator->getLink('league.play'));
    }

    public function test_unrestricted_legacy_links_and_redirect_inputs_stay_compatible(): void {
        $route = $this->router->get('/play', static fn () => null)->name('play');
        $this->router->resolveDomains();

        self::assertSame('/play', $this->generator->getLink('play'));
        self::assertSame('/play?tab=score', $this->generator->getLink(['play', 'tab' => 'score']));
        self::assertSame('/play', $this->app->redirect($route)->getHeaderLine('Location'));
        self::assertSame('/play', $this->app->redirect(['play'])->getHeaderLine('Location'));
        self::assertSame('/literal', $this->app->redirect('/literal')->getHeaderLine('Location'));
        self::assertSame('https://external_host.test/path', $this->generator->getLink('https://external_host.test/path'));
    }

    public function test_named_menu_destination_and_active_state_follow_current_host(): void {
        $this->router->domain('league.test')->get('/play', static fn () => null)->name('league.play');
        $this->router->resolveDomains();
        $item = new MenuItem('League', path: ['play'], routeName: 'league.play');
        self::assertSame('https://league.test:8443/play', $item->url);
        self::assertFalse($item->active);
        $parent = new MenuItem('Menu', path: ['menu'], children: [$item]);
        self::assertFalse($parent->active);

        $this->request('https://league.test/play');
        self::assertTrue($item->checkActive());
        self::assertTrue($parent->checkActive());
        self::assertSame('/play', $item->url);
        $serialized = serialize($item);
        $this->request('https://arena.test/play');
        $restored = unserialize($serialized, ['allowed_classes' => [MenuItem::class]]);
        self::assertInstanceOf(MenuItem::class, $restored);
        self::assertFalse($restored->active);
        self::assertSame('https://league.test/play', $restored->url);
    }

    public function test_domain_menu_treats_non_dns_request_hosts_as_nonmatches(): void {
        $this->router->domain('league.test')->get('/play', static fn () => null)->name('league.play');
        $this->router->resolveDomains();
        $this->request('https://public_service.test:8443/play');
        $item = new MenuItem('League', path: ['play'], routeName: 'league.play');

        self::assertFalse($item->active);
        self::assertSame('https://league.test:8443/play', $item->url);
    }

    public function test_routing_panel_displays_other_domain_routes(): void {
        $this->router->get('/play', static fn () => null)->name('public.play');
        $this->router->domain('league.test')->get('/results/{id}', static fn () => null)->name('league.results');
        $this->router->resolveDomains();
        $target = $this->router->getRouteByName('league.results');
        self::assertNotNull($target);
        $this->router->get('/legacy/{id}', $target)->name('legacy.results');

        $panel = (new RoutingTracyPanel())->getPanel();
        self::assertStringContainsString('league.test', $panel);
        self::assertStringContainsString('league.results', $panel);
        self::assertStringContainsString('legacy.results', $panel);
        self::assertStringContainsString('arena.test', $panel);
    }

    private function request(string $uri): void {
        $this->app->setRequest(new Request(new ServerRequest('GET', $uri)));
    }
}
