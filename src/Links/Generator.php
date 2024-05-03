<?php

namespace Lsr\Core\Links;

use Lsr\Core\App;
use Lsr\Core\Routing\Router;
use Nette\Http\Url;

readonly class Generator
{

	private Url  $baseUrl;
	private bool $prettyUrl;

	public function __construct(
		private Router $router
	) {
		$this->baseUrl = App::getUrl(true);
		$this->prettyUrl = App::isPrettyUrl();
	}

	/**
	 * @param array<array<string|int,string>|string> ...$request
	 *
	 * @return string
	 */
	public function getLink(...$request) : string {
		return (string) $this->getLinkObject(...$request);
	}

	/**
	 * @param array<array<string|int,string>|string> ...$request
	 *
	 * @return Url
	 */
	public function getLinkObject(array|string ...$request) : Url {
		$url = clone $this->baseUrl;

		$count = count($request);
		if ($count === 1) {
			if (is_string($request[0])) {
				// Try to get route by name
				$route = $this->router->getRouteByName($request[0]);
				if (isset($route)) {
					$path = $route->getPath();
					$this->buildUrlFromPath($url, $path);
					return $url;
				}

				// Route is given as a string
				$this->buildUrlFromPath($url, explode('/', $request[0]));
				return $url;
			}
			if (is_array($request[0])) {
				/** @var string[] $path */
				$path = array_filter($request[0], 'is_int', ARRAY_FILTER_USE_KEY);
				/** @var array<string,string> $query */
				$query = array_filter($request[0], 'is_string', ARRAY_FILTER_USE_KEY);

				$url->setQuery($query);
				$this->buildUrlFromPath($url, $path);
			}
			return $url;
		}

		if ($count > 1) {
			$this->buildUrlFromPath($url, $request);
		}

		return $url;
	}

	/**
	 * @param Url      $url
	 * @param string[] $path
	 *
	 * @return void
	 */
	private function buildUrlFromPath(Url $url, array $path) : void {
		// Check validity
		foreach ($path as $part) {
			if (preg_match('/\{([a-zA-Z\d]+)}/', $part, $matches) === 1) {
				throw new \RuntimeException('Cannot build parametrized URL if the parameter is not provided. '.implode('/', $path));
			}
		}
		if ($this->prettyUrl) {
			$url->setPath(implode('/', $path));
			return;
		}
		$url->setQueryParameter('p', $path);
	}

}