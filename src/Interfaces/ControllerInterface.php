<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Core\Interfaces;

use Lsr\Core\Requests\Interfaces\RequestInterface;

interface ControllerInterface
{

	/**
	 * Initialization function
	 *
	 * @param RequestInterface $request
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public function init(RequestInterface $request) : void;

}