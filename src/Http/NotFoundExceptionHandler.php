<?php
declare(strict_types=1);

namespace Lsr\Core\Http;

use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Exceptions\RouteNotFoundException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Exceptions\ModelNotFoundException as RoutingModelNotFoundException;
use Lsr\Interfaces\ResponseFactoryInterface;
use Lsr\Orm\Exceptions\ModelNotFoundException as OrmModelNotFoundException;
use Psr\Http\Message\ResponseInterface;

readonly class NotFoundExceptionHandler implements ExceptionHandlerInterface
{

    public function __construct(
      private ResponseFactoryInterface $responseFactory,
    ) {}

    /**
     * @inheritDoc
     */
    public function handles(\Throwable $exception) : bool {
        return $exception instanceof RouteNotFoundException
          || $exception instanceof OrmModelNotFoundException
          || $exception instanceof RoutingModelNotFoundException;
    }

    /**
     * @inheritDoc
     */
    public function handle(\Throwable $exception, Request $request) : ResponseInterface {
        $acceptTypes = array_filter(
          array_map(
            static fn(string $header) => strtolower(trim(explode(';', $header, 2)[0])),
            $request->getHeader('Accept')
          )
        );

        if (in_array('application/json', $acceptTypes, true)) {
            try {
                $data = json_encode(
                  new ErrorResponse(
                               'Oops, I cannot find this.',
                               ErrorType::NOT_FOUND,
                    detail   : $exception->getMessage(),
                    exception: $exception
                  ),
                  JSON_THROW_ON_ERROR
                );
            } catch (\JsonException) {
                $data = '{"type":"resource_not_found_error","title":"Oops, I cannot find this."}';
            }
            return $this->responseFactory->createFullResponse(
              404,
              ['Content-Type' => 'application/json'],
              $data
            );
        }

        if (in_array('text/html', $acceptTypes, true)) {
            return $this->responseFactory->createFullResponse(
              404,
              ['Content-Type' => 'text/html'],
              <<<HTML
                <!doctype html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                     <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
                     <meta http-equiv="X-UA-Compatible" content="ie=edge">
                     <title>Not Found</title>
                </head>
                <body>
                  <h1>Oops, I cannot find this.</h1>
                  <p>{$exception->getMessage()}</p>
                </body>
                </html>
                HTML
            );
        }

        return $this->responseFactory->createFullResponse(
          404,
          ['Content-Type' => 'text/plain'],
          'Oops, I cannot find this. - '.$exception->getMessage()
        );
    }
}