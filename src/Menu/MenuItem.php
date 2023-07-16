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
	 * @param string $name
	 * @param string $icon
	 * @param array<string|int> $path
	 * @param MenuItem[] $children
	 * @param int $order
	 */
	public function __construct(
		public string $name = '',
		public string $icon = '',
		public array  $path = [],
		public array  $children = [],
		public int    $order = 0,
	) {
		$this->url = App::getLink($this->path);
		$this->active = Router::comparePaths($this->path);
	}
}