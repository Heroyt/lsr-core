<?php

namespace TestCases;

use DateTime;
use Dibi\DriverException;
use Dibi\Fluent;
use Lsr\Core\DB;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Database abstraction test suite
 *
 * @author Tomáš Vojík
 * @covers \Lsr\Core\DB
 */
class DBTest extends TestCase
{
	public function tearDown() : void {
		DB::close();
		if (file_exists(TMP_DIR.'db.db')) {
			unlink(TMP_DIR.'db.db');
		}
		parent::tearDown();
	}

	public function testUninitializedSelect() : void {
		$this->expectException(RuntimeException::class);
		DB::select('table1', '*');
	}

	public function testUninitializedInsert() : void {
		$this->expectException(RuntimeException::class);
		DB::insert('table1', []);
	}

	public function testUninitializedInsertIgnore() : void {
		$this->expectException(RuntimeException::class);
		DB::insertIgnore('table1', []);
	}

	public function testUninitializedInsertGet() : void {
		$this->expectException(RuntimeException::class);
		DB::insertGet('table1', []);
	}

	public function testUninitializedUpdate() : void {
		$this->expectException(RuntimeException::class);
		DB::update('table1', []);
	}

	public function testUninitializedDelete() : void {
		$this->expectException(RuntimeException::class);
		DB::delete('table1', []);
	}

	public function testUninitializedDeleteGet() : void {
		$this->expectException(RuntimeException::class);
		DB::deleteGet('table1');
	}

	public function testUninitializedReplace() : void {
		$this->expectException(RuntimeException::class);
		DB::replace('table1', []);
	}

	public function testUninitializedGetInsertId() : void {
		$this->expectException(RuntimeException::class);
		DB::getInsertId();
	}

	public function testUninitializedResetAutoincrement() : void {
		$this->expectException(RuntimeException::class);
		DB::resetAutoIncrement('table');
	}

	public function testUninitializedGetAffectedRows() : void {
		$this->expectException(RuntimeException::class);
		DB::getAffectedRows();
	}

	public function testInsert() : void {
		$this->initSqlite();
		$count = DB::insert('table1', [
			'name' => 'test1',
			'age'  => null
		]);
		self::assertEquals(1, $count);
		$this->dropTable();
	}

	public function initSqlite() : void {
		DB::init();
		$this->initSqliteTable();
	}

	public function initSqliteTable() : void {
		DB::getConnection()->query("
			CREATE TABLE table1 ( 
			    id integer PRIMARY KEY autoincrement NOT NULL , 
			    name char(60) NOT NULL, 
			    age int 
			);
		");
		DB::getConnection()->query("
			CREATE TABLE table2 ( 
			    id integer PRIMARY KEY autoincrement NOT NULL ,
			    table_1_id integer,
			    name varchar(60) NOT NULL 
			);
		");
	}

	public function dropTable() : void {
		DB::getConnection()->query("DROP TABLE table2");
		DB::getConnection()->query("DROP TABLE table1");
	}

	public function testInsertMultiple() : void {
		$this->initSqlite();
		$count = DB::insert('table1',
												[
													'name' => 'test1',
													'age'  => null
												],
												[
													'name' => 'test2',
													'age'  => 10
												],
												[
													'name' => 'test3',
													'age'  => 99
												]
		);
		self::assertEquals(3, DB::select('table1', 'count(*)')->fetchSingle());
		self::assertEquals(3, $count);
		$this->dropTable();
	}

	public function testInsertIgnore() : void {
		$this->initMysql();
		$count = DB::insert('table1', [
			'name' => 'test1',
			'age'  => null
		]);
		$id = DB::getInsertId();
		self::assertEquals(1, $count);
		$count = DB::insertIgnore('table1', [
			'id'   => $id,
			'name' => 'test2'
		]);
		self::assertEquals(0, $count);
		$this->dropTable();
	}

	public function initMysql() : void {
		DB::init([
							 'Database' => [
								 'HOST'     => 'localhost',
								 'USER'     => 'root',
								 'PASS'     => '',
								 'DRIVER'   => 'mysqli',
								 'DATABASE' => 'test',
								 'PORT'     => 3306,
								 'COLLATE'  => 'utf8mb4'
							 ],
						 ]);
		$this->initMysqlTable();
	}

	public function initMysqlTable() : void {
		DB::getConnection()->query("
			CREATE TABLE IF NOT EXISTS table1 ( 
			    id int(11) UNSIGNED NOT NULL AUTO_INCREMENT, 
			    name varchar(60) NOT NULL, 
			    age int(10) UNSIGNED,
			    date datetime DEFAULT NULL,
			    PRIMARY KEY (`id`)
			);
		");
		DB::getConnection()->query("
			CREATE TABLE IF NOT EXISTS table2 ( 
			    id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			    table_1_id int(11) UNSIGNED DEFAULT NULL,
			    name varchar(60) NOT NULL, 
			    PRIMARY KEY (`id`),
			    CONSTRAINT table_1_fk FOREIGN KEY (`table_1_id`) REFERENCES table1 (id) ON DELETE SET NULL 
			);
		");
	}

	public function testGetAffectedRows() : void {
		$this->initMysql();
		$count = DB::insert('table1', [
			'name' => 'test1',
			'age'  => null
		]);
		self::assertEquals(1, DB::getAffectedRows());
		$this->dropTable();
	}

	public function testInit() : void {
		// Init SQLite
		$this->initSqlite();
		$this->dropTable();
		self::assertTrue(DB::getConnection()->isConnected());
		DB::close();
		self::assertFalse(DB::getConnection()->isConnected());

		// Init MySQL
		$this->initMysql();
		$this->dropTable();
		self::assertTrue(DB::getConnection()->isConnected());
		DB::close();
		self::assertFalse(DB::getConnection()->isConnected());

		// Init MySQL - invalid login
		$this->expectException(DriverException::class);
		DB::init([
							 'Database' => [
								 'HOST'     => 'localhost',
								 'USER'     => 'root',
								 'PASS'     => 'invalid',
								 'DRIVER'   => 'mysqli',
								 'DATABASE' => 'test',
								 'PORT'     => 3306,
								 'COLLATE'  => 'utf8mb4'
							 ],
						 ]);
	}

	public function testResetAutoIncrement() : void {
		$this->initMysql();
		DB::insert('table1', [
			'name' => 'test1',
			'age'  => null
		]);
		DB::insert('table1', [
			'name' => 'test2',
			'age'  => 10
		]);
		DB::delete('table1');
		DB::insert('table1', [
			'name' => 'test1',
			'age'  => null
		]);
		self::assertEquals(3, DB::getInsertId());
		DB::delete('table1');
		DB::resetAutoIncrement('table1');
		DB::insert('table1', [
			'name' => 'test1',
			'age'  => null
		]);
		self::assertEquals(1, DB::getInsertId());
		$this->dropTable();
	}

	public function testInsertGet() : void {
		$this->initSqlite();
		$query = DB::insertGet('table1', [
			'name' => 'test1',
			'age'  => null
		]);
		self::assertInstanceOf(Fluent::class, $query);
		$count = $query->execute();
		self::assertEquals(1, $count->count());
		$this->dropTable();
	}

	public function testUpdate() : void {
		$this->initSqlite();
		DB::insert('table1', [
			'name' => 'test1',
			'age'  => null
		]);
		$id = DB::getInsertId();
		$count = DB::update('table1', [
			'name' => 'hello!'
		],                  [
													'id = %i',
													$id
												]);
		self::assertTrue(is_int($count));
		self::assertEquals(1, $count);
		$row = DB::select('table1', '*')->where('id = %i', $id)->fetch();
		self::assertEquals('hello!', $row->name);
		self::assertEquals(null, $row->age);

		$query = DB::update('table1', [
			'name' => 'hello!'
		]);
		self::assertInstanceOf(Fluent::class, $query);
		$query->execute();
		$row = DB::select('table1', '*')->where('id = %i', $id)->fetch();
		self::assertEquals('hello!', $row->name);
		self::assertEquals(null, $row->age);
		$this->dropTable();
	}

	public function testDeleteGet() : void {
		$this->initSqlite();
		DB::insert('table1', [
			'name' => 'test1',
			'age'  => null
		]);
		$id = DB::getInsertId();
		$query = DB::deleteGet('table1')->where('id = %i', $id)->execute();
		self::assertEquals(1, $query->count());
		$this->dropTable();
	}

	public function testDelete() : void {
		$this->initSqlite();
		DB::insert('table1', [
			'name' => 'test1',
			'age'  => null
		]);
		$id = DB::getInsertId();
		$count = DB::delete('table1', ['id = %i', $id]);
		self::assertEquals(1, $count);
		$this->dropTable();
	}

	public function testReplace() : void {
		$this->initMysql();
		DB::insert('table1', [
			'name' => 'test1',
			'age'  => null
		]);
		$id = DB::getInsertId();
		DB::replace('table1', [
			'id'   => $id,
			'name' => 'name',
			'age'  => 1,
		]);
		$row = DB::select('table1', '*')->where('id = %i', $id)->fetch();
		self::assertEquals('name', $row->name);
		self::assertEquals(1, $row->age);
		DB::replace('table1', [
			[
				'id'   => $id,
				'name' => 'name2',
				'age'  => 12,
				'date' => null,
			],
			[
				'id'   => $id + 1,
				'name' => 'abc',
				'age'  => 30,
				'date' => new DateTime('now')
			],
		]);
		$row = DB::select('table1', '*')->where('id = %i', $id)->fetch();
		self::assertEquals('name2', $row->name);
		self::assertEquals(12, $row->age);
		$row = DB::select('table1', '*')->where('id = %i', $id + 1)->fetch();
		self::assertEquals('abc', $row->name);
		self::assertEquals(30, $row->age);
		$this->dropTable();
	}

	/**
	 * @depends testInsert
	 * @return void
	 */
	public function testSelect() : void {
		$this->initMysql();
		DB::insert('table1', [
			'name' => 'test1',
			'age'  => null
		]);
		$id1 = DB::getInsertId();
		DB::insert('table1', [
			'name' => 'test2',
			'age'  => 12
		]);
		$id2 = DB::getInsertId();
		DB::insert('table2', [
			'name'       => 'test3',
			'table_1_id' => $id1,
		]);
		$id3 = DB::getInsertId();
		DB::insert('table2', [
			'name'       => 'test4',
			'table_1_id' => null,
		]);
		$id4 = DB::getInsertId();

		// Simple select
		$rows = DB::select('table1', '*')->fetchAll();
		self::assertCount(2, $rows);

		// Join select with alias
		$rows = DB::select(['table1', 'a'], 'a.id, a.name, a.age, b.name as value')
			->join('table2', 'b')
			->on('a.id = b.table_1_id')
			->fetchAll();
		self::assertCount(1, $rows);
		$row = first($rows);
		self::assertEquals($id1, $row->id);
		self::assertEquals('test1', $row->name);
		self::assertEquals(null, $row->age);
		self::assertEquals('test3', $row->value);

		$this->dropTable();
	}
}