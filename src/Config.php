<?php

namespace Lsr\Core;

use Dotenv\Dotenv;
use Nette\Neon\Exception;
use Nette\Neon\Neon;

class Config
{

	private const ENV_DEFAULTS = [
		'APP_NAME'    => '',
		'DB_HOST'     => 'localhost',
		'DB_PORT'     => 3306,
		'DB_NAME'     => '',
		'DB_USER'     => '',
		'DB_PASSWORD' => '',
		'DB_DATABASE' => '',
		'DB_COLLATE'  => 'utf8mb4',
		'DB_DRIVER'   => 'mysqli',
		'DB_PREFIX'   => '',
	];

	private static Config $instance;

	private bool $initialized = false;

	/** @var array<string,array<string,string|numeric>|string|numeric> */
	private array $config = [
		'ENV' => [],
	];

	private string $iniFile;
	private string $neonFile;
	private string $envFile;

	public function __construct(
		private readonly string $cacheDir = TMP_DIR
	) {
		$this->config['ENV'] = self::ENV_DEFAULTS;
	}

	/**
	 * @return Config
	 */
	public static function getInstance(string $cacheDir = TMP_DIR) : Config {
		if (!isset(self::$instance)) {
			self::$instance = new self($cacheDir);
		}
		if (!self::$instance->isInitialized()) {
			self::$instance->init();
		}
		return self::$instance;
	}

	/**
	 * @return bool
	 */
	public function isInitialized() : bool {
		return $this->initialized;
	}

	/**
	 * Initializes APP configuration
	 *
	 * Checks config cache first. Should be only called once.
	 *
	 * @post Initialized flag is set
	 * @post Config is cached into a cache file
	 *
	 * @return void
	 */
	public function init() : void {
		if ($this->initialized || $this->checkCache()) {
			return;
		}
		$ini = $neon = [];
		if (($iniFile = $this->getIniFile()) !== '') {
			$ini = parse_ini_file($iniFile, true);
			if ($ini === false) {
				$ini = [];
			}
		}
		if (($neonFile = $this->getNeonFile()) !== '') {
			try {
				$neon = Neon::decodeFile($neonFile);
			} catch (Exception) {
			}
		}
		// @phpstan-ignore-next-line
		$this->config = array_merge($this->config, $ini, $neon);

		$dotenv = Dotenv::createImmutable(ROOT);
		$dotenv->safeLoad();
		$env = getenv();
		if (!is_array($env)) {
			$env = [];
		}
		$this->config['ENV'] = array_merge($this->config['ENV'], $_ENV, $env);

		$this->initialized = true;
		$this->saveCache();
	}

	/**
	 * Check config cache file and if it exists and is valid, load the cache file into config
	 *
	 * @post If cache file is valid, it is loaded into config.
	 *
	 * @return bool If the cache file is valid and loaded
	 */
	public function checkCache() : bool {
		$cacheFile = $this->cacheDir.'config.cache';
		if (!file_exists($cacheFile)) {
			return false;
		}

		// Check file updates
		$modTime = filemtime($cacheFile);
		$ini = $this->getIniFile();
		if ($ini !== '' && filemtime($ini) > $modTime) {
			return false;
		}
		$neon = $this->getNeonFile();
		if ($neon !== '' && filemtime($neon) > $modTime) {
			return false;
		}
		$env = $this->getEnvFile();
		if ($env !== '' && filemtime($env) > $modTime) {
			return false;
		}

		// Load cache
		$content = file_get_contents($cacheFile);
		if (!$content) {
			return false;
		}
		/** @var false|array<string,array<string,string|numeric>|string|numeric> $config */
		$config = unserialize($content, ['allowed_classes' => false]);
		if ($config === false) {
			return false;
		}
		$this->config = $config;
		$this->initialized = true;
		return true;
	}

	/**
	 * @return string Absolute file path or empty string if the file does not exist
	 */
	public function getIniFile() : string {
		$this->iniFile ??= file_exists(PRIVATE_DIR.'config.ini') ? PRIVATE_DIR.'config.ini' : '';
		return $this->iniFile;
	}

	/**
	 * @return string Absolute file path or empty string if the file does not exist
	 */
	public function getNeonFile() : string {
		$this->neonFile ??= file_exists(PRIVATE_DIR.'config.neon') ? PRIVATE_DIR.'config.neon' : '';
		return $this->neonFile;
	}

	/**
	 * @return string Absolute file path or empty string if the file does not exist
	 */
	public function getEnvFile() : string {
		$this->envFile ??= file_exists(ROOT.'.env') ? ROOT.'.env' : '';
		return $this->envFile;
	}

	/**
	 * @param array<string,string|numeric> $defaults
	 *
	 * @return void
	 */
	public function extendEnvDefault(array $defaults) : void {
		$this->config['ENV'] = array_merge($this->config['ENV'], $defaults);
	}

	/**
	 * @param string|null $category
	 *
	 * @return ($category is null ? array<string,array<string,string|numeric>|numeric|string> :
	 *                    array<string,string|numeric>)
	 */
	public function getConfig(?string $category = null) : array {
		if (!$this->initialized) {
			return [];
		}
		if (isset($category)) {
			/** @var array<string, string|numeric>|string|numeric|null $return */
			$return = $this->config[$category] ?? [];
			if (!is_array($return)) {
				return [];
			}
			return $return;
		}
		return $this->config;
	}

	/**
	 * Cache loaded config into a file
	 *
	 * @return bool If save was successful
	 */
	public function saveCache() : bool {
		$success = file_put_contents($this->cacheDir.'config.cache', serialize($this->config));
		return $success !== false && $success > 0;
	}

}