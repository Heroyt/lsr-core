<?php

namespace Lsr\Core\Templating;

use Latte\Engine;
use Lsr\Exceptions\TemplateDoesNotExistException;

class Latte
{

	public function __construct(private readonly Engine $engine) {
	}

	/**
	 * Renders a view from a latte template
	 *
	 * @param string $template Template name
	 * @param array  $params   Template parameters
	 *
	 * @throws TemplateDoesNotExistException
	 */
	public function view(string $template, array $params = []) : void {
		$this->engine->render($this->getTemplate($template), $params);
	}

	/**
	 * Get latte template file path by template name
	 *
	 * @param string $name Template file name
	 *
	 * @return string
	 *
	 * @throws TemplateDoesNotExistException()
	 *
	 * @version 0.1
	 * @since   0.1
	 */
	public function getTemplate(string $name) : string {
		if (!file_exists(TEMPLATE_DIR.$name.'.latte')) {
			throw new TemplateDoesNotExistException('Cannot find latte template file ('.$name.')');
		}
		return TEMPLATE_DIR.$name.'.latte';
	}

	/**
	 * Renders a view from a latte template
	 *
	 * @param string $template Template name
	 * @param array  $params   Template parameters
	 *
	 * @return string Can be empty if $return is false
	 * @throws TemplateDoesNotExistException
	 */
	public function viewToString(string $template, array $params = []) : string {
		return $this->engine->renderToString($this->getTemplate($template), $params);
	}

}