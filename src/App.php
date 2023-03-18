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
use JsonException;
use Lsr\Core\Menu\MenuItem;
use Lsr\Core\Requests\CliRequest;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Uri;
use Lsr\Core\Routing\CliRoute;
use Lsr\Core\Routing\Route;
use Lsr\Core\Routing\Router;
use Lsr\Exceptions\FileException;
use Lsr\Helpers\Tools\Timer;
use Lsr\Interfaces\RequestInterface;
use Lsr\Interfaces\RouteInterface;
use Lsr\Interfaces\SessionInterface;
use Lsr\Logging\Logger;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\ExtensionsExtension;
use Nette\DI\MissingServiceException;
use Nette\Http\Url;
use ReflectionException;

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

	public static string    $activeLanguageCode = 'cs_CZ';
	public static ?Language $language;
	/** @var array<string,string> */
	public static array $supportedLanguages = [];
	/** @var string[] */
	public static array $supportedCountries = [];
	/**
	 * @var bool $prettyUrl
	 * @brief If app should use a SEO-friendly pretty url
	 */
	protected static bool $prettyUrl = false;
	/** @var RequestInterface $request Current request object */
	protected static RequestInterface $request;
	protected static Logger           $logger;
	/**
	 * @var string
	 */
	private static mixed            $timezone;
	private static Container        $container;
	private static Router           $router;
	private static SessionInterface $session;
	private static Config           $config;

	/**
	 * Initialization function
	 *
	 * @post Logger is initialized
	 * @post Routes are set
	 * @post Request is parsed
	 * @post Latte macros are set
	 * @throws JsonException
	 * @throws ReflectionException
	 */
	public static function init() : void {
		Timer::start('core.setup');
		self::$logger = new Logger(LOG_DIR, 'app');

		self::setupDi();

		// Setup session
		self::$session = self::getServiceByType(SessionInterface::class);

		// Setup routes
		self::$router = self::getServiceByType(Router::class);
		self::$router->setup();

		self::$config = self::getServiceByType(Config::class);

		if (PHP_SAPI === "cli") {
			global $argv;
			self::$request = new CliRequest($argv[1] ?? '');
		}
		else {
			self::$request = new Request(
				new Uri((self::isSecure() ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'])
			);
			/** @var string|null $previousRequest */
			$previousRequest = self::$session->getFlash('fromRequest');
			if (isset($previousRequest)) {
				/** @var Request|false $previousRequest */
				$previousRequest = unserialize(
					$previousRequest,
					[
						'allowed_classes' => [
							Request::class,
							CliRequest::class,
							Route::class,
							CliRoute::class,
							Uri::class,
						],
					]
				);
				if ($previousRequest instanceof RequestInterface) {
					self::$request->setPreviousRequest($previousRequest);
				}
			}
		}

		// Set language and translations
		self::setupLanguage();
		Timer::stop('core.setup');
	}

	/**
	 * Set up a DI container
	 *
	 * @return void
	 */
	protected static function setupDi() : void {
		Timer::start('core.setup.di');
		$loader = new ContainerLoader(TMP_DIR.'di/');
		/** @var Container $class */
		$class = $loader->load(function(Compiler $compiler) {
			$compiler->addExtension('extensions', new ExtensionsExtension());
			/** @var string[] $configs */
			$configs = require ROOT.'config/services.php';
			// This will load all found services.neon files in the whole application that are cached in one PHP file
			foreach ($configs as $config) {
				$compiler->loadConfig($config);
			}
		});
		self::$container = new $class;
		Timer::stop('core.setup.di');
	}

	/**
	 * Resolves service by type.
	 *
	 * @template T of object
	 *
	 * @param class-string<T> $type
	 *
	 * @return T
	 * @throws MissingServiceException
	 */
	public static function getServiceByType(string $type) : object {
		return self::getContainer()->getByType($type);
	}

	/**
	 * @return Container
	 */
	public static function getContainer() : Container {
		return self::$container;
	}

	/**
	 * Get if https is enabled
	 *
	 * @return bool
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function isSecure() : bool {
		return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
	}

	/**
	 * Setup language for translations to work
	 *
	 * @return void
	 */
	protected static function setupLanguage() : void {
		// Load language info
		self::$language = Language::getById(self::getDesiredLanguageCode());

		date_default_timezone_set(self::getTimezone());

		if (isset(self::$language)) {
			$supported = self::getSupportedLanguages();
			self::$activeLanguageCode = self::$language->id;
			if (isset($supported[self::$language->id])) {
				/* @phpstan-ignore-next-line */
				self::$activeLanguageCode .= '_'.$supported[self::$language->id];
			}

			// Set target language
			putenv('LANG='.self::$activeLanguageCode);
			putenv('LC_ALL='.self::$activeLanguageCode);
			setlocale(LC_ALL, '0');
			setlocale(LC_ALL, self::$activeLanguageCode, self::$activeLanguageCode.'.UTF8', self::$activeLanguageCode.'.UTF-8', self::$activeLanguageCode.'.utf-8', self::$language->name);
			setlocale(LC_MESSAGES, self::$activeLanguageCode, self::$activeLanguageCode.'.UTF8', self::$activeLanguageCode.'.UTF-8', self::$activeLanguageCode.'.utf-8', self::$language->name);
			bindtextdomain(LANGUAGE_FILE_NAME, substr(LANGUAGE_DIR, 0, -1));
			textdomain(LANGUAGE_FILE_NAME);
			bind_textdomain_codeset(LANGUAGE_FILE_NAME, "UTF-8");
		}
	}

	/**
	 * Get desired language for the page
	 *
	 * Checks request parameters, session and HTTP headers in this order.
	 *
	 * @return string Language code
	 */
	protected static function getDesiredLanguageCode() : string {
		$request = self::getRequest();
		if (isset($request, $request->params['lang']) && self::isSupportedLanguage($request->params['lang'])) {
			return $request->params['lang'];
		}
		/** @var string|null $sessLang */
		$sessLang = self::$session->get('lang');
		if (isset($sessLang) && self::isSupportedLanguage($sessLang)) {
			return $sessLang;
		}
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			$info = explode(';', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
			$languages = explode(',', $info[0]);
			foreach ($languages as $language) {
				if (self::isSupportedLanguage($language)) {
					return $language;
				}
			}
		}
		return DEFAULT_LANGUAGE;
	}

	/**
	 * Get the request array
	 *
	 * @return RequestInterface|null
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function getRequest() : ?RequestInterface {
		return self::$request ?? null;
	}

	/**
	 * Test if the language code is valid and if the language is supported
	 *
	 * @param string $language Language code
	 *
	 * @return bool
	 */
	protected static function isSupportedLanguage(string $language) : bool {
		preg_match('/([a-z]{2})[\-_]?/', $language, $matches);
		$id = $matches[1];
		return self::isValidLanguage($language) && isset(self::getSupportedLanguages()[$id]);
	}

	/**
	 * Check if the language exists
	 *
	 * @param string $language
	 *
	 * @return bool
	 */
	protected static function isValidLanguage(string $language) : bool {
		return Language::getById($language) !== null;
	}

	/**
	 * @param bool $returnObjects
	 *
	 * @return array<string, string|Language|null>
	 */
	public static function getSupportedLanguages(bool $returnObjects = false) : array {
		if (empty(self::$supportedLanguages)) {
			/** @var string[] $files */
			$files = glob(LANGUAGE_DIR.'*');
			$dirs = array_map(static function(string $dir) {
				return str_replace(LANGUAGE_DIR, '', $dir);
			}, $files);
			foreach ($dirs as $dir) {
				$explode = explode('_', $dir);
				if (count($explode) !== 2) {
					continue;
				}
				[$lang, $country] = $explode;
				self::$supportedLanguages[$lang] = $country;
			}
		}
		if ($returnObjects) {
			$return = [];
			foreach (self::$supportedLanguages as $lang => $country) {
				$return[$lang] = Language::getById($lang);
			}
			return $return;
		}
		return self::$supportedLanguages;
	}

	/**
	 * @return string
	 */
	public static function getTimezone() : string {
		if (empty(self::$timezone)) {
			self::$timezone = (string) (self::$config->getConfig('General')['TIMEZONE'] ?? 'Europe/Prague');
		}
		return self::$timezone;
	}

	/**
	 * Get parsed config.ini file
	 *
	 * @return array<string,array<string,string|numeric>|numeric|string>
	 *
	 * @deprecated Use DI for loading config instead
	 */
	public static function getConfig() : array {
		return self::$config->getConfig();
	}

	/**
	 * @return string[]
	 */
	public static function getSupportedCountries() : array {
		if (empty(self::$supportedCountries)) {
			/** @var string $country */
			foreach (self::getSupportedLanguages() as $country) {
				if (isset(Constants::COUNTRIES[$country])) {
					self::$supportedCountries[$country] = Constants::COUNTRIES[$country];
				}
			}
		}
		return self::$supportedCountries;
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

	/**
	 * Get all css files in dist and return html links
	 *
	 * @return string
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function getCss() : string {
		/** @var string[] $files */
		$files = glob(ROOT.'dist/*.css');
		$return = '';
		foreach ($files as $file) {
			if (!str_contains($file, '.min') && in_array(str_replace('.css', '.min.css', $file), $files, true)) {
				continue;
			}
			$return .= '<link rel="stylesheet" href="'.str_replace(ROOT, self::getUrl(), $file).'?v='.self::getCacheVersion().'" />'.PHP_EOL;
		}
		return $return;
	}

	/**
	 * Get the current URL
	 *
	 * @param bool $returnObject If true, return Url object, else return string
	 *
	 * @return ($returnObject is true ? Url : string)
	 */
	public static function getUrl(bool $returnObject = false) : Url|string {
		$url = new Url();
		$url
			->setScheme(self::isSecure() ? 'https' : 'http')
			->setHost($_SERVER['HTTP_HOST'] ?? 'localhost');
		if ($returnObject) {
			return $url;
		}
		return (string) $url;
	}

	/**
	 * Gets the FE cache version from config.ini
	 *
	 * @return int
	 */
	public static function getCacheVersion() : int {
		return (int) (self::$config->getConfig('General')['CACHE_VERSION'] ?? 1);
	}

	/**
	 * Get all js files in dist and return html script-src tags
	 *
	 * @return string
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function getJs() : string {
		/** @var string[] $files */
		$files = glob(ROOT.'dist/*.js');
		$return = '';
		foreach ($files as $file) {
			if (!str_contains($file, '.min') && in_array(str_replace('.js', '.min.js', $file), $files, true)) {
				continue;
			}
			$return .= '<script src="'.str_replace(ROOT, self::getUrl(), $file).'?v='.self::getCacheVersion().'"></script>'.PHP_EOL;
		}
		return $return;
	}

	/**
	 * Get current page HTML or run CLI command
	 *
	 * @throws Requests\Exceptions\RouteNotFoundException
	 * @since   1.0
	 * @version 1.0
	 */
	public static function run() : void {
		self::$request->handle();
	}

	/**
	 * Echo json-encoded data and exits
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return never
	 * @throws JsonException
	 */
	public static function sendAjaxData(array $data) : never {
		header('Content-Type: application/json; charset=UTF-8');
		bdump($data);
		exit(json_encode($data, JSON_THROW_ON_ERROR));
	}

	/**
	 * Checks if the GENERAL - DEBUG option is set in config.ini
	 *
	 * @return bool
	 */
	public static function isProduction() : bool {
		return !(self::$config->getConfig('General')['DEBUG'] ?? false);
	}

	/**
	 * Redirect to something
	 *
	 * @param string[]|string|RouteInterface|Url $to
	 * @param RequestInterface|null              $from
	 *
	 * @noreturn
	 */
	public static function redirect(Url|RouteInterface|array|string $to, ?RequestInterface $from = null) : never {
		$link = '';
		if ($to instanceof RouteInterface) {
			$link = self::getLink($to->getPath());
		}
		elseif ($to instanceof Url) {
			$link = $to->getAbsoluteUrl();
		}
		elseif (is_array($to)) {
			$link = self::getLink($to);
		}
		elseif (is_string($to)) {
			/** @var Route|null $route */
			$route = self::$router->getRouteByName($to);
			if (isset($route)) {
				$link = self::getLink($route->path);
			}
			else {
				$link = $to;
			}
		}
		if (isset($from)) {
			self::$session->flash('fromRequest', serialize($from));
		}
		header('Location: '.$link);
		exit;
	}

	/**
	 * Get url to request location
	 *
	 * @param array<string|int> $request      request array
	 *                                        * Ex: ['user', 'login', 'view' => 1, 'type' => 'company']: http(s)://host.cz/user/login?view=1&type=company
	 * @param bool              $returnObject if set to true, return Url object
	 *
	 * @return string|Url
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public static function getLink(array $request = [], bool $returnObject = false) : Url|string {
		/** @var Url $url */
		$url = self::getUrl(true);
		$request = array_filter($request, static function($value) {
			return !empty($value);
		});
		if (self::isPrettyUrl()) {
			$url->setPath(implode('/', array_filter($request, 'is_int', ARRAY_FILTER_USE_KEY)));
			$url->setQuery(array_filter($request, 'is_string', ARRAY_FILTER_USE_KEY));
		}
		else {
			$query = array_filter($request, 'is_string', ARRAY_FILTER_USE_KEY);
			$query['p'] = array_filter($request, 'is_int', ARRAY_FILTER_USE_KEY);
			$url->setQuery($query);
		}
		if ($returnObject) {
			return $url;
		}
		return (string) $url;
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
	 * @return Logger
	 */
	public static function getLogger() : Logger {
		if (!isset(self::$logger)) {
			/** @var Logger $logger */
			$logger = self::getService('logger');
			self::$logger = $logger;
		}
		return self::$logger;
	}

	/**
	 * Gets the service object by name.
	 *
	 * @param string $name
	 *
	 * @return object
	 */
	public static function getService(string $name) : object {
		return self::getContainer()->getService($name);
	}

	/**
	 * @param string $type
	 *
	 * @return MenuItem[]
	 * @throws FileException
	 */
	public static function getMenu(string $type = 'menu') : array {
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
				$path = self::$router->getRouteByName($item['route'])?->getPath();
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
					$path = self::$router->getRouteByName($child['route'])?->getPath();
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
	 * @todo         : Implement auth package
	 * @noinspection PhpUndefinedClassInspection
	 */
	private static function checkAccess(array $item) : bool {
		/** @phpstan-ignore-next-line */
		if (isset($item['loggedInOnly']) && $item['loggedInOnly'] && !User::loggedIn()) {
			return false;
		}
		/** @phpstan-ignore-next-line */
		if (isset($item['loggedOutOnly']) && $item['loggedOutOnly'] && User::loggedIn()) {
			return false;
		}
		if (!isset($item['access'])) {
			return true;
		}
		$available = true;
		$access = [];
		if (is_string($item['access'])) {
			$access = [$item['access']];
		}
		else if (is_array($item['access'])) {
			$access = $item['access'];
		}
		foreach ($access as $right) {
			/** @phpstan-ignore-next-line */
			if (!User::hasRight($right)) {
				$available = false;
				break;
			}
		}
		return $available;
	}

	public static function getShortLanguageCode() : string {
		return explode('_', self::$activeLanguageCode)[0];
	}

	public static function getAppName() : string {
		return (string) (self::$config->getConfig('ENV')['APP_NAME'] ?? '');
	}

}
