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

	public function __construct() {
		$this->config['ENV'] = self::ENV_DEFAULTS;
	}

	/**
	 * @return Config
	 */
	public static function getInstance() : Config {
		if (!isset(self::$instance)) {
			self::$instance = new self();
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
	 * @return void
	 */
	public function init() : void {
		if ($this->initialized) {
			return;
		}
		$ini = $neon = [];
		if (file_exists(PRIVATE_DIR.'config.ini')) {
			$ini = parse_ini_file(PRIVATE_DIR.'config.ini', true);
			if ($ini === false) {
				$ini = [];
			}
		}
		if (file_exists(PRIVATE_DIR.'config.neon')) {
			try {
				$neon = Neon::decodeFile(PRIVATE_DIR.'config.neon');
			} catch (Exception) {
			}
		}
		// @phpstan-ignore-next-line
		$this->config = array_merge($this->config, $ini, $neon);

		$dotenv = Dotenv::createImmutable(ROOT);
		$dotenv->safeLoad();
		$this->config['ENV'] = array_merge($this->config['ENV'], $_ENV);

		$this->initialized = true;
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
	 * @return ($category is null ? array<string,array<string,string|numeric>|numeric|string> : array<string,string|numeric>)
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

}