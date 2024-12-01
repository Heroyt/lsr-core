<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Core\Menu;

use Lsr\Core\App;
use Lsr\Core\Routing\Router;

class MenuItem
{

    public bool $active = false;
    public string $url = '';

    /**
     * @param  string  $name
     * @param  string  $icon
     * @param  array<string|int, string>  $path
     * @param  MenuItem[]  $children
     * @param  int  $order
     */
    public function __construct(
      public string $name = '',
      public string $icon = '',
      public array  $path = [],
      public array  $children = [],
      public int    $order = 0,
    ) {
        $this->url = App::getLink($this->path);
        $this->checkActive();
    }

    /**
     * Check if this menu item is currently active
     *
     * @return bool
     */
    public function checkActive() : bool {
        $activePath = App::getInstance()->getRequest()->getPath();
        $this->active = Router::comparePaths(array_values($this->path), $activePath);
        foreach ($this->children as $child) {
            $this->active = $this->active || Router::comparePaths($child->path, $activePath);
        }
        return $this->active;
    }

    /**
     * If menu items are serialized, it should still check if it is active
     *
     * @return void
     */
    public function __wakeup() : void {
        $this->checkActive();
    }
}