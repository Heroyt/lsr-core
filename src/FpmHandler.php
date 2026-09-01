<?php

declare(strict_types=1);

namespace Lsr\Core;

use Lsr\Core\Http\AsyncHandlerInterface;
use Lsr\Core\Http\ExceptionHandlerInterface;
use Lsr\Core\Http\Lifecycle\RequestLifecycleHookInterface;
use Lsr\Core\Http\Lifecycle\RequestLifecycleScopeInterface;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\DispatchBreakException;
use Lsr\Interfaces\RequestFactoryInterface;
use Lsr\Interfaces\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;
use Tracy\Debugger;

class FpmHandler
{
    /**
     * @param  ExceptionHandlerInterface[]  $exceptionHandlers  Exceptions are handled by the first valid handler.
     * @param  AsyncHandlerInterface[]  $asyncHandlers  Handlers that are run after the response is sent.
     */
    public function __construct(
        protected RequestFactoryInterface $requestFactory,
        protected SessionInterface $session,
        protected array $exceptionHandlers = [],
        protected array $asyncHandlers = [],
        protected ?RequestLifecycleHookInterface $requestLifecycle = null,
    ) {
        // Validate that all handlers are of the correct type
        /** @phpstan-ignore instanceof.alwaysTrue */
        assert(array_all($this->exceptionHandlers, static fn($val) => $val instanceof ExceptionHandlerInterface));
        /** @phpstan-ignore instanceof.alwaysTrue */
        assert(array_all($this->asyncHandlers, static fn($val) => $val instanceof AsyncHandlerInterface));
    }

    public function setRequestLifecycleHook(RequestLifecycleHookInterface $hook): static
    {
        $this->requestLifecycle = $hook;
        return $this;
    }

    public function addAsyncHandler(AsyncHandlerInterface $handler): static
    {
        $this->asyncHandlers[] = $handler;
        return $this;
    }

    protected function handleAsync(): void
    {
        foreach ($this->asyncHandlers as $handler) {
            $handler->run();
        }
    }

    public function run(): void
    {
        $app = App::getInstance();

        try {
            // Parse request
            $request = $this->createRequest();
        } catch (DispatchBreakException $e) {
            $this->finishRequest($e->getResponse(), null);
            return;
        }

        $scope = $this->beginLifecycle($request);
        $response = null;
        $failure = null;

        try {
            $app->setRequest($request);
            $response = $this->withCookies($app->run());
        } catch (DispatchBreakException $e) {
            $response = $this->withCookies($e->getResponse());
        } catch (Throwable $e) {
            $this->recordLifecycleException($scope, $e);
            try {
                $response = $this->withCookies($this->handleException($e, $request));
            } catch (Throwable $handlerException) {
                $this->recordLifecycleException($scope, $handlerException);
                $failure = $handlerException;
            }
        }

        try {
            $this->finishRequest($response, $scope);
        } catch (Throwable $finishException) {
            $failure ??= $finishException;
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    private function beginLifecycle(Request $request): ?RequestLifecycleScopeInterface
    {
        try {
            return $this->requestLifecycle?->begin($request);
        } catch (Throwable) {
            return null;
        }
    }

    private function recordLifecycleException(
        ?RequestLifecycleScopeInterface $scope,
        Throwable $exception
    ): void {
        try {
            $scope?->recordException($exception);
        } catch (Throwable) {
            // Lifecycle hooks must never affect request handling.
        }
    }

    private function finishRequest(
        ?ResponseInterface $response,
        ?RequestLifecycleScopeInterface $scope
    ): void {
        $failure = null;

        if ($response !== null) {
            try {
                $this->sendResponse($response);
            } catch (Throwable $exception) {
                $this->recordLifecycleException($scope, $exception);
                $failure = $exception;
            }
        }

        try {
            Debugger::shutdownHandler();
        } catch (Throwable $exception) {
            $this->recordLifecycleException($scope, $exception);
            $failure ??= $exception;
        }

        try {
            $this->session->close();
        } catch (Throwable $exception) {
            $this->recordLifecycleException($scope, $exception);
            $failure ??= $exception;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        try {
            $scope?->complete($response);
        } catch (Throwable) {
            // Lifecycle hooks must never affect request handling.
        }

        try {
            $this->handleAsync();
        } catch (Throwable $exception) {
            $failure ??= $exception;
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    public function createRequest(): Request
    {
        $request = $this->requestFactory->getHttpRequest();
        if (!($request instanceof Request)) {
            $request = new Request($request); // Wrap the PSR-7 request into our Request class
        }

        $previousRequestState = $this->session->getFlash(PreviousRequestState::FLASH_KEY);
        PreviousRequestState::restore($request, $previousRequestState);

        return $request;
    }

    protected function withCookies(ResponseInterface $response): ResponseInterface
    {
        $headers = App::cookieJar()->getHeaders();
        if (empty($headers)) {
            return $response;
        }
        return $response->withAddedHeader('Set-Cookie', $headers);
    }

    protected function handleException(Throwable $exception, Request $request): ResponseInterface
    {
        foreach ($this->exceptionHandlers as $handler) {
            if ($handler->handles($exception)) {
                return $handler->handle($exception, $request);
            }
        }

        // If no handler was found, throw the exception
        throw $exception;
    }

    protected function sendResponse(ResponseInterface $response): void
    {
        // Check if something is not already sent
        if (headers_sent()) {
            throw new RuntimeException('Headers were already sent. The response could not be emitted!');
        }

        // Status code
        http_response_code($response->getStatusCode());

        // Send headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        // Send body
        $stream = $response->getBody();

        if (!$stream->isReadable()) {
            return;
        }

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        while (!$stream->eof()) {
            echo $stream->read(8192);
            flush();
        }
    }
}
