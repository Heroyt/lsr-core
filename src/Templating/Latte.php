<?php

namespace Lsr\Core\Templating;

use Latte\Engine;
use Latte\Loaders\FileLoader;
use Latte\Loaders\StringLoader;
use Latte\Sandbox\SecurityPolicy;
use Lsr\Core\Controllers\TemplateParameters;
use Lsr\Exceptions\TemplateDoesNotExistException;

readonly class Latte
{

    public function __construct(
      private Engine $engine,
    ) {
        $sandbox = SecurityPolicy::createSafePolicy();
        $sandbox->allowTags(['svgIcon', 'link', 'getUrl', 'lang']);
        $sandbox->allowFilters($sandbox::ALL);
        $sandbox->allowFunctions(['sprintf', 'lang']);

        $this->engine->setPolicy($sandbox);
    }

    /**
     * Renders a view from a latte template
     *
     * @param  string  $template  Template name
     * @param  array<string, mixed>|TemplateParameters  $params  Template parameters
     *
     * @throws TemplateDoesNotExistException
     */
    public function view(string $template, array|TemplateParameters $params = []) : void {
        $this->engine->render($this->getTemplate($template), $params);
    }

    /**
     * Get latte template file path by template name
     *
     * @param  string  $name  Template file name
     *
     * @return string
     *
     * @throws TemplateDoesNotExistException
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
     * @param  string  $template  Template name
     * @param  array<string, mixed>|TemplateParameters  $params  Template parameters
     *
     * @return string Can be empty if $return is false
     * @throws TemplateDoesNotExistException
     */
    public function viewToString(string $template, array|TemplateParameters $params = []) : string {
        return $this->engine->renderToString($this->getTemplate($template), $params);
    }

    /**
     * Render template in sandbox mode
     *
     * @param  string  $template
     * @param  array<string,mixed>|TemplateParameters  $params
     *
     * @return void
     * @throws TemplateDoesNotExistException
     */
    public function sandbox(string $template, array|TemplateParameters $params) : void {
        $this->engine->setSandboxMode();
        $this->engine->render($this->getTemplate($template), $params);
        $this->engine->setSandboxMode(false);
    }

    /**
     * Render template in sandbox mode.
     *
     * @param  string  $template
     * @param  array<string,mixed>|TemplateParameters  $params
     *
     * @return string
     * @throws TemplateDoesNotExistException
     */
    public function sandboxToString(string $template, array|TemplateParameters $params) : string {
        $this->engine->setSandboxMode();
        $return = $this->engine->renderToString($this->getTemplate($template), $params);
        $this->engine->setSandboxMode(false);
        return $return;
    }

    /**
     * Render template from string in sandbox mode.
     *
     * @param  string  $latte
     * @param  array<string,mixed>|TemplateParameters  $params
     *
     * @return void
     */
    public function sandboxFromString(string $latte, array|TemplateParameters $params) : void {
        $this->engine->setSandboxMode();
        $this->engine->setLoader(new StringLoader);
        $this->engine->render($latte, $params);
        $this->engine->setLoader(new FileLoader);
        $this->engine->setSandboxMode(false);
    }

    /**
     * Render template from string in sandbox mode.
     *
     * @param  string  $latte
     * @param  array<string, mixed>|TemplateParameters  $params
     *
     * @return string
     */
    public function sandboxFromStringToString(string $latte, array|TemplateParameters $params) : string {
        $this->engine->setSandboxMode();
        $this->engine->setLoader(new StringLoader);
        $return = $this->engine->renderToString($latte, $params);
        $this->engine->setSandboxMode(false);
        $this->engine->setLoader(new FileLoader);
        return $return;
    }

}