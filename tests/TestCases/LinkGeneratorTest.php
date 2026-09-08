<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\App;
use Lsr\Core\Links\Generator;
use Lsr\Core\Routing\Route;
use Lsr\Core\Routing\Router;
use Lsr\Core\Translations;
use Lsr\Enums\RequestMethod;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LinkGeneratorTest extends TestCase
{
    private Router $router;
    private Generator $generator;

    protected function setUp(): void {
        Router::$availableRoutes = [];
        Router::$namedRoutes = [];
        App::prettyUrl();

        $app = $this->createStub(App::class);
        $app->method('getBaseUrlObject')->willReturn(new Uri('https://arena.test/'));

        $translations = $this->createStub(Translations::class);
        $translations->method('getLangId')->willReturn('cs');
        $this->router = new Router();
        $this->generator = new Generator($this->router, $app, translations: $translations);

        $route = Route::create(RequestMethod::GET, 'play', static fn () => null);
        $route->setName('play');
        $this->router->register($route);
        $this->router->registerNamed($route);
    }

    public function test_local_array_link_returns_path_and_query_only(): void {
        self::assertSame('/play/round?tab=score', $this->generator->getLink(['play', 'round', 'tab' => 'score']));
    }

    public function test_named_route_returns_path_only(): void {
        self::assertSame('/play', $this->generator->getLink('play'));
    }

    public function test_localized_named_route_uses_explicit_or_current_locale(): void {
        $this->router
            ->get('/ochrana-osobnich-udaju', static fn () => null)
            ->name('public.privacy')
            ->localize('cs')
            ->localize('en', '/en/privacy');

        self::assertSame('/ochrana-osobnich-udaju', $this->generator->route('public.privacy'));
        self::assertSame('/en/privacy', $this->generator->route('public.privacy', locale: 'en'));
    }

    public function test_localized_route_substitutes_parameters_and_preserves_query_and_fragment(): void {
        $this->router
            ->get('/vysledky/{gameId}/{slug}', static fn () => null)
            ->name('results.published')
            ->localize('cs')
            ->localize('en', '/en/results/{slug}/{gameId}');

        self::assertSame(
            '/en/results/night%20final/42?tab=score&page=2#teams',
            $this->generator->route(
                'results.published',
                [
                    'gameId' => 42,
                    'slug'   => 'night final',
                    'tab'    => 'score',
                    'page'   => 2,
                ],
                'en',
                'teams',
            ),
        );
    }

    public function test_missing_localized_variant_fails_explicitly(): void {
        $this->router
            ->get('/ochrana-osobnich-udaju', static fn () => null)
            ->name('public.privacy')
            ->localize('cs')
            ->localize('en', '/en/privacy');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Route "public.privacy" has no path for locale "de".');
        $this->generator->route('public.privacy', locale: 'de');
    }

    public function test_missing_required_parameter_fails_explicitly(): void {
        $this->router
            ->get('/vysledky/{gameId}', static fn () => null)
            ->name('results.published');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing parameter "gameId" for route "results.published".');
        $this->generator->route('results.published');
    }

    public function test_nonlocalized_route_ignores_generation_locale(): void {
        self::assertSame('/play', $this->generator->route('play', locale: 'en'));
    }

    public function test_non_pretty_named_route_keeps_path_and_extra_query_parameters(): void {
        App::uglyUrl();
        try {
            $app = $this->createStub(App::class);
            $app->method('getBaseUrlObject')->willReturn(new Uri('https://arena.test/'));
            $generator = new Generator($this->router, $app);
            self::assertSame(
                '/?p%5B0%5D=play&tab=score',
                $generator->route('play', ['tab' => 'score']),
            );
        } finally {
            App::prettyUrl();
        }
    }

    public function test_explicit_locale_does_not_read_or_mutate_current_locale(): void {
        $translations = $this->createMock(Translations::class);
        $translations->expects($this->never())->method('getLangId');
        $translations->expects($this->never())->method('setLang');
        $app = $this->createStub(App::class);
        $app->method('getBaseUrlObject')->willReturn(new Uri('https://arena.test/'));
        $generator = new Generator($this->router, $app, translations: $translations);
        $this->router
            ->get('/ochrana-osobnich-udaju', static fn () => null)
            ->name('public.privacy')
            ->localize('cs')
            ->localize('en', '/en/privacy');

        self::assertSame('/en/privacy', $generator->route('public.privacy', locale: 'en'));
    }

    public function test_explicit_local_string_returns_path_only(): void {
        self::assertSame('/admin', $this->generator->getLink('/admin'));
    }

    public function test_external_string_stays_absolute(): void {
        self::assertSame('https://x.test', $this->generator->getLink('https://x.test'));
    }

    public function test_absolute_link_opt_in_includes_host(): void {
        self::assertSame('https://arena.test/play/round', $this->generator->getAbsoluteLink(['play', 'round']));
    }
}
