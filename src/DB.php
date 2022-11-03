<?php
/**
 * @file      DB.php
 * @brief     Database connection handling
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */


namespace Lsr\Core;

use DateTimeInterface;
use dibi;
use Dibi\Connection;
use Dibi\Exception;
use Dibi\Fluent;
use Dibi\Result;
use InvalidArgumentException;
use Lsr\Core\Dibi\Drivers\MySqliDriver;
use Lsr\Logging\Logger;
use RuntimeException;

/**
 * @class   DB
 * @brief   Class responsible for managing Database connection and storing common queries
 * @details Database abstraction layer for managing database connection. It uses a Dibi library to connect to the database and expands on it, adding some common queries as single methods.
 *
 * @package Core
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
class DB
{

	/**
	 * @var Connection $db Dibi Database connection
	 */
	protected static Connection $db;
	protected static Logger     $log;

	/**
	 * Initialization function
	 *
	 * @param array{
	 *     Database?: array{
	 *         DRIVER?: string,
	 *         HOST?: string,
	 *         PORT?: string,
	 *         USER?: string,
	 *         PASS?: string,
	 *         DATABASE?: string,
	 *         COLLATE?: string,
	 *         PREFIX?: string,
	 *     }
	 * } $config
	 *
	 * @post  Database connection is created and stored in DB::db variable
	 *
	 * @throws Exception
	 *
	 * @since 1.0
	 */
	public static function init(array $config = []) : void {
		if (empty($config)) {
			$config = App::getConfig();
		}
		/**
		 * @var array{
		 *         DRIVER?: string,
		 *         HOST?: string,
		 *         PORT?: string|int,
		 *         USER?: string,
		 *         PASS?: string,
		 *         DATABASE?: string,
		 *         COLLATE?: string,
		 *         PREFIX?: string,
		 *     } $dbConfig
		 */
		$dbConfig = $config['Database'] ?? [];
		self::$log = new Logger(LOG_DIR, 'db');
		$options = [
			'driver' => $dbConfig['DRIVER'] ?? 'mysqli',
		];
		if (!empty($dbConfig['HOST'])) {
			$options['host'] = $dbConfig['HOST'];
		}
		if (!empty($dbConfig['PORT'])) {
			$options['port'] = (int) $dbConfig['PORT'];
		}
		if (!empty($dbConfig['USER'])) {
			$options['username'] = $dbConfig['USER'];
		}
		if (!empty($dbConfig['PASS'])) {
			$options['password'] = $dbConfig['PASS'];
		}
		if (!empty($dbConfig['DATABASE'])) {
			$options['database'] = $dbConfig['DATABASE'];
		}
		if (!empty($dbConfig['COLLATE'])) {
			$options['charset'] = $dbConfig['COLLATE'];
		}
		if ($options['driver'] === 'sqlite') {
			/** @var string $dbFile */
			$dbFile = $options['database'] ?? TMP_DIR.'db.db';
			if (!file_exists($dbFile)) {
				touch($dbFile);
			}
		}
		else if ($options['driver'] === 'mysqli') {
			$options['driver'] = new MySqliDriver($options);
		}
		self::$db = new Connection($options);
		/** @phpstan-ignore-next-line */
		self::$db->getSubstitutes()->{''} = $dbConfig['PREFIX'] ?? '';
		self::$db->onEvent[] = [self::$log, 'logDb'];
	}

	/**
	 * Connection close function
	 *
	 * @pre   Connection should be initialized
	 * @post  Connection is closed
	 *
	 * @since 1.0
	 */
	public static function close() : void {
		if (isset(self::$db)) {
			self::$db->disconnect();
		}
	}

	/**
	 * Get query update
	 *
	 * @param string                 $table
	 * @param array<string, mixed>   $args
	 * @param array<int, mixed>|null $where
	 *
	 * @return Fluent|int
	 *
	 * @throws Exception
	 * @since 1.0
	 */
	public static function update(string $table, array $args, array $where = null) : Fluent|int {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}
		$q = self::$db->update($table, $args);
		if (isset($where)) {
			/** @var int $rows */
			$rows = $q->where(...$where)->execute(dibi::AFFECTED_ROWS);
			return $rows;
		}
		return $q;
	}

	/**
	 * Get query insert
	 *
	 * @param string                  $table
	 * @param iterable<string, mixed> $args
	 *
	 * @return Fluent
	 *
	 * @since 1.0
	 */
	public static function insertGet(string $table, iterable $args) : Fluent {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}
		return self::$db->insert($table, $args);
	}

	/**
	 * Insert values
	 *
	 * @param string               $table
	 * @param array<string, mixed> ...$args
	 *
	 * @return int
	 * @throws Exception
	 *
	 * @since 1.0
	 */
	public static function insert(string $table, array ...$args) : int {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}
		if (count($args) > 1) {
			/** @phpstan-ignore-next-line */
			return self::$db
				->command()
				->insert()
				->into('%n', $table, '(%n)', array_keys($args[0]))
				->values('%l'.str_repeat(', %l', count($args) - 1), ...$args)
				->execute(dibi::AFFECTED_ROWS);
		}
		/** @phpstan-ignore-next-line */
		return self::$db->insert($table, ...$args)->execute(dibi::AFFECTED_ROWS);
	}

	/**
	 * Insert value with IGNORE flag enabled
	 *
	 * @param string                  $table
	 * @param iterable<string, mixed> $args
	 *
	 * @return int
	 * @throws Exception
	 */
	public static function insertIgnore(string $table, iterable $args) : int {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}
		/** @phpstan-ignore-next-line */
		return self::$db->insert($table, $args)->setFlag('IGNORE')->execute(dibi::AFFECTED_ROWS);
	}

	/**
	 * Get query insert
	 *
	 * @param string $table
	 *
	 * @return Fluent
	 *
	 * @since 1.0
	 */
	public static function deleteGet(string $table) : Fluent {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}
		return self::$db->delete($table);
	}

	/**
	 * Insert values
	 *
	 * @param string            $table
	 * @param array<int, mixed> $where
	 *
	 * @return int
	 * @throws Exception
	 * @since 1.0
	 */
	public static function delete(string $table, array $where = []) : int {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}
		$query = self::$db->delete($table);
		if (!empty($where)) {
			$query->where(...$where);
		}
		/** @phpstan-ignore-next-line */
		return $query->execute(dibi::AFFECTED_ROWS);
	}

	/**
	 * Get connection class
	 *
	 * @return Connection
	 *
	 * @since 1.0
	 */
	public static function getConnection() : Connection {
		return self::$db;
	}

	/**
	 * Get last generated id of the inserted row
	 *
	 * @return int
	 * @throws Exception
	 * @since 1.0
	 */
	public static function getInsertId() : int {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}
		return self::$db->getInsertId();
	}

	/**
	 * Start query select
	 *
	 * @param string[]|string $table
	 * @param mixed           ...$args
	 *
	 * @return Fluent
	 *
	 * @throws InvalidArgumentException
	 *
	 * @since 1.0
	 */
	public static function select(array|string $table, ...$args) : Fluent {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}
		$query = self::$db->select(...$args);
		if (is_string($table)) {
			$query->from($table);
		}
		elseif (is_array($table)) {
			$query->from(...$table);
		}
		return $query;
	}

	public static function disableCache() : void {
		if (isset(self::$db)) {
			$driver = self::$db->getDriver();
			if (isset($driver->cacheEnabled)) {
				$driver->cacheEnabled = false;
			}
		}
	}

	public static function enableCache() : void {
		if (isset(self::$db)) {
			$driver = self::$db->getDriver();
			if (isset($driver->cacheEnabled)) {
				$driver->cacheEnabled = true;
			}
		}
	}

	/**
	 * Resets autoincrement value to the first available number
	 *
	 * @param string $table
	 *
	 * @return Result
	 * @throws Exception
	 */
	public static function resetAutoIncrement(string $table) : Result {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}
		return self::$db->query('ALTER TABLE %n AUTO_INCREMENT = 1', $table);
	}

	/**
	 * @param string                                    $table
	 * @param array<string, mixed|array<string, mixed>> $values
	 *
	 * @return int
	 * @throws Exception
	 */
	public static function replace(string $table, array $values) : int {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}

		$multiple = false;
		foreach ($values as $data) {
			if (is_array($data)) {
				$multiple = true;
				break;
			}
		}

		$args = [];
		$valueArgs = [];
		$queryKeys = [];
		$rows = [];
		$row = [];
		foreach ($values as $key => $data) {
			if (is_array($data)) {
				$row = [];
				foreach ($data as $key2 => $val) {
					$queryKeys[$key2] = '%n';
					$args[$key2] = $key2;
					$row[$key2] = self::getEscapeType($val);
					$valueArgs[] = $val;
				}
				$rows[] = '('.implode(', ', $row).')';
				continue;
			}
			$queryKeys[$key] = '%n';
			$args[$key] = $key;
			$row[$key] = self::getEscapeType($data);
			$valueArgs[] = $data;
		}
		if (!$multiple) {
			$rows[] = '('.implode(', ', $row).')';
		}
		$args = array_merge($args, $valueArgs);

		// Split for debugging
		$sql = "REPLACE INTO %n (".implode(', ', $queryKeys).") VALUES ".implode(', ', $rows).";";
		return self::$db->query($sql, $table, ...array_values($args))->count();
	}

	private static function getEscapeType(mixed $value) : string {
		return match (true) {
			is_int($value) => '%i',
			is_float($value) => '%f',
			is_string($value) => '%s',
			$value instanceof DateTimeInterface => '%dt',
			default => '%s',
		};
	}

	/**
	 * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query
	 *
	 * @return int
	 * @throws Exception
	 * @since 1.0
	 */
	public static function getAffectedRows() : int {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}
		return self::$db->getAffectedRows();
	}

}
