<?php

namespace TestCases\Models;

use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\LoadingType;
use Lsr\Core\Models\Model;
use PHPUnit\Framework\TestCase;

class ModelComplexRelationTest extends TestCase
{

	public function setUp(): void {
		DB::init([
			         'Database' => [
				         'DATABASE' => ROOT . "tests/tmp/dbc.db",
				         'DRIVER'   => "sqlite",
				         'PREFIX'   => "",
			         ],
		         ]);
		DB::getConnection()->query(
			"
			CREATE TABLE models_a ( 
			    id_model_a INTEGER PRIMARY KEY autoincrement NOT NULL , 
			    name CHAR(60) NOT NULL, 
			    id_model_b INT NOT NULL,
			    id_model_c INT NOT NULL 
			);
		"
		);
		DB::getConnection()->query(
			"
			CREATE TABLE models_b ( 
			    id_model_b INTEGER PRIMARY KEY autoincrement NOT NULL, 
			    name CHAR(60) NOT NULL
			);
		"
		);
		DB::getConnection()->query(
			"
			CREATE TABLE models_c ( 
			    id_model_c INTEGER PRIMARY KEY autoincrement NOT NULL,
			    name CHAR(60) NOT NULL,
			    id_model_b INT NOT NULL
			);
		"
		);

		$this->refreshData();

		foreach (glob(TMP_DIR . 'models/*') as $file) {
			unlink($file);
		}

		parent::setUp();
	}

	private function refreshData(): void {
		DB::delete(ModelCA::TABLE, ['1 = 1']);
		DB::delete(ModelCB::TABLE, ['1 = 1']);
		DB::delete(ModelCC::TABLE, ['1 = 1']);

		DB::insert(ModelCB::TABLE, [
			'id_model_b' => 1,
			'name'       => 'Parent1',
		]);
		DB::insert(ModelCB::TABLE, [
			'id_model_b' => 2,
			'name'       => 'Parent2',
		]);

		DB::insert(ModelCC::TABLE, [
			'id_model_c' => 1,
			'id_model_b' => 1,
			'name'       => 'Group1',
		]);
		DB::insert(ModelCC::TABLE, [
			'id_model_c' => 2,
			'id_model_b' => 1,
			'name'       => 'Group2',
		]);
		DB::insert(ModelCC::TABLE, [
			'id_model_c' => 3,
			'id_model_b' => 1,
			'name'       => 'Group3',
		]);

		DB::insert(ModelCC::TABLE, [
			'id_model_c' => 4,
			'id_model_b' => 2,
			'name'       => 'Group4',
		]);
		DB::insert(ModelCC::TABLE, [
			'id_model_c' => 5,
			'id_model_b' => 2,
			'name'       => 'Group5',
		]);

		DB::insert(ModelCA::TABLE, [
			'id_model_a' => 1,
			'name'       => 'Model1',
			'id_model_b' => 1,
			'id_model_c' => 1,
		]);
		DB::insert(ModelCA::TABLE, [
			'id_model_a' => 2,
			'name'       => 'Model2',
			'id_model_b' => 1,
			'id_model_c' => 1,
		]);
		DB::insert(ModelCA::TABLE, [
			'id_model_a' => 3,
			'name'       => 'Model3',
			'id_model_b' => 1,
			'id_model_c' => 1,
		]);

		DB::insert(ModelCA::TABLE, [
			'id_model_a' => 4,
			'name'       => 'Model4',
			'id_model_b' => 1,
			'id_model_c' => 2,
		]);
		DB::insert(ModelCA::TABLE, [
			'id_model_a' => 5,
			'name'       => 'Model5',
			'id_model_b' => 1,
			'id_model_c' => 2,
		]);

		DB::insert(ModelCA::TABLE, [
			'id_model_a' => 6,
			'name'       => 'Model6',
			'id_model_b' => 1,
			'id_model_c' => 3,
		]);

		DB::insert(ModelCA::TABLE, [
			'id_model_a' => 7,
			'name'       => 'Model7',
			'id_model_b' => 2,
			'id_model_c' => 4,
		]);

		DB::insert(ModelCA::TABLE, [
			'id_model_a' => 8,
			'name'       => 'Model8',
			'id_model_b' => 2,
			'id_model_c' => 5,
		]);
		DB::insert(ModelCA::TABLE, [
			'id_model_a' => 9,
			'name'       => 'Model9',
			'id_model_b' => 2,
			'id_model_c' => 6,
		]);

		App::getServiceByType(Cache::class)->clean([Cache::All => true]);
	}

