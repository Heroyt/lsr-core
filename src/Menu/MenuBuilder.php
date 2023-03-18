<?php

namespace Lsr\Core\Menu;

use Lsr\Core\App;
use Lsr\Core\Routing\Router;
use Lsr\Exceptions\FileException;
use Lsr\Interfaces\AuthInterface;
use Nette\DI\MissingServiceException;

class MenuBuilder
{

	public function __construct(
		private readonly Router $router
	) {
	}

	/**
	 * @param string $type
	 *
	 * @return MenuItem[]
	 * @throws FileException
	 */
	public function getMenu(string $type = 'menu') : array {
		if (!file_exists(ROOT.'config/nav/'.$type.'.php')) {
			throw new FileException('Menu configuration file "'.$type.'.php" does not exist.');
		}
		$config = require ROOT.'config/nav/'.$type.'.php';
		$menu = [];
		foreach ($config as $item) {
			if (!self::checkAccess($item)) {
				continue;
			}
			if (isset($item['route'])) {
				$path = $this->router->getRouteByName($item['route'])?->getPath();
			}
			else {
				$path = $item['path'] ?? ['E404'];
			}
			$menuItem = new MenuItem(name: $item['name'], icon: $item['icon'] ?? '', path: $path);
			foreach ($item['children'] ?? [] as $child) {
				if (!self::checkAccess($child)) {
					continue;
				}
				if (isset($child['route'])) {
					$path = $this->router->getRouteByName($child['route'])?->getPath();
				}
				else {
					$path = $child['path'] ?? ['E404'];
				}
				$menuItem->children[] = new MenuItem(name: $child['name'], icon: $child['icon'] ?? '', path: $path);
			}
			$menu[] = $menuItem;
		}
		return $menu;
	}

	/**
	 * @param array{
	 *   access:string[]|null|string,
	 *   loggedInOnly:bool|null,
	 *   loggedOutOnly:bool|null
	 * } $item
	 *
	 * @return bool
	 */
	private static function checkAccess(array $item) : bool {
		try {
			$auth = App::getServiceByType(AuthInterface::class);
		} catch (MissingServiceException) {
			return false;
		}

		if (isset($item['loggedInOnly']) && $item['loggedInOnly'] && !$auth->loggedIn()) {
			return false;
		}
		if (isset($item['loggedOutOnly']) && $item['loggedOutOnly'] && $auth->loggedIn()) {
			return false;
		}
		if (!isset($item['access'])) {
			return true;
		}

		if (is_string($item['access'])) {
			$access = [$item['access']];
		}
		else {
			$access = $item['access'];
		}

		foreach ($access as $right) {
			if (!$auth->hasRight($right)) {
				return false;
			}
		}

		return true;
	}

}