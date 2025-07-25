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

use JsonException;
use Lsr\Core\App;
use Lsr\Core\Requests\Response;
use Lsr\Core\Routing\Middleware;
use Lsr\Core\Templating\Latte;
use Lsr\Dto\Notice;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Interfaces\ControllerInterface;
use Lsr\Interfaces\RequestInterface;
use Lsr\Interfaces\RouteInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

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

    /** @var Middleware[] */
    public array $middleware = [];
    /**
     * @var array<string, mixed> $params Parameters added to latte template
     */
    public TemplateParameters | array $params = [];
    /** @var App Injected property */
    public App $app;
    /** @var Latte Injected property */
    protected Latte $latte;
    /**
     * @var string $title Page name
     */
    protected string $title = '';

    /**
     * @var array<int,string|numeric> Parameters to replace description wildcards
     * @see sprintf()
     */
    protected array $descriptionParams = [];

    /**
     * @var array<int,string|numeric> Parameters to replace title wildcards
     * @see sprintf()
     */
    protected array $titleParams = [];

    /**
     * @var string $description Page description
     */
    protected string $description = '';
    protected RequestInterface $request;

    /**
     * Initialization function
     *
     * @param  RequestInterface  $request
     *
     * @version 1.0
     * @since   1.0
     */
    public function init(RequestInterface $request) : void {
        $this->request = $request;
        $this->params['page'] = $this;
        $this->params['app'] = $this->getApp();
        $this->params['request'] = $request;
        $this->params['errors'] = $request->getErrors();
        $this->params['notices'] = $request->getNotices();
        $this->params['flashMessages'] = $this->app->session->getFlashMessages();
    }

    public function getApp() : App {
        if (!isset($this->app)) {
            $this->app = App::getInstance();
        }
        return $this->app;
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
        return $this->getApp()->getAppName().
          (!empty($this->title) ?
            ' - '.sprintf(
                 lang($this->title, context: 'pageTitles'),
              ...$this->titleParams
            )
            : ''
          );
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
        return sprintf(lang($this->description, context: 'pageDescription'), ...$this->descriptionParams);
    }

    public function injectLatte(Latte $latte) : void {
        $this->latte = $latte;
    }

    public function injectApp(App $app) : void {
        $this->app = $app;
    }

    /**
     * @param  string  $template
     *
     * @return ResponseInterface
     * @throws JsonException
     * @throws TemplateDoesNotExistException
     */
    protected function view(string $template) : ResponseInterface {
        return $this->respond(
          $this->latte
            ->setLocale($this->app->translations->getLang())
            ->viewToString($template, $this->params)
        )
                    ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @param  string|array<string, mixed>|list<mixed>|object  $data
     * @param  int  $code
     * @param  string[]  $headers
     *
     * @return ResponseInterface
     */
    protected function respond(
      string | array | object $data,
      int                     $code = 200,
      array                   $headers = []
    ) : ResponseInterface {
        $response = new Response(new \Nyholm\Psr7\Response($code, $headers));

        if (is_string($data)) {
            return $response->withStringBody($data);
        }

        $acceptTypes = $this->getAcceptTypes($this->request);
        if (in_array('application/json', $acceptTypes)) {
            return $response->withJsonBody($data);
        }
        if (in_array('application/xml', $acceptTypes)) {
            return $response->withXmlBody($data);
        }

        // Default to JSON
        return $response->withJsonBody($data);
    }

    /**
     * @param  ServerRequestInterface  $request
     * @return string[]
     */
    protected function getAcceptTypes(ServerRequestInterface $request) : array {
        $types = [];
        foreach ($request->getHeader('Accept') as $value) {
            $types[] = strtolower(trim(explode(';', $value, 2)[0]));
        }
        return $types;
    }

    /**
     * @param  UriInterface|RouteInterface|string[]|string  $to  URL, Route, Route's name or path as an array
     * @param  RequestInterface|null  $from  Previous request
     * @param  int  $type  Redirect HTTP code
     * @return ResponseInterface
     */
    protected function redirect(
      UriInterface | RouteInterface | array | string $to,
      ?RequestInterface                              $from = null,
      int                                            $type = 302
    ) : ResponseInterface {
        return App::getInstance()->redirect($to, $from, $type);
    }

    protected function flashSuccess(string $message) : void {
        $this->app->session->flashSuccess($message);
    }

    protected function flashError(string $message) : void {
        $this->app->session->flashError($message);
    }

    protected function flashWarning(string $message) : void {
        $this->app->session->flashWarning($message);
    }

    protected function flashInfo(string $message) : void {
        $this->app->session->flashInfo($message);
    }

    protected function flashNotice(Notice $notice) : void {
        $this->app->session->flashNotice($notice);
    }

}