	public function tearDown(): void {
		DB::close();
		unlink(TMP_DIR . 'dbc.db');
		App::getServiceByType(Cache::class)->clean([Cache::All => true]);
		parent::tearDown();
	}

	public function testModelA(): void {
		$model = ModelCA::get(1);

		self::assertEquals('Model1', $model->name);
		self::assertEquals(1, $model->parent->id);
		self::assertEquals('Parent1', $model->parent->name);

		self::assertFalse(isset($model->parentC));
		$model->getParentC();
		self::assertTrue(isset($model->parentC));
		self::assertEquals(1, $model->parentC->id);
		self::assertEquals('Group1', $model->parentC->name);
	}

	public function testModelB(): void {
		$model = ModelCB::get(1);

		self::assertEquals('Parent1', $model->name);

		self::assertCount(6, $model->children);
		self::assertCount(3, $model->childrenC);

		self::assertContains(ModelCA::get(1), $model->children);
		self::assertContains(ModelCA::get(2), $model->children);
		self::assertContains(ModelCA::get(3), $model->children);
		self::assertContains(ModelCA::get(4), $model->children);
		self::assertContains(ModelCA::get(5), $model->children);
		self::assertContains(ModelCA::get(6), $model->children);

		self::assertContains(ModelCC::get(1), $model->childrenC);
		self::assertContains(ModelCC::get(2), $model->childrenC);
		self::assertContains(ModelCC::get(3), $model->childrenC);

		$model = ModelCB::get(2);

		self::assertEquals('Parent2', $model->name);

		self::assertCount(3, $model->children);
		self::assertCount(2, $model->childrenC);

		self::assertContains(ModelCA::get(7), $model->children);
		self::assertContains(ModelCA::get(8), $model->children);
		self::assertContains(ModelCA::get(9), $model->children);

		self::assertContains(ModelCC::get(4), $model->childrenC);
		self::assertContains(ModelCC::get(5), $model->childrenC);
	}

	public function testModelC(): void {
		$model = ModelCC::get(1);

		self::assertEquals('Group1', $model->name);

		self::assertFalse(isset($model->children));
		self::assertCount(3, $model->getChildren());
		self::assertTrue(isset($model->children));

		self::assertCount(3, $model->children);

		self::assertContains(ModelCA::get(1), $model->children);
		self::assertContains(ModelCA::get(2), $model->children);
		self::assertContains(ModelCA::get(3), $model->children);
	}

}

#[PrimaryKey('id_model_a')]
class ModelCA extends Model
{

	public const TABLE = 'models_a';

	public string $name;

	#[ManyToOne]
	public ModelCB $parent;

	#[ManyToOne(loadingType: LoadingType::LAZY)]
	public ModelCC $parentC;

	public function getParentC(): ModelCC {
		$this->parentC ??= ModelCC::get($this->relationIds['parentC']);
		return $this->parentC;
	}

}

#[PrimaryKey('id_model_b')]
class ModelCB extends Model
{

	public const TABLE = 'models_b';

	public string $name;

	/** @var ModelCA[] */
	#[OneToMany(class: ModelCA::class)]
	public array $children = [];

	/** @var ModelCC[] */
	#[OneToMany(class: ModelCC::class)]
	public array $childrenC = [];

}

#[PrimaryKey('id_model_c')]
class ModelCC extends Model
{

	public const TABLE = 'models_c';

	public string $name;

	#[ManyToOne]
	public ModelCB $parent;

	#[OneToMany(class: ModelCA::class, loadingType: LoadingType::LAZY)]
	public array $children;

	public function getChildren(): array {
		$this->children ??= ModelCA::query()->where('%n = %i', $this::getPrimaryKey(), $this->id)->get();
		return $this->children;
	}

}
