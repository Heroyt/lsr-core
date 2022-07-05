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
		self::$log = new Logger(LOG_DIR, 'db');
		$options = [
			'driver' => $config['Database']['DRIVER'] ?? 'mysqli',
		];
		if (!empty($config['Database']['HOST'])) {
			$options['host'] = $config['Database']['HOST'];
		}
		if (!empty($config['Database']['PORT'])) {
			$options['port'] = (int) $config['Database']['PORT'];
		}
		if (!empty($config['Database']['USER'])) {
			$options['username'] = $config['Database']['USER'];
		}
		if (!empty($config['Database']['PASS'])) {
			$options['password'] = $config['Database']['PASS'];
		}
		if (!empty($config['Database']['DATABASE'])) {
			$options['database'] = $config['Database']['DATABASE'];
		}
		if (!empty($config['Database']['COLLATE'])) {
			$options['charset'] = $config['Database']['COLLATE'];
		}
		if ($options['driver'] === 'sqlite') {
			$dbFile = $options['database'] ?? TMP_DIR.'db.db';
			if (!file_exists($dbFile)) {
				touch($dbFile);
			}
		}
		self::$db = new Connection($options);
		self::$db->getSubstitutes()->{''} = $config['Database']['PREFIX'] ?? '';
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
	 * @param string     $table
	 * @param iterable   $args
	 * @param array|null $where
	 *
	 * @return Fluent|int
	 *
	 * @throws Exception
	 * @since 1.0
	 */
	public static function update(string $table, iterable $args, array $where = null) : Fluent|int {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}
		$q = self::$db->update($table, $args);
		if (isset($where)) {
			$q = $q->where(...$where)->execute(dibi::AFFECTED_ROWS);
		}
		return $q;
	}

	/**
	 * Get query insert
	 *
	 * @param string   $table
	 * @param iterable $args
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
	 * @param string   $table
	 * @param iterable $args
	 *
	 * @return int
	 * @throws Exception
	 *
	 * @since 1.0
	 */
	public static function insert(string $table, iterable $args) : int {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}
		return self::$db->insert($table, $args)->execute(dibi::AFFECTED_ROWS);
	}

	/**
	 * Insert value with IGNORE flag enabled
	 *
	 * @param string   $table
	 * @param iterable $args
	 *
	 * @return int
	 * @throws Exception
	 */
	public static function insertIgnore(string $table, iterable $args) : int {
		if (!isset(self::$db)) {
			throw new RuntimeException('Database is not connected');
		}
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
	 * @param string $table
	 * @param array  $where
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
	 * @param array|string $table
	 * @param mixed        ...$args
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
	 * @param string        $table
	 * @param array[]|array $values
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