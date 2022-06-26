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

namespace Lsr\Core;


use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
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
abstract class Controller implements ControllerInterface
{

	public array $middleware = [];
	/**
	 * @var array $params Parameters added to latte template
	 */
	public array $params = [];
	/**
	 * @var string $title Page name
	 */
	protected string $title;
	/**
	 * @var string $description Page description
	 */
	protected string           $description;
	protected RequestInterface $request;

	public function __construct(protected Latte $latte) {
	}

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
		$this->params['page'] = $this;
		$this->params['request'] = $request;
		$this->params['errors'] = $request->errors;
		$this->params['notices'] = $request->notices;
	}

	/**
	 * Getter method for page title
	 *
	 * @return string
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public function getTitle() : string {
		return Constants::SITE_NAME.(!empty($this->title) ? ' - '.lang($this->title, context: 'pageTitles') : '');
	}

	/**
	 * Getter method for page description
	 *
	 * @return string
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public function getDescription() : string {
		return lang($this->description, context: 'pageDescription');
	}

	/**
	 * @param string|array|object $data
	 * @param int                 $code
	 * @param string[]            $headers
	 *
	 * @return never
	 */
	public function respond(string|array|object $data, int $code = 200, array $headers = []) : never {
		http_response_code($code);

		$dataNormalized = '';
		if (is_string($data)) {
			$dataNormalized = $data;
		}
		else if (is_array($data) || is_object($data)) {
			$dataNormalized = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			$headers['Content-Type'] = 'application/json';
		}


		foreach ($headers as $name => $value) {
			header($name.': '.$value);
		}

		echo $dataNormalized;
		exit;
	}

	/**
	 * @param string $template
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 */
	protected function view(string $template) : void {
		$this->latte->view($template, $this->params);
	}

}
