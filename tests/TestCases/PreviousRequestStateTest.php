<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\App;
use Lsr\Core\Config;
use Lsr\Core\FpmHandler;
use Lsr\Core\PreviousRequestState;
use Lsr\Core\Requests\Request;
use Lsr\Core\RouteHandler;
use Lsr\Core\Routing\Router;
use Lsr\Core\Translations;
use Lsr\Interfaces\RequestFactoryInterface;
use Lsr\Interfaces\SessionInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class PreviousRequestStateTest extends TestCase
{
    public function test_capture_excludes_runtime_and_sensitive_request_data(): void {
        $request = new Request(
            (new ServerRequest(
                'POST',
                '/submit',
                ['Authorization' => 'Bearer secret-token'],
                'secret-body',
                serverParams: ['SECRET_ENV' => 'secret-environment'],
            ))->withAttribute('runtimeService', static fn () => null),
        );
        $request->addPassError('validation error');
        $request->addPassNotice(['title' => 'Notice', 'content' => 'validation notice']);

        $state = PreviousRequestState::capture($request);
        $serialized = serialize($state);

        self::assertSame(
            [
                'errors' => ['validation error'],
                'notices' => [['title' => 'Notice', 'content' => 'validation notice']],
            ],
            $state,
        );
        self::assertStringNotContainsString('secret-token', $serialized);
        self::assertStringNotContainsString('secret-body', $serialized);
        self::assertStringNotContainsString('secret-environment', $serialized);
    }

    public function test_restore_adds_pass_through_messages_to_current_request(): void {
        $request = new Request(new ServerRequest('GET', '/target'));

        PreviousRequestState::restore(
            $request,
            [
                'errors' => ['validation error'],
                'notices' => [['content' => 'validation notice']],
            ],
        );

        self::assertSame(['validation error'], $request->getErrors());
        self::assertSame([['content' => 'validation notice']], $request->getNotices());
    }

    public function test_fpm_handler_restores_scalar_previous_request_state(): void {
        $factory = $this->createStub(RequestFactoryInterface::class);
        $factory->method('getHttpRequest')->willReturn(new Request(new ServerRequest('GET', '/target')));
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('getFlash')
            ->with(PreviousRequestState::FLASH_KEY)
            ->willReturn([
                'errors' => ['validation error'],
                'notices' => [['content' => 'validation notice']],
            ]);

        $request = (new FpmHandler($factory, $session))->createRequest();

        self::assertSame(['validation error'], $request->getErrors());
        self::assertSame([['content' => 'validation notice']], $request->getNotices());
    }

    public function test_app_redirect_stores_only_scalar_previous_request_state(): void {
        if ( ! defined('TMP_DIR')) {
            define('TMP_DIR', sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        }
        $source = (new Request(new ServerRequest(
            'POST',
            '/submit',
            ['Authorization' => 'Bearer secret-token'],
            'secret-body',
        )))->withAttribute('runtimeService', static fn () => null);
        $source->addPassError('validation error');
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('flash')
            ->with(
                PreviousRequestState::FLASH_KEY,
                ['errors' => ['validation error'], 'notices' => []],
            );
        $app = new App(
            $this->createStub(Router::class),
            $this->createStub(RouteHandler::class),
            $session,
            $this->createStub(Config::class),
            $this->createStub(Translations::class),
        );

        $response = $app->redirect('/target', $source);

        self::assertSame('/target', $response->getHeaderLine('Location'));
    }

}
