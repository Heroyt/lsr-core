<?php
/**
 * @file      App.php
 * @brief     Core\App class
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */


namespace Lsr\Core;

use Gettext\Languages\Language;
use Lsr\Core\DataObjects\PageInfoDto;
use Lsr\Core\Exceptions\InvalidLanguageException;
use Lsr\Core\Links\Generator;
use Lsr\Core\Menu\MenuBuilder;
use Lsr\Core\Menu\MenuItem;
use Lsr\Core\Requests\Exceptions\RouteNotFoundException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Response;
use Lsr\Core\Routing\Route;
use Lsr\Core\Routing\Router;
use Lsr\Exceptions\FileException;
use Lsr\Helpers\Tools\Timer;
use Lsr\Interfaces\CookieJarInterface;
use Lsr\Interfaces\RequestFactoryInterface;
use Lsr\Interfaces\RequestInterface;
use Lsr\Interfaces\RouteInterface;
use Lsr\Interfaces\SessionInterface;
use Lsr\Logging\Logger;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\ExtensionsExtension;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use ReflectionException;
use RuntimeException;

/**
 * @class   App
 * @brief   App class containing all global getters and setters for app-wide options
 *
 * @package Core
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
class App
{
    /**
     * @var bool $prettyUrl
     * @brief If app should use a SEO-friendly pretty url
     */
    protected static bool $prettyUrl = false;
    protected static Container $container;
    protected static App $instance;
    /**
     * @var string
     */
    protected mixed $timezone;
    /** @var RequestInterface $request Current request object */
    protected RequestInterface $request;
    protected ?RouteInterface $route;
    protected Logger $logger;
    protected ?CookieJarInterface $cookieJar = null;

    /**
     * @throws ReflectionException
     */
    public function __construct(
      public readonly Router           $router,
      public readonly RouteHandler $routeHandler,
      public readonly SessionInterface $session,
      public readonly Config           $config,
      public readonly Translations     $translations,
    ) {
        self::$instance = $this;
        $router->setup();
    }

    /**
     * The ability to call all methods statically to preserve backwards compatibility.
     *
     * @param  string  $name
     * @param  array<int|string, mixed>  $arguments
     *
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments) {
        return self::getInstance()->{$name}(...$arguments);
    }

    public static function getInstance() : App {
        if (!isset(self::$instance)) {
            // @phpstan-ignore-next-line
            self::$instance = self::getService('app');
        }
        // @phpstan-ignore-next-line
        return self::$instance;
    }

    /**
     * Gets the service object by name.
     *
     * @param  string  $name
     *
     * @return object
     */
    public static function getService(string $name) : object {
        /** @phpstan-ignore return.type */
        return self::getContainer()->getService($name);
    }

    /**
     * @return Container
     */
    public static function getContainer() : Container {
        return self::$container;
    }

    /**
     * Set up a DI container
     *
     * @return void
     */
    public static function setupDi() : void {
        if (isset(self::$container)) {
            return;
        }
        Timer::start('core.setup.di');
        $loader = new ContainerLoader(TMP_DIR.'di/');
        /** @var class-string<Container> $class */
        $class = $loader->load(
          function (Compiler $compiler) {
              $compiler->addExtension('extensions', new ExtensionsExtension());
              /** @var string[] $configs */
              $configs = require ROOT.'config/services.php';
              // This will load all found services.neon files in the whole application that are cached in one PHP file
              foreach ($configs as $config) {
                  $compiler->loadConfig($config);
              }
          }
        );
        self::$container = new $class;
        Timer::stop('core.setup.di');
    }

    /**
     * Set pretty url to false
     *
     * @version 1.0
     * @since   1.0
     */
    public static function uglyUrl() : void {
        self::$prettyUrl = false;
    }

    /**
     * Set pretty url to true
     *
     * @version 1.0
     * @since   1.0
     */
    public static function prettyUrl() : void {
        self::$prettyUrl = true;
    }

    public static function sendResponse(ResponseInterface $response) : never {
        // Check if something is not already sent
        if (headers_sent()) {
            throw new RuntimeException('Headers were already sent. The response could not be emitted!');
        }

        // Status code
        http_response_code($response->getStatusCode());

        // Send headers
        foreach ($response->getHeaders() as $name => $values) {
            header(sprintf('%s: %s', $name, $response->getHeaderLine($name)), false);
        }

        // Send body
        $stream = $response->getBody();

        if (!$stream->isReadable()) {
            exit;
        }

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        while (!$stream->eof()) {
            echo $stream->read(8192);
        }
        exit;
    }

    /**
     * Get url to request location
     *
     * @param  array<string|int, string>  $request  request array
     *                                           * Ex: ['user', 'login', 'view' => 1, 'type' => 'company']:
     *   http(s)://host.cz/user/login?view=1&type=company
     *
     * @return UriInterface
     * @warning Should use the new \Lsr\Core\Links\Generator class to generate links
     * @see     Generator
     *
     * @version 1.0
     * @since   1.0
     */
    public static function getLinkObject(array $request = []) : UriInterface {
        /** @var Generator $generator */
        $generator = self::getService('links.generator');
        return $generator->getLinkObject($request);
    }

    /**
     * Get prettyUrl
     *
     * @return bool
     *
     * @version 1.0
     * @since   1.0
     */
    public static function isPrettyUrl() : bool {
        return self::$prettyUrl;
    }

    /**
     * @param  string  $type
     *
     * @return MenuItem[]
     * @throws FileException
     */
    public static function getMenu(string $type = 'menu') : array {
        /** @var MenuBuilder $menuBuilder */
        $menuBuilder = self::getService('menu.builder');
        return $menuBuilder->getMenu($type);
    }

    /**
     * Resolves service by type.
     *
     * @template T of object
     *
     * @param  class-string<T>  $type
     *
     * @return T|null
     */
    public static function getServiceByType(string $type) : null | object {
        /** @var T|null $service */
        $service = self::getContainer()->getByType($type);
        return $service;
    }

    /**
     * Resolves service by type.
     *
     * @template T of object
     *
     * @param  class-string<T>  $type
     *
     * @return T[]
     */
    public static function findServicesByType(string $type) : array {
        $service = self::getContainer()->findByType($type);
        /** @var T[] $services */
        $services = array_map([static::class, 'getService'], $service);
        return $services;
    }

    public static function cookieJar() : CookieJarInterface {
        $app = static::getInstance();
        if ($app->cookieJar === null) {
            $app->cookieJar = CookieJar::fromRequest($app->getRequest());
        }
        return $app->cookieJar;
    }

    /**
     * Get the request array
     *
     * @return RequestInterface
     *
     * @version 1.0
     * @since   1.0
     */
    public function getRequest() : RequestInterface {
        if (!isset($this->request)) {
            $request = $this::getServiceByType(RequestFactoryInterface::class)?->getHttpRequest();
            if ($request === null) {
                throw new RuntimeException('RequestFactoryInterface is not registered in the DI container.');
            }
            $this->request = $request;

            if (isset($this->session)) {
                /** @var string|null $previousRequest */
                $previousRequest = $this->session->getFlash('fromRequest');
                if (isset($previousRequest)) {
                    /** @var Request|false $previousRequest */
                    $previousRequest = unserialize($previousRequest, ['allowed_classes' => true,]);
                    if ($previousRequest instanceof RequestInterface) {
                        $this->request->setPreviousRequest($previousRequest);
                    }
                }
            }
        }
        return $this->request;
    }

    public function setRequest(RequestInterface $request) : void {
        $this->request = $request;
        $this->route = null;
        $this->cookieJar = null;
    }

    /**
     * Check if the language exists
     *
     * @param  string  $language
     *
     * @return bool
     */
    protected static function isValidLanguage(string $language) : bool {
        return Language::getById($language) !== null;
    }

    /**
     * @return string
     */
    public function getTimezone() : string {
        if (empty($this->timezone)) {
            $this->timezone = (string) ($this->config->getConfig('General')['TIMEZONE'] ?? 'Europe/Prague');
        }
        return $this->timezone;
    }

    /**
     * Get parsed config.ini file
     *
     * @return array<string,array<string,string|numeric>|numeric|string>
     *
     * @deprecated Use DI for loading config instead
     */
    public static function getConfig() : array {
        return self::getInstance()->config->getConfig();
    }

    /**
     * @param  bool  $returnObjects
     *
     * @return array<string, string|Language|null>
     */
    public function getSupportedLanguages(bool $returnObjects = false) : array {
        $supported = $this->translations->supportedLanguages;

        if ($returnObjects) {
            $return = [];
            foreach ($supported as $lang => $country) {
                $return[$lang] = Language::getById($lang);
            }
            return $return;
        }

        return $supported;
    }

    /**
     * Get all css files in dist and return html links
     *
     * @return string
     *
     * @version 1.0
     * @since   1.0
     */
    public function getCss() : string {
        $files = glob(ROOT.'dist/*.css');
        if ($files === false) {
            return '';
        }
        $return = '';
        foreach ($files as $file) {
            if (!str_contains($file, '.min') && in_array(str_replace('.css', '.min.css', $file), $files, true)) {
                continue;
            }
            $return .= '<link rel="stylesheet" href="'.str_replace(
                ROOT,
                $this->getBaseUrl(),
                $file
              ).'?v='.$this->getCacheVersion().'" />'.PHP_EOL;
        }
        return $return;
    }

    /**
     * Get the current base URL (without path, query, etc.)
     *
     * @return non-empty-string
     */
    public function getBaseUrl() : string {
        // @phpstan-ignore-next-line
        return (string) $this->getBaseUrlObject();
    }

    /**
     * Get the current base URL (without path, query, etc.)
     *
     * @return UriInterface
     */
    public function getBaseUrlObject() : UriInterface {
        return $this->getRequest()->getUri()->withPath('/')->withFragment('')->withQuery('');
    }

    /**
     * Gets the FE cache version from config.ini
     *
     * @return int
     */
    public function getCacheVersion() : int {
        return (int) ($this->config->getConfig('General')['CACHE_VERSION'] ?? 1);
    }

    /**
     * Get all js files in dist and return html script-src tags
     *
     * @return string
     *
     * @version 1.0
     * @since   1.0
     */
    public function getJs() : string {
        $files = glob(ROOT.'dist/*.js');
        if ($files === false) {
            return '';
        }
        $return = '';
        foreach ($files as $file) {
            if (!str_contains($file, '.min') && in_array(str_replace('.js', '.min.js', $file), $files, true)) {
                continue;
            }
            $return .= '<script src="'.str_replace(
                ROOT,
                $this->getBaseUrl(),
                $file
              ).'?v='.$this->getCacheVersion().'"></script>'.PHP_EOL;
        }
        return $return;
    }

    /**
     * Checks if the GENERAL - DEBUG option is set in config.ini
     *
     * @return bool
     */
    public function isProduction() : bool {
        return !($this->config->getConfig('General')['DEBUG'] ?? false);
    }

    /**
     * Redirect to something
     *
     * @param  string[]|string|RouteInterface|UriInterface  $to
     * @param  RequestInterface|null  $from
     * @param  int  $type
     *
     * @return Response
     * @noreturn
     */
    public function redirect(
      UriInterface | RouteInterface | array | string $to,
      ?RequestInterface                              $from = null,
      int                                            $type = 302
    ) : Response {
        $link = '';
        if ($to instanceof RouteInterface) {
            $link = self::getLink($to->getPath());
        }
        elseif ($to instanceof UriInterface) {
            $link = (string) $to;
        }
        elseif (is_array($to)) {
            $link = self::getLink($to);
        }
        elseif (is_string($to)) {
            /** @var Route|null $route */
            $route = $this->router->getRouteByName($to);
            if (isset($route)) {
                $link = self::getLink($route->path);
            }
            else {
                $link = $to;
            }
        }
        if (isset($from)) {
            $this->session->flash('fromRequest', serialize($from));
        }

        return new Response(new \Nyholm\Psr7\Response($type, headers: ['Location' => $link]));
    }

    /**
     * Get url to request location
     *
     * @param  array<string|int, string>  $request  request array
     *                                           * Ex: ['user', 'login', 'view' => 1, 'type' => 'company']:
     *   http(s)://host.cz/user/login?view=1&type=company
     *
     * @return string
     * @warning Should use the new \Lsr\Core\Links\Generator class to generate links
     * @see     Generator
     *
     * @version 1.0
     * @since   1.0
     */
    public static function getLink(array $request = []) : string {
        /** @var Generator $generator */
        $generator = self::getService('links.generator');
        return $generator->getLink($request);
    }

    public function getLogger() : Logger {
        if (!isset($this->logger)) {
            $this->logger = new Logger(LOG_DIR, 'app');
        }
        return $this->logger;
    }

    /**
     * @return string[]
     */
    public function getSupportedCountries() : array {
        return $this->translations->supportedCountries;
    }

    /**
     * Get page info for the current request.
     *
     * Used for passing to JS.
     *
     * @return PageInfoDto
     */
    public function getPageInfo() : PageInfoDto {
        $request = $this->getRequest();
        $params = [];
        return new PageInfoDto(
          $request->getType(),
          $this->getRoute($params)?->getName(),
          $request->getPath(),
          /** @phpstan-ignore argument.type */
          array_merge($request->getAttributes(), $params),
        );
    }

    /**
     * Get route for current Request
     *
     * @param  array<string,mixed>  $params
     *
     * @return RouteInterface|null
     * @see Router::getRoute()
     *
     * @see App::getRequest()
     */
    public function getRoute(array &$params) : ?RouteInterface {
        $this->route ??= Router::getRoute($this->getRequest()->getType(), $this->getRequest()->getPath(), $params);
        return $this->route;
    }

    /**
     * Get current page HTML or run CLI command
     *
     * @throws Requests\Exceptions\RouteNotFoundException
     * @throws InvalidLanguageException
     * @since   1.0
     * @version 1.0
     */
    public function run() : ResponseInterface {
        $params = [];
        $request = $this->getRequest();

        // Serve static file
        // This is a fallback handler, because normally an HTTP server should handle static files.
        if ($request instanceof Request && $request->isStaticFile()) {
            header('Content-Type: '.$request->getStaticFileMime());
            $filePath = urldecode(ROOT.substr($request->getUri()->getPath(), 1));
            readfile($filePath);
            exit();
        }

        $route = $this->getRoute($params);

        if (!isset($route)) {
            throw new RouteNotFoundException($request);
        }

        foreach ($params as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }
        // Update immutable request
        $this->request = $request;

        /** @phpstan-ignore argument.type */
        $request->setParams($params);

        $lang = $this->getDesiredLanguageCode();
        $this->translations->setLang($lang);
        $request = $request->withAttribute('lang', $this->translations->getLangId());

        // Update immutable request
        $this->request = $request;

        assert($route instanceof Route);
        return $this->routeHandler
          ->setRoute($route)
          ->handle($request);
    }

    /**
     * Get desired language for the page
     *
     * Checks request parameters, session and HTTP headers in this order.
     *
     * @return string Language code
     */
    protected function getDesiredLanguageCode() : string {
        $request = $this->getRequest();
        /** @var string|null $lang */
        $lang = $request->getParam('lang');
        if (isset($lang) && $this->isSupportedLanguage($lang)) {
            return $lang;
        }

        if (isset($this->session)) {
            /** @var string|null $sessLang */
            $sessLang = $this->session->get('lang');
            if (isset($sessLang) && $this->isSupportedLanguage($sessLang)) {
                return $sessLang;
            }
        }

        $cookieLang = $request->getCookieParams()['lang'] ?? null;
        if (is_string($cookieLang) && $this->isSupportedLanguage($cookieLang)) {
            return $cookieLang;
        }

        if ($request->hasHeader('Accept-Language')) {
            $header = $request->getHeader('Accept-Language');
            foreach ($header as $value) {
                $info = explode(';', $value);
                $languages = explode(',', $info[0]);
                foreach ($languages as $language) {
                    if ($this->isSupportedLanguage($language)) {
                        return $language;
                    }
                }
            }
        }
        return DEFAULT_LANGUAGE;
    }

    /**
     * Test if the language code is valid and if the language is supported
     *
     * @param  string  $language  Language code
     *
     * @return bool
     */
    protected function isSupportedLanguage(string $language) : bool {
        preg_match('/([a-z]{2})[\-_]?/', $language, $matches);
        if (!isset($matches[1])) {
            return false;
        }
        $id = $matches[1];
        return isset($this->translations->supportedLanguages[$id]);
    }

    public function getAppName() : string {
        return (string) ($this->config->getConfig('ENV')['APP_NAME'] ?? '');
    }

    public function getLanguage() : Language {
        return $this->translations->getLanguage();
    }

}
