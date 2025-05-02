<?php
declare(strict_types=1);

namespace Lsr\Core;

use Lsr\Caching\Cache;
use Lsr\Core\Attributes\MapRequest;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Response;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\Core\Routing\AliasRoute;
use Lsr\Core\Routing\Dispatcher;
use Lsr\Core\Routing\Exceptions\ModelNotFoundException as RouteModelNotFoundException;
use Lsr\Core\Routing\Middleware;
use Lsr\Core\Routing\Route;
use Lsr\Enums\RequestMethod;
use Lsr\Exceptions\RedirectException;
use Lsr\Helpers\Tools\Strings;
use Lsr\Interfaces\ControllerInterface;
use Lsr\Interfaces\RequestInterface;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Lsr\Orm\Model;
use Lsr\Serializer\Mapper;
use Nette\Caching\Cache as CacheParent;
use Nette\DI\MissingServiceException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use RuntimeException;
use Throwable;

class RouteHandler implements RequestHandlerInterface
{

    protected Route $route;

    public function __construct(
      protected readonly Cache  $cache,
      protected readonly Mapper $mapper,
    ) {}

    public function handle(ServerRequestInterface $request) : ResponseInterface {
        assert(isset($this->route) && $request instanceof Request);

        /** @var Middleware|false $middleware */
        $middleware = current($this->route->middleware);

        // No more middleware to process, call the handler
        if ($middleware === false) {
            // Handle route redirect (alias)
            if ($this->route instanceof AliasRoute) {
                $link = App::getLink($this->route->redirectTo->getPath());
                foreach ($request->params as $name => $value) {
                    $link = str_replace('{'.$name.'}', $value, $link);
                }
                return Response::create(
                  308,
                  ['Location' => $link]
                );
            }

            $handler = $this->route->getHandler();

            if (
              is_array($handler)
              && (is_object($handler[0]) || (is_string($handler[0]) && class_exists($handler[0])))
              && is_string($handler[1])
            ) {
                [$class, $func] = $handler;
                /** @var ControllerInterface $controller */
                $controller = is_object($class) ? $class : App::getContainer()->getByType($class);

                // Controller-wide middleware
                if (isset($controller->middleware) && is_array($controller->middleware) && count(
                    $controller->middleware
                  ) > 0) {
                    // Create a dispatcher
                    $dispatcher = new Dispatcher(
                    /** @phpstan-ignore argument.type */
                      array_merge(
                        $controller->middleware,
                        [
                          function (RequestInterface $request) use ($controller, $func) : ResponseInterface {
                              $controller->init($request);
                              $args = $this->getHandlerArgs($request);

                              $cookieHeaders = App::cookieJar()->getHeaders();
                              /** @var ResponseInterface $response */
                              $response = $controller->$func(...$args);
                              if (!empty($cookieHeaders)) {
                                  $response = $response->withAddedHeader('Set-Cookie', $cookieHeaders);
                              }
                              return $response;
                          },
                        ]
                      )
                    );
                    return $dispatcher->handle($request);
                }

                $controller->init($request);
                $args = $this->getHandlerArgs($request);

                $cookieHeaders = App::cookieJar()->getHeaders();
                /** @var ResponseInterface $response */
                $response = $controller->$func(...$args);
                if (!empty($cookieHeaders)) {
                    $response = $response->withAddedHeader('Set-Cookie', $cookieHeaders);
                }
                return $response;
            }

            $cookieHeaders = App::cookieJar()->getHeaders();
            /** @var ResponseInterface $response */
            $response = call_user_func($handler, $request);
            if (!empty($cookieHeaders)) {
                $response = $response->withAddedHeader('Set-Cookie', $cookieHeaders);
            }
            return $response;
        }

        // Iterate to the next middleware
        next($this->route->middleware);

        // Process route-wide middleware
        try {
            return $middleware->process($request, $this);
        } catch (RedirectException $e) {
            return App::getInstance()->redirect($e->url, $e->request, $e->getCode());
        }
    }


    /**
     * @param  RequestInterface  $request
     *
     * @return array<string, mixed>
     * @throws ValidationException
     * @throws Throwable
     */
    private function getHandlerArgs(RequestInterface $request) : array {
        /** @var array<string,array{optional:bool,type:string|class-string,nullable:bool,mapRequest:bool}> $args */
        $args = $this->cache->load(
          'route.'.$this->route->getMethod()->value.'.'.$this->route->getReadable().'.args',
          function () {
              /** @var array{0:class-string|object,1:string}|callable $handler */
              $handler = $this->route->getHandler();
              $reflection = is_array($handler) ?
                new ReflectionMethod($handler[0], $handler[1]) : // @phpstan-ignore-line
                new ReflectionFunction($handler); // @phpstan-ignore argument.type
              $arguments = $reflection->getParameters();
              $args = [];
              foreach ($arguments as $argument) {
                  $name = $argument->getName();
                  $optional = $argument->isOptional();

                  /** @var ReflectionType|\ReflectionUnionType|null $type */
                  $type = $argument->getType();

                  if ($type instanceof ReflectionNamedType) {
                      $args[$name] = [
                        'optional'   => $optional,
                        'union'      => false,
                        'type'       => $type->getName(),
                        'nullable'   => $type->allowsNull(),
                        'mapRequest' => !empty(
                        $argument->getAttributes(
                          MapRequest::class,
                          \ReflectionAttribute::IS_INSTANCEOF
                        )
                        ),
                      ];
                  }
                  elseif ($type instanceof \ReflectionUnionType) {
                      $subTypes = [];
                      foreach ($type->getTypes() as $subtype) {
                          if (!$subtype instanceof ReflectionNamedType && !$subtype->isBuiltin()) {
                              throw new RuntimeException(
                                sprintf(
                                  "Unsupported route handler method union type in %s(%s). Only built-in types, RequestInterface and Model classes are supported.",
                                  $this->handlerToString($this->route->getHandler()),
                                  $name
                                )
                              );
                          }
                          $subTypes[] = $subtype->getName();
                      }
                      $args[$name] = [
                        'optional'   => $optional,
                        'union'      => true,
                        'type'       => $subTypes,
                        'nullable'   => $type->allowsNull(),
                        'mapRequest' => !empty(
                        $argument->getAttributes(
                          MapRequest::class,
                          \ReflectionAttribute::IS_INSTANCEOF
                        )
                        ),
                      ];
                  }
                  else {
                      throw new RuntimeException(
                        sprintf(
                          "Unsupported route handler method type in %s(%s). Only built-in types, RequestInterface and Model classes are supported.",
                          $this->handlerToString($this->route->getHandler()),
                          $name
                        )
                      );
                  }
              }
              return $args;
          },
          [
            CacheParent::Expire => '1 days',
            CacheParent::Tags   => ['routes', 'core'],
          ]
        );

        $requestMapper = new RequestValidationMapper($this->mapper);
        if (!($request instanceof Request)) {
            $request = new Request($request);
        }
        $requestMapper->setRequest($request);

        $argsValues = [];
        foreach ($args as $name => $type) {
            if ($type['union']) {
                $value = $request->getParam($name);
                if (
                  (in_array('float', $type['type'], true) || in_array('double', $type['type']))
                  && is_numeric($value)
                ) {
                    $argsValues[$name] = (float) $value;
                    continue;
                }
                if (
                  (in_array('int', $type['type'], true) || in_array('integer', $type['type']))
                  && is_numeric($value)
                ) {
                    $argsValues[$name] = (int) $value;
                    continue;
                }
                if (
                  (in_array('bool', $type['type'], true) || in_array('boolean', $type['type']))
                  && (is_numeric($value) || in_array(strtolower($value), ['true', 'false']))
                ) {
                    $argsValues[$name] = is_numeric($value) ? ((int) $value) > 0 : strtolower($value) === 'true';
                    continue;
                }
                if (in_array('string', $type['type'], true)) {
                    $argsValues[$name] = (string) $value;
                    continue;
                }
                throw new RunTimeException(
                  sprintf(
                    "Unsupported route handler method type in %s(%s \$%s). Only built-in types, RequestInterface and Model classes are supported.",
                    $this->handlerToString($this->route->getHandler()),
                    $type['type'],
                    $name
                  )
                );
            }
            if (class_exists($type['type'])) {
                // Check for request
                $implements = class_implements($type['type']);
                if ($type['type'] === RequestInterface::class || isset($implements[RequestInterface::class])) {
                    $argsValues[$name] = $request;
                    continue;
                }

                // Check for model
                if (is_subclass_of($type['type'], Model::class)) {
                    // Find ID
                    $paramName = Strings::toCamelCase($name.'_id');
                    $id = $request->getParam($paramName);
                    if (!isset($id)) {
                        $id = $request->getParam(strtolower($paramName));
                    }
                    if (!isset($id)) {
                        $id = $request->getParam(strtolower($name));
                    }
                    if (!isset($id)) {
                        $id = $request->getParam('id');
                    }
                    if (!isset($id)) {
                        if ($type['optional']) {
                            continue;
                        }
                        throw new RuntimeException(
                          sprintf(
                            "Cannot instantiate Model for route. No ID route parameter. %s - argument: %s \$%s. Expecting parameter \"id\" or \"%s\".",
                            $this->route->getReadable(),
                            $type['type'],
                            $name,
                            $paramName
                          )
                        );
                    }
                    try {
                        $model = $type['type']::get((int) $id);
                    } catch (ModelNotFoundException $e) {
                        if (!$type['nullable']) {
                            throw new RouteModelNotFoundException(
                                        sprintf(
                                          "Cannot instantiate Model for route. Model not found. %s - argument: %s \$%s.",
                                          $this->route->getReadable(),
                                          $type['type'],
                                          $name
                                        ),
                              previous: $e
                            );
                        }
                        $model = null;
                    }
                    $argsValues[$name] = $model;
                    continue;
                }

                // Check for request mapping
                if ($type['mapRequest']) {
                    $argsValues[$name] = $request->getType() === RequestMethod::GET ?
                      $requestMapper->mapQueryToObject($type['type']) :
                      $requestMapper->mapBodyToObject($type['type']);
                }

                // Try to get class from DI
                try {
                    $class = App::getContainer()->getByType($type['type']);
                } catch (MissingServiceException $e) {
                    if (!$type['nullable']) {
                        throw $e;
                    }
                    $class = null;
                }
                $argsValues[$name] = $class;
                continue;
            }

            // Basic types
            $argsValues[$name] = match ($type['type']) {
                'string'          => (string) $request->getParam($name),
                'integer', 'int'  => (int) $request->getParam($name),
                'double', 'float' => (float) $request->getParam($name),
                'boolean', 'bool' => (bool) $request->getParam($name),
                default           => throw new RunTimeException(
                  sprintf(
                    "Unsupported route handler method type in %s(%s \$%s). Only built-in types, RequestInterface and Model classes are supported.",
                    $this->handlerToString($this->route->getHandler()),
                    $type['type'],
                    $name
                  )
                ),
            };
        }

        return $argsValues;
    }

    /**
     * @param  array{0:class-string|object, 1:string}|callable  $handler
     * @return string
     */
    private function handlerToString(array | callable $handler) : string {
        if (is_array($handler)) {
            return implode('::', $handler);
        }
        if (is_string($handler)) {
            return $handler;
        }
        return 'callable';
    }

    public function setRoute(Route $route) : RouteHandler {
        $this->route = $route;
        return $this;
    }
}