<?php

declare(strict_types=1);
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Core\Menu;

use InvalidArgumentException;
use Lsr\Core\App;
use Lsr\Core\Links\Generator;
use Lsr\Core\Routing\Domain\Hostname;
use Lsr\Core\Routing\Interfaces\DomainRouteInterface;
use Lsr\Core\Routing\Router;

class MenuItem
{
    public bool $active = false;
    public string $url = '';
    public ?string $routeName = null;

    /**
     * @param  string  $name
     * @param  string  $icon
     * @param  array<string|int, string>  $path
     * @param  MenuItem[]  $children
     * @param  int  $order
     * @param  string|null  $routeName Named destination, retaining its host constraint
     */
    public function __construct(
        public string $name = '',
        public string $icon = '',
        public array  $path = [],
        public array  $children = [],
        public int    $order = 0,
        ?string $routeName = null,
    ) {
        $this->routeName = $routeName;
        if ($this->routeName === null) {
            $this->url = App::getLink($this->path);
        }
        $this->checkActive();
    }

    /**
     * Check if this menu item is currently active
     *
     * @return bool
     */
    public function checkActive(): bool {
        $app = App::getInstance();
        $request = $app->getRequest();
        $activePath = $request->getPath();
        $domain = null;
        if ($this->routeName !== null) {
            /** @var Generator $generator */
            $generator = App::getService('links.generator');
            $this->url = $generator->getLink($this->routeName);
            $route = $app->router->getRouteByName($this->routeName);
            $domain = $route instanceof DomainRouteInterface ? $route->getDomain() : null;
        }
        $host = $request->getUri()->getHost();
        try {
            $domainMatches = $domain === null || ($host !== '' && Hostname::normalize($host) === $domain);
        } catch (InvalidArgumentException) {
            // URI reg-names outside the routing hostname grammar cannot match a constrained route.
            $domainMatches = false;
        }
        $this->active = $domainMatches && Router::comparePaths(array_values($this->path), $activePath);
        foreach ($this->children as $child) {
            $childActive = $child->checkActive();
            $this->active = $this->active || $childActive;
        }
        return $this->active;
    }

    /**
     * If menu items are serialized, it should still check if it is active
     *
     * @return void
     */
    public function __wakeup(): void {
        $this->checkActive();
    }
}
