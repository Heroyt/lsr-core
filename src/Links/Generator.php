<?php
/** @noinspection PhpUndefinedClassInspection */

namespace Lsr\Core\Links;

use Lsr\Core\App;
use Lsr\Core\Routing\Router;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\UriInterface;
use RuntimeException;

readonly class Generator
{

    private UriInterface $baseUrl;
    private bool $prettyUrl;

    /**
     * @param  LinkModifier[]  $modifiers
     */
    public function __construct(
      protected Router $router,
      App              $app,
      protected array  $modifiers = [],
    ) {
        $this->baseUrl = $app->getBaseUrlObject();
        $this->prettyUrl = App::isPrettyUrl();
    }

    /**
     * @param  LinkArray|LinkArray[]  ...$request
     *
     * @return string
     */
    public function getLink(array | string ...$request) : string {
        $link = $this->getLinkObject(...$request);
        return $this->formatLocalLink($link);
    }

    /**
     * @param  LinkArray|LinkArray[]  ...$request
     *
     * @return string
     */
    public function getAbsoluteLink(array | string ...$request) : string {
        return (string) $this->getLinkObject(...$request);
    }

    /**
     * @param  LinkArray|LinkArray[]  ...$request
     *
     * @return UriInterface
     */
    public function getLinkObject(array | string ...$request) : UriInterface {
        $count = count($request);
        if ($count === 1) {
            /** @var LinkArray|string $request */
            $request = $request[0];
            if (is_string($request)) {
                if ($this->isAbsoluteUrl($request)) {
                    return new Uri($request);
                }

                // Try to get route by name
                $route = $this->router->getRouteByName($request);
                if (isset($route)) {
                    $path = $route->getPath();

                    // Apply modifiers
                    foreach ($this->modifiers as $modifier) {
                        $path = $modifier->modifyLinkPath($path);
                    }

                    return $this->buildUrlFromPath($path);
                }

                // Route is given as a string
                return $this->buildUrlFromPath(explode('/', $request));
            }

            // Apply modifiers
            foreach ($this->modifiers as $modifier) {
                $request = $modifier->modifyLinkPath($request);
            }

            /** @var string[] $path */
            $path = array_filter($request, 'is_int', ARRAY_FILTER_USE_KEY);
            /** @var array<string,string> $query */
            $query = array_filter($request, 'is_string', ARRAY_FILTER_USE_KEY);

            return $this->buildUrlFromPath($path)->withQuery(http_build_query($query));
        }

        if ($count > 1) {
            // Apply modifiers
            foreach ($this->modifiers as $modifier) {
                /** @phpstan-ignore argument.type */
                $request = $modifier->modifyLinkPath($request);
            }
            // @phpstan-ignore-next-line
            return $this->buildUrlFromPath($request);
        }

        return $this->baseUrl;
    }

    private function formatLocalLink(UriInterface $link) : string {
        if (!$this->isLocalUri($link)) {
            return (string) $link;
        }

        $path = $link->getPath();
        if ($path === '') {
            $path = '/';
        }

        $query = $link->getQuery();
        if ($query !== '') {
            $path .= '?'.$query;
        }

        $fragment = $link->getFragment();
        if ($fragment !== '') {
            $path .= '#'.$fragment;
        }

        return $path;
    }

    private function isAbsoluteUrl(string $link) : bool {
        return preg_match('#^[a-z][a-z0-9+.-]*://#i', $link) === 1;
    }

    private function isLocalUri(UriInterface $link) : bool {
        $host = $link->getHost();
        if ($host === '') {
            return true;
        }

        return strcasecmp($host, $this->baseUrl->getHost()) === 0
            && $link->getPort() === $this->baseUrl->getPort();
    }

    /**
     * @param  LinkArray  $path
     *
     * @return UriInterface
     */
    private function buildUrlFromPath(array $path) : UriInterface {
        // Check validity
        foreach ($path as $part) {
            $part = (string) $part;
            if (preg_match('/\{([a-zA-Z\d]+)}/', $part) === 1) {
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
