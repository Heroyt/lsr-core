<?php

namespace Lsr\Core\Migrations;

use Lsr\Core\Exceptions\CyclicDependencyException;
use Lsr\Exceptions\FileException;
use Nette\Neon\Exception;
use Nette\Neon\Neon;
use Nette\Utils\AssertionException;
use Nette\Utils\Validators;

/**
 * @phpstan-import-type MigrationData from Migration
 */
class MigrationLoader
{

    /** @var array<string, MigrationData> */
    public array $migrations = [];
    /** @var array<string,bool> */
    protected array $loadedFiles = [];
    /** @var array<string,string> */
    public array $views = [];

    /**
     * @param  string  $configFile
     */
    public function __construct(
      public readonly string $configFile
    ) {}

    /**
     * @return void
     * @throws AssertionException
     * @throws CyclicDependencyException
     * @throws Exception
     * @throws FileException
     */
    public function load() : void {
        ['tables' => $tables, 'views' => $views] = $this->loadFile($this->configFile);
        $this->migrations = $tables;
        $this->views = $views;
    }

    /**
     * @param  string  $file
     *
     * @return array{tables: array<string, MigrationData>, views: array<string,string>}
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

        /** @var array{includes?: string[], tables?: array<string,MigrationData>, views?: array<string,string>} $data */
        $data = Neon::decodeFile($file);

        $tables = [];
        $views = [];

        if (isset($data['includes'])) {
            Validators::assert($data['includes'], 'list');
            foreach ($data['includes'] as $includeFile) {
                $loaded = $this->loadFile($includeFile);
                $tables = static::merge($loaded['tables'], $tables);
                $views = array_merge($loaded['views'], $views);
            }
        }

        return [
          'tables' => static::merge($data['tables'] ?? [], $tables),
          'views'  => array_merge($data['views'] ?? [], $views),
        ];
    }

    /**
     * @template T of array
     *
     * @param  T|null  $value
     * @param  T|null  $base
     *
     * @return T
     */
    public static function merge(?array $value, ?array $base) : array {
        if (is_array($value) && is_array($base)) {
            $index = 0;
            foreach ($value as $key => $val) {
                if ($key === $index) {
                    $base[] = $val;
                    $index++;
                }
                elseif (!is_array($val)) {
                    $base[$key] = $val;
                }
                else {
                    /** @phpstan-ignore argument.type */
                    $base[$key] = static::merge($val, $base[$key] ?? null);
                }
            }
            /** @phpstan-ignore-next-line  */
            return $base;
        }

        if (is_array($value)) {
            return $value;
        }

        /** @phpstan-ignore-next-line  */
        return $base ?? [];
    }

    /**
     * @param  array<string,MigrationData>  $data
     * @return array<string,Migration>
     */
    public static function transformToDto(array $data) : array {
        $migrations = [];
        foreach ($data as $table => $migrationData) {
            $migrations[$table] = Migration::fromArray($table, $migrationData);
        }
        return $migrations;
    }

}