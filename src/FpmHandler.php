<?php
declare(strict_types=1);

namespace Lsr\Core;

use Lsr\Core\Http\AsyncHandlerInterface;
use Lsr\Core\Http\ExceptionHandlerInterface;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\DispatchBreakException;
use Lsr\Interfaces\RequestFactoryInterface;
use Lsr\Interfaces\RequestInterface;
use Lsr\Interfaces\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;
use Tracy\Debugger;

readonly class FpmHandler
{

    /**
     * @param  ExceptionHandlerInterface[]  $exceptionHandlers  Exceptions are handled by the first valid handler.
     * @param  AsyncHandlerInterface[]  $asyncHandlers  Handlers that are run after the response is sent.
     */
    public function __construct(
      protected RequestFactoryInterface $requestFactory,
      protected SessionInterface        $session,
      protected array                   $exceptionHandlers = [],
      protected array                   $asyncHandlers = [],
    ) {
        // Validate that all handlers are of the correct type
        assert(array_all($this->exceptionHandlers, static fn($val) => $val instanceof ExceptionHandlerInterface));
        assert(array_all($this->asyncHandlers, static fn($val) => $val instanceof AsyncHandlerInterface));
    }

    protected function handleAsync() : void {
        foreach ($this->asyncHandlers as $handler) {
            $handler->run();
        }
    }

    public function run() : void {
        $app = App::getInstance();

        // Parse request
        $request = $this->createRequest();

        try {
            $app->setRequest($request);

            $response = $app->run();
        } catch (DispatchBreakException $e) {
            $response = $e->getResponse();
        } catch (Throwable $e) {
            $response = $this->handleException($e, $request);
        } finally {
            $this->sendResponse($response);
            Debugger::shutdownHandler();
            $this->session->close();
            fastcgi_finish_request();
            $this->handleAsync();
        }
    }

    public function createRequest() : Request {
        $request = $this->requestFactory->getHttpRequest();
        if (!($request instanceof Request)) {
            $request = new Request($request); // Wrap the PSR-7 request into our Request class
        }

        /** @var string|null $previousRequest */
        $previousRequest = $this->session->getFlash('fromRequest');
        if ($previousRequest !== null) {
            $previousRequest = unserialize($previousRequest, ['allowed_classes' => true,]);
            if ($previousRequest instanceof RequestInterface) {
                $request->setPreviousRequest($previousRequest);
            }
        }

        return $request;
    }

    protected function handleException(Throwable $exception, Request $request) : ResponseInterface {
        foreach ($this->exceptionHandlers as $handler) {
            if ($handler->handles($exception)) {
                return $handler->handle($exception, $request);
            }
        }

        // If no handler was found, throw the exception
        throw $exception;
    }

    protected function sendResponse(ResponseInterface $response) : void {
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