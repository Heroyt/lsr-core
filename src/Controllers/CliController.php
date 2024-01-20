<?php
/**
 * @file      Page.php
 * @brief     Core\Page class
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 *
 * @defgroup  Pages Pages
 * @brief     All page classes
 */

namespace Lsr\Core\Controllers;


use Lsr\Helpers\Cli\CliHelper;
use Lsr\Interfaces\ControllerInterface;
use Lsr\Interfaces\RequestInterface;

/**
 * @class   Page
 * @brief   Abstract Page class that specifies all basic functionality for other Pages
 *
 * @package Core
 * @ingroup Pages
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
abstract class CliController implements ControllerInterface
{

	protected RequestInterface $request;

	/**
	 * Initialization function
	 *
	 * @param RequestInterface $request
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public function init(RequestInterface $request) : void {
		$this->request = $request;
	}

	/**
	 * Print a message to STDERR
	 *
	 * @param string $message
	 * @param mixed  ...$args
	 *
	 * @return void
	 */
	public function errorPrint(string $message, ...$args) : void {
		CliHelper::printErrorMessage($message, ...$args);
	}

}