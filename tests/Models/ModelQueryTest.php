<?php

namespace Models;

use Dibi\Exception;
use Lsr\Core\DB;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use PHPUnit\Framework\TestCase;
use TestEnum;

/**
 * Test suite for Model queries
 *
 * @author  Tomáš Vojík
 * @covers  \Lsr\Core\Models\ModelQuery
 * @uses    \Lsr\Core\Models\Model
 * @uses    \Lsr\Core\DB
 */
class ModelQueryTest extends TestCase
{

	/**
	 * @return void
	 * @throws Exception
	 */
	public function setUp() : void {
		DB::init();
		DB::getConnection()->query("
			CREATE TABLE models ( 
			    id_model INTEGER PRIMARY KEY autoincrement NOT NULL, 
			    name CHAR(60) NOT NULL, 
			    age INT 
			);
		");
		DB::getConnection()->query("
			CREATE TABLE data ( 
			    id INTEGER PRIMARY KEY autoincrement NOT NULL, 
			    id_model INTEGER,
			    description CHAR(200) NOT NULL, 
			    model_type CHAR(1) NOT NULL
			);
		");
		$this->refreshData();
		parent::setUp();
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function refreshData() : void {
		DB::delete(TestingModel::TABLE, ['1 = 1']);
		DB::delete('data', ['1 = 1']);

		DB::insert(TestingModel::TABLE,
							 [
								 'name' => 'model1',
								 'age'  => 20,
							 ],
							 [
								 'name' => 'model2',
								 'age'  => 10,
							 ],
							 [
								 'name' => 'model3',
								 'age'  => 99,
							 ],
							 [
								 'name' => 'model4',
								 'age'  => null,
							 ],
		);
		DB::insert('data',
							 [
								 'id_model'    => 1,
								 'description' => 'aasda',
								 'model_type'  => 'A',
							 ],
							 [
								 'id_model'    => 2,
								 'description' => 'ahoj',
								 'model_type'  => 'B',
							 ],
							 [
								 'id_model'    => 1,
								 'description' => 'desc',
								 'model_type'  => 'C',
							 ],
		);
	}

	public function tearDown() : void {
		DB::close();
		unlink(TMP_DIR.'db.db');
		parent::tearDown();
	}

	public function testOffset() : void {
		$query = TestingModel::query()->offset(1);
		self::assertCount(3, $query->get());
	}

	public function testGet() : void {
		$query = TestingModel::query();
		$models = $query->get();
		self::assertCount(4, $models);
		foreach ($models as $model) {
			self::assertInstanceOf(TestingModel::class, $model);
			self::assertNotNull($model->id);
			self::assertSame($models[$model->id], $model);
		}
	}

	public function testOrderBy() : void {
		$query = TestingModel::query()->orderBy('age');
		$models = array_values($query->get());
		self::assertEquals(null, $models[0]->age);
		self::assertEquals(10, $models[1]->age);
		self::assertEquals(20, $models[2]->age);
		self::assertEquals(99, $models[3]->age);
	}

	public function testAsc() : void {
		$query = TestingModel::query()->orderBy('age')->asc();
		$models = array_values($query->get());
		self::assertEquals(null, $models[0]->age);
		self::assertEquals(10, $models[1]->age);
		self::assertEquals(20, $models[2]->age);
		self::assertEquals(99, $models[3]->age);

	}

	public function testJoin() : void {
		$models = TestingModel::query()
													->join('data', 'b')
													->on('a.id_model = b.id_model')
													->where('b.model_type = %s', 'C')
													->get();

		self::assertCount(1, $models);
		self::assertEquals(1, first($models)->id);

	}

	public function testDesc() : void {
		$query = TestingModel::query()->orderBy('age')->desc();
		$models = array_values($query->get());
		self::assertEquals(null, $models[3]->age);
		self::assertEquals(10, $models[2]->age);
		self::assertEquals(20, $models[1]->age);
		self::assertEquals(99, $models[0]->age);

	}

	public function testWhere() : void {
		$query = TestingModel::query()->where('age >= 20');
		$models = $query->get();
		self::assertCount(2, $models);
		self::assertEquals([1, 3], array_keys($models));

	}

	public function testCount() : void {
		$count = TestingModel::query()->count();
		self::assertEquals(4, $count);
	}

	public function testLimit() : void {
		$models = TestingModel::query()->limit(2)->get();
		self::assertCount(2, $models);

	}

	public function testFirst() : void {
		$model = TestingModel::query()->first();
		self::assertInstanceOf(TestingModel::class, $model);
		self::assertEquals(1, $model->id);
	}

	public function testFirstEmpty() : void {
		$model = TestingModel::query()->where('1 = 0')->first();
		self::assertNull($model);
	}
}


#[PrimaryKey('id_model')]
class TestingModel extends Model
{

	public const TABLE = 'models';

	public string $name;
	public ?int   $age = null;

	#[OneToMany(foreignKey: 'id', class: DataModel::class)]
	public array $data = [];

}

#[PrimaryKey('id')]
class DataModel extends Model
{

	public const TABLE = 'data';

	#[ManyToOne]
	public TestingModel $model;

	public string   $description;
	public TestEnum $model_type;

}