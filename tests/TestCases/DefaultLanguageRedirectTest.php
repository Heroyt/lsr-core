<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\Middleware\DefaultLanguageRedirect;
use Lsr\Core\Translations;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DefaultLanguageRedirectTest extends TestCase
{
    public function test_only_exact_leading_default_locale_segment_is_removed(): void {
        $translations = $this->createStub(Translations::class);
        $translations->method('getDefaultLangId')->willReturn('cs');
        $middleware = new DefaultLanguageRedirect($translations);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface {
                return new Response();
            }
        };
        $request = (new ServerRequest('GET', '/cs/cast/cs?tab=score'))->withAttribute('lang', 'cs');

        $response = $middleware->process($request, $handler);

        self::assertSame(308, $response->getStatusCode());
        self::assertSame('/cast/cs?tab=score', $response->getHeaderLine('Location'));
    }

    public function test_redirect_preserves_trailing_slash(): void {
        $translations = $this->createStub(Translations::class);
        $translations->method('getDefaultLangId')->willReturn('cs');
        $middleware = new DefaultLanguageRedirect($translations);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface {
                return new Response();
            }
        };
        $request = (new ServerRequest('GET', '/cs/cast/?tab=score'))->withAttribute('lang', 'cs');

        $response = $middleware->process($request, $handler);

        self::assertSame('/cast/?tab=score', $response->getHeaderLine('Location'));
    }

    public function test_canonical_path_never_redirects_to_itself(): void {
        $translations = $this->createStub(Translations::class);
        $translations->method('getDefaultLangId')->willReturn('cs');
        $middleware = new DefaultLanguageRedirect($translations);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface {
                return new Response(204);
            }
        };
        $request = (new ServerRequest('GET', '/cast/cs'))->withAttribute('lang', 'cs');

        $response = $middleware->process($request, $handler);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Location'));
    }
}
