<?php

namespace Lsr\Core\Links;

use Lsr\Core\App;
use Lsr\Core\Routing\Router;
use Psr\Http\Message\UriInterface;
use RuntimeException;

readonly class Generator
{

    private UriInterface $baseUrl;
    private bool $prettyUrl;

    public function __construct(
      private Router $router,
      App            $app
    ) {
        $this->baseUrl = $app->getBaseUrlObject();
        $this->prettyUrl = App::isPrettyUrl();
    }

    /**
     * @param  array<array<string|int,string>|string>  ...$request
     *
     * @return string
     */
    public function getLink(...$request) : string {
        return (string) $this->getLinkObject(...$request);
    }

    /**
     * @param  array<array<string|int,string>|string>  ...$request
     *
     * @return UriInterface
     */
    public function getLinkObject(array | string ...$request) : UriInterface {
        $count = count($request);
        if ($count === 1) {
            if (is_string($request[0])) {
                // Try to get route by name
                $route = $this->router->getRouteByName($request[0]);
                if (isset($route)) {
                    $path = $route->getPath();
                    return $this->buildUrlFromPath($path);
                }

                // Route is given as a string
                return $this->buildUrlFromPath(explode('/', $request[0]));
            }

            /** @var string[] $path */
            $path = array_filter($request[0], 'is_int', ARRAY_FILTER_USE_KEY);
            /** @var array<string,string> $query */
            $query = array_filter($request[0], 'is_string', ARRAY_FILTER_USE_KEY);

            return $this->buildUrlFromPath($path)->withQuery(http_build_query($query));
        }

        if ($count > 1) {
            // @phpstan-ignore-next-line
            return $this->buildUrlFromPath($request);
        }

        return $this->baseUrl;
    }

    /**
     * @param  string[]  $path
     *
     * @return UriInterface
     */
    private function buildUrlFromPath(array $path) : UriInterface {
        // Check validity
        foreach ($path as $part) {
            if (preg_match('/\{([a-zA-Z\d]+)}/', $part, $matches) === 1) {
                throw new RuntimeException(
                  'Cannot build parametrized URL if the parameter is not provided. '.implode('/', $path)
                );
            }
        }
        if ($this->prettyUrl) {
            return $this->baseUrl->withPath(implode('/', $path));
        }
        return $this->baseUrl->withQuery(http_build_query(['p' => $path]));
    }

}