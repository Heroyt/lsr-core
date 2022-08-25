<?php

namespace Lsr\Core\Migrations;

use Lsr\Core\Exceptions\CyclicDependencyException;
use Lsr\Exceptions\FileException;
use Nette\Neon\Exception;
use Nette\Neon\Neon;
use Nette\Utils\AssertionException;
use Nette\Utils\Validators;

class MigrationLoader
{

	/** @var array<string, array{definition:string, modifications?:array<string,string[]>}> */
	public array $migrations = [];
	/** @var array<string,bool> */
	protected array $loadedFiles = [];

	/**
	 * @param string $configFile
	 */
	public function __construct(
		public readonly string $configFile
	) {
	}

	/**
	 * @return void
	 * @throws AssertionException
	 * @throws CyclicDependencyException
	 * @throws Exception
	 * @throws FileException
	 */
	public function load() : void {
		$this->migrations = $this->loadFile($this->configFile);
	}

	/**
	 * @param string $file
	 *
	 * @return array<string, array{definition:string, modifications?:array<string,string[]>}>
	 * @throws AssertionException
	 * @throws CyclicDependencyException
	 * @throws Exception
	 * @throws FileException
	 */
	public function loadFile(string $file) : array {
		if (!file_exists($file)) {
			throw new FileException('File "'.$file.'" does not exit');
		}
		if (!is_readable($file)) {
			throw new FileException('File "'.$file.'" is not readable');
		}

		if (isset($this->loadedFiles[$file])) {
			throw new CyclicDependencyException('Recursive included file "'.$file.'"');
		}

		$this->loadedFiles[$file] = true;

		/** @var array{includes?: string[], tables?: array<string,array{definition:string,modifications?:array<string,string[]>}>} $data */
		$data = Neon::decodeFile($file);

		$migrations = [];

		if (isset($data['includes'])) {
			Validators::assert($data['includes'], 'list');
			foreach ($data['includes'] as $includeFile) {
				$migrations = static::merge($this->loadFile($includeFile), $migrations);
			}
		}

		return static::merge($data['tables'] ?? [], $migrations);
	}

	/**
	 * @param array|null $value
	 * @param array|null $base
	 *
	 * @return array
	 */
	public static function merge(?array $value, ?array $base) : array {
		if (is_array($value) && is_array($base)) {
			$index = 0;
			foreach ($value as $key => $val) {
				if ($key === $index) {
					$base[] = $val;
					$index++;
				}
				else {
					$base[$key] = static::merge($val, $base[$key] ?? null);
				}
			}
			return $base;
		}

		if (is_array($value)) {
			return $value;
		}

		return $base;
	}

}