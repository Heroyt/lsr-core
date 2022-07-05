<?php


use Dibi\DriverException;
use Dibi\Fluent;
use Lsr\Core\DB;
use PHPUnit\Framework\TestCase;

class DBTest extends TestCase
{
	public function tearDown() : void {
		DB::close();
		if (file_exists(TMP_DIR.'db.db')) {
			unlink(TMP_DIR.'db.db');
		}
		parent::tearDown();
	}

	public function testUninitializedSelect() {
		$this->expectException(RuntimeException::class);
		DB::select('table1', '*');
	}

	public function testUninitializedInsert() {
		$this->expectException(RuntimeException::class);
		DB::insert('table1', []);
	}

	public function testUninitializedInsertIgnore() {
		$this->expectException(RuntimeException::class);
		DB::insertIgnore('table1', []);
	}

	public function testUninitializedInsertGet() {
		$this->expectException(RuntimeException::class);
		DB::insertGet('table1', []);
	}

	public function testUninitializedUpdate() {
		$this->expectException(RuntimeException::class);
		DB::update('table1', []);
	}

	public function testUninitializedDelete() {
		$this->expectException(RuntimeException::class);
		DB::delete('table1', []);
	}

	public function testUninitializedDeleteGet() {
		$this->expectException(RuntimeException::class);
		DB::deleteGet('table1');
	}

	public function testUninitializedReplace() {
		$this->expectException(RuntimeException::class);
		DB::replace('table1', []);
	}

	public function testUninitializedGetInsertId() {
		$this->expectException(RuntimeException::class);
		DB::getInsertId();
	}

	public function testUninitializedResetAutoincrement() {
		$this->expectException(RuntimeException::class);
		DB::resetAutoIncrement('table');
	}

	public function testUninitializedGetAffectedRows() {
		$this->expectException(RuntimeException::class);
		DB::getAffectedRows();
	}

	public function testInsert() {
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
			    id INTEGER PRIMARY KEY autoincrement NOT NULL , 
			    name CHAR(60) NOT NULL, 
			    age INT 
			);
		");
	}

	public function dropTable() : void {
		DB::getConnection()->query("DROP TABLE table1");
	}

	public function testInsertIgnore() {
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
			    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, 
			    name VARCHAR(60) NOT NULL, 
			    age INT(10) UNSIGNED,
			    date DATETIME DEFAULT NULL,
			    PRIMARY KEY (`id`)
			);
		");
	}

	public function testGetAffectedRows() {
		$this->initMysql();
		$count = DB::insert('table1', [
			'name' => 'test1',
			'age'  => null
		]);
		self::assertEquals(1, DB::getAffectedRows());
		$this->dropTable();
	}

	public function testInit() {
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

	public function testResetAutoIncrement() {
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

	public function testInsertGet() {
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

	public function testUpdate() {
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

	public function testDeleteGet() {
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

	public function testDelete() {
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

	public function testReplace() {
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
}
