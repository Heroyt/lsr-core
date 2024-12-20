<?php
/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUndefinedFieldInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace TestCases\Models;

use Dibi\Row;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\ManyToMany;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Email;
use Lsr\Core\Models\Attributes\Validation\Numeric;
use Lsr\Core\Models\Attributes\Validation\Required;
use Lsr\Core\Models\Attributes\Validation\StringLength;
use Lsr\Core\Models\Attributes\Validation\Uri;
use Lsr\Core\Models\Attributes\Validation\Url;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;
use Lsr\Core\Models\LoadingType;
use Lsr\Core\Models\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TestEnum;
use function json_encode;


/**
 * Test suite for models
 *
 * @author Tomáš Vojík
 */
#[CoversClass(Url::class)]
#[CoversClass(Uri::class)]
#[CoversClass(Email::class)]
#[CoversClass(StringLength::class)]
#[CoversClass(Required::class)]
#[CoversClass(Numeric::class)]
#[CoversClass(PrimaryKey::class)]
#[CoversClass(ManyToOne::class)]
#[CoversClass(OneToMany::class)]
#[CoversClass(ManyToMany::class)]
#[CoversClass(Model::class)]
#[UsesClass(DB::class)]
class ModelTest extends TestCase
{

    private Cache $cache;

    public function setUp() : void {
        // @phpstan-ignore-next-line
        $this->cache = App::getServiceByType(Cache::class);
        DB::init(
          [
            'Database' => [
              'DATABASE' => ROOT."tests/tmp/db.db",
              'DRIVER'   => "sqlite",
              'PREFIX'   => "",
            ],
          ]
        );
        DB::getConnection()->query(
          "
			CREATE TABLE modelsA ( 
			    model_a_id INTEGER PRIMARY KEY autoincrement NOT NULL , 
			    name CHAR(60) NOT NULL, 
			    age INT 
			);
		"
        );
        DB::getConnection()->query(
          "
			CREATE TABLE modelsB ( 
			    model_b_id INTEGER PRIMARY KEY autoincrement NOT NULL, 
			    description CHAR(200) NOT NULL, 
			    model_type CHAR(1) NOT NULL, 
			    model_a_id INT 
			);
		"
        );
        DB::getConnection()->query(
          "
			CREATE TABLE modelsC ( 
			    model_c_id INTEGER PRIMARY KEY autoincrement NOT NULL,
			    value0 CHAR(50) NOT NULL, 
			    value1 CHAR(50) NOT NULL, 
			    value2 CHAR(50) NOT NULL
			);
		"
        );
        DB::getConnection()->query(
          "
			CREATE TABLE modelsD ( 
			    model_d_id INTEGER PRIMARY KEY autoincrement NOT NULL,
			    name CHAR(50) NOT NULL
			);
		"
        );
        DB::getConnection()->query(
          "
			CREATE TABLE modelsE ( 
			    model_e_id INTEGER PRIMARY KEY autoincrement NOT NULL,
			    name CHAR(50) NOT NULL
			);
		"
        );
        DB::getConnection()->query(
          "
			CREATE TABLE modelsD_modelsE ( 
			    model_d_id INTEGER NOT NULL,
			    model_e_id INTEGER NOT NULL,
			  	PRIMARY KEY(model_d_id, model_e_id)
			);
		"
        );
        DB::getConnection()->query(
          "
			CREATE TABLE model_invalid_instantiate ( 
			    id_model INTEGER PRIMARY KEY autoincrement NOT NULL
			);
		"
        );
        $this->refreshData();

        $files = glob(TMP_DIR.'models/*');
        assert($files !== false);
        foreach ($files as $file) {
            unlink($file);
        }

        parent::setUp();
    }

    public function refreshData() : void {
        DB::delete(ModelA::TABLE, ['1 = 1']);
        DB::delete(ModelB::TABLE, ['1 = 1']);
        DB::delete(ModelC::TABLE, ['1 = 1']);
        DB::delete(ModelD::TABLE, ['1 = 1']);
        DB::delete(ModelE::TABLE, ['1 = 1']);
        DB::delete(ModelInvalidInstantiate::TABLE, ['1 = 1']);
        DB::delete('modelsD_modelsE', ['1 = 1']);

        DB::insert(
          ModelA::TABLE,
          [
            'model_a_id' => 1,
            'name'       => 'model1',
            'age'        => 20,
          ]
        );

        DB::insert(
          ModelA::TABLE,
          [
            'model_a_id' => 2,
            'name'       => 'model2',
            'age'        => null,
          ]
        );

        DB::insert(
          ModelB::TABLE,
          [
            'model_b_id'  => 1,
            'description' => 'Lorem ipsum',
            'model_type'  => 'A',
            'model_a_id'  => 1,
          ]
        );
        DB::insert(
          ModelB::TABLE,
          [
            'model_b_id'  => 2,
            'description' => 'Lorem ipsumaaaaa',
            'model_type'  => 'A',
            'model_a_id'  => 1,
          ]
        );
        DB::insert(
          ModelB::TABLE,
          [
            'model_b_id'  => 3,
            'description' => 'Lorem ipsumbbbbbb',
            'model_type'  => 'C',
            'model_a_id'  => 2,
          ]
        );
        DB::insert(
          ModelB::TABLE,
          [
            'model_b_id'  => 4,
            'description' => 'Lorem dasmdlsakdnad',
            'model_type'  => 'D',
            'model_a_id'  => null,
          ]
        );
        DB::insert(
          ModelC::TABLE,
          [
            'model_c_id' => 1,
            'value0'     => 'value0',
            'value1'     => 'value1',
            'value2'     => 'value2',
          ]
        );
        DB::insert(
          ModelC::TABLE,
          [
            'model_c_id' => 2,
            'value0'     => 'a',
            'value1'     => 'b',
            'value2'     => 'c',
          ]
        );

        DB::insert(
          ModelE::TABLE,
          [
            'model_e_id' => 1,
            'name'       => 'a',
          ]
        );
        DB::insert(
          ModelE::TABLE,
          [
            'model_e_id' => 2,
            'name'       => 'b',
          ]
        );
        DB::insert(
          ModelE::TABLE,
          [
            'model_e_id' => 3,
            'name'       => 'c',
          ]
        );

        DB::insert(
          ModelD::TABLE,
          [
            'model_d_id' => 1,
            'name'       => 'a',
          ]
        );
        DB::insert(
          ModelD::TABLE,
          [
            'model_d_id' => 2,
            'name'       => 'b',
          ]
        );
        DB::insert(
          ModelD::TABLE,
          [
            'model_d_id' => 3,
            'name'       => 'c',
          ]
        );
        DB::insert(
          ModelInvalidInstantiate::TABLE,
          [
            'id_model' => 1,
          ]
        );

        DB::insert(
          'modelsD_modelsE',
          [
            'model_d_id' => 1,
            'model_e_id' => 1,
          ]
        );
        DB::insert(
          'modelsD_modelsE',
          [
            'model_d_id' => 1,
            'model_e_id' => 2,
          ]
        );
        DB::insert(
          'modelsD_modelsE',
          [
            'model_d_id' => 1,
            'model_e_id' => 3,
          ]
        );
        DB::insert(
          'modelsD_modelsE',
          [
            'model_d_id' => 2,
            'model_e_id' => 1,
          ]
        );
        DB::insert(
          'modelsD_modelsE',
          [
            'model_d_id' => 2,
            'model_e_id' => 3,
          ]
        );
        DB::insert(
          'modelsD_modelsE',
          [
            'model_d_id' => 3,
            'model_e_id' => 1,
          ]
        );
        $this->cache->clean([Cache::All => true]);
    }

    public function tearDown() : void {
        DB::close();
        unlink(TMP_DIR.'db.db');
        $this->cache->clean([Cache::All => true]);
        parent::tearDown();
    }

    public function testConstruct() : void {
        $this->refreshData();

        // Test row only
        /** @var Row|null $row */
        $row = DB::select(ModelA::TABLE, '*')->where('%n = %i', ModelA::getPrimaryKey(), 1)->fetch();
        $model = new ModelA(dbRow: $row);
        self::assertEquals(1, $model->id);
        self::assertEquals('model1', $model->name);
        self::assertEquals(20, $model->age);

        // Test row only without ID
        unset($row->model_a_id);
        $model = new ModelA(dbRow: $row);
        self::assertEquals(null, $model->id);
        self::assertEquals('model1', $model->name);
        self::assertEquals(20, $model->age);
    }

    public function testFetch() : void {
        $this->refreshData();
        $model = new ModelA();
        $model->id = 1;
        $model->fetch();
        self::assertEquals('model1', $model->name);
        self::assertEquals(20, $model->age);
    }

    public function testInvalidFetch() : void {
        $model = new ModelA();

        $this->expectException(RuntimeException::class);
        $model->fetch();
    }

    public function testGet() : void {
        $this->refreshData();
        $model1 = ModelA::get(1);

        self::assertEquals('model1', $model1->name);
        self::assertEquals(20, $model1->age);

        // Check if DB is correctly initialized
        self::assertEquals(2, DB::select(ModelB::TABLE, '*')->where('model_a_id = %i', 1)->count(cache: false));
        self::assertCount(2, $model1->children);

        $model2 = ModelB::get(1);

        self::assertEquals('Lorem ipsum', $model2->description);
        self::assertEquals(TestEnum::A, $model2->modelType);
        self::assertSame($model1, $model2->parent);

        $model3 = ModelB::get(4);

        self::assertEquals('Lorem dasmdlsakdnad', $model3->description);
        self::assertEquals(TestEnum::D, $model3->modelType);
        self::assertNull($model3->parent);

        $model4 = ModelC::get(1);

        self::assertEquals('value0', $model4->value0);
        self::assertEquals('value1', $model4->data->value1);
        self::assertEquals('value2', $model4->data->value2);
    }

    public function testRelations() : void {
        $parent = ModelA::get(1);

        $modelEager = ModelB::get(1);
        $modelLazy = ModelBLazy::get(1);

        self::assertTrue(isset($modelEager->parent));
        self::assertFalse(isset($modelLazy->parent));

        self::assertEquals($modelEager->parent, $parent);
        self::assertEquals($modelLazy->getParent(), $parent);
        self::assertTrue(isset($modelLazy->parent));
    }

    public function testRelationsSave() : void {
        $parent1 = ModelA::get(1);
        $parent2 = ModelA::get(2);

        $modelEager = ModelB::get(1);
        $modelLazy = ModelBLazy::get(2);

        self::assertTrue(isset($modelEager->parent));
        self::assertFalse(isset($modelLazy->parent));

        // Eager model should update its relation normally
        $modelEager->parent = $parent2;
        $data = $modelEager->getQueryData();
        self::assertEquals(2, $data['model_a_id']);
        self::assertArrayHasKey('model_a_id', $data);
        self::assertTrue($modelEager->save());
        $testId = DB::select(ModelB::TABLE, 'model_a_id')->where('model_b_id = %i', 1)->fetchSingle(cache: false);
        self::assertEquals(2, $testId);

        // Save without setting any parent (parent parameter is not set) should not change its value
        $data = $modelLazy->getQueryData();
        // @phpstan-ignore argument.type
        self::assertArrayNotHasKey('model_a_id', $data, json_encode($data));
        self::assertTrue($modelLazy->save());
        $testId = DB::select(ModelBLazy::TABLE, 'model_a_id')->where('model_b_id = %i', 2)->fetchSingle(cache: false);
        self::assertEquals(1, $testId);

        // After setting the value, it should behave as expected
        $modelLazy->parent = $parent2;
        $data = $modelLazy->getQueryData();
        self::assertArrayHasKey('model_a_id', $data);
        self::assertEquals(2, $data['model_a_id']);
        self::assertTrue($modelLazy->save());
        $testId = DB::select(ModelBLazy::TABLE, 'model_a_id')->where('model_b_id = %i', 2)->fetchSingle(cache: false);
        self::assertEquals(2, $testId);
    }

    public function testGetAll() : void {
        $this->refreshData();

        $models = ModelA::getAll();

        self::assertCount(2, $models);
        self::assertTrue(isset($models[1]));
        self::assertInstanceOf(ModelA::class, $models[1]);
        self::assertEquals(1, $models[1]->id);
        self::assertEquals('model1', $models[1]->name);
        self::assertEquals(20, $models[1]->age);
        self::assertCount(2, $models[1]->children);

        self::assertTrue(isset($models[2]));
        self::assertInstanceOf(ModelA::class, $models[2]);
        self::assertEquals(2, $models[2]->id);
        self::assertEquals('model2', $models[2]->name);
        self::assertEquals(null, $models[2]->age);
        self::assertCount(1, $models[2]->children);
    }

    public function testRepetitiveGet() : void {
        $this->refreshData();
        $model1 = ModelA::get(1);
        $model2 = ModelA::get(1);

        self::assertSame($model1, $model2);
    }

    public function testGetQueryData() : void {
        $model = new ModelA();
        $model->name = 'test';
        $model->age = 10;

        self::assertEquals(['name' => 'test', 'age' => 10], $model->getQueryData());

        $model->id = 99;

        $model2 = new ModelB();
        $model2->description = 'abcd';
        $model2->parent = $model;
        $model2->modelType = TestEnum::C;
        self::assertEquals(
          ['description' => 'abcd', 'model_a_id' => 99, 'model_type' => TestEnum::C->value],
          $model2->getQueryData()
        );

        $model3 = new ModelC();
        $model3->value0 = 'a';
        $model3->data = new SimpleData(
          'b',
          'c'
        );
        self::assertEquals(['value0' => 'a', 'value1' => 'b', 'value2' => 'c'], $model3->getQueryData());
    }

    public function testSave() : void {
        $model = new ModelA();
        $model->name = 'test';
        $model->age = 10;

        self::assertTrue($model->save());

        // Insert successful
        self::assertNotNull($model->id);

        // Check object caching
        self::assertSame($model, ModelA::get($model->id));

        // Check DB
        $row = DB::select(ModelA::TABLE, '*')->where('model_a_id = %i', $model->id)->fetch();
        self::assertNotNull($row);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->id, $row->model_a_id);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->name, $row->name);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->age, $row->age);

        // Update
        $model->age = 21;

        self::assertTrue($model->save());

        // Check DB
        $row = DB::select(ModelA::TABLE, '*')->where('model_a_id = %i', $model->id)->fetch(cache: false);

        self::assertNotNull($row);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->id, $row->model_a_id);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->name, $row->name);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->age, $row->age);
    }

    public function testInsertInvalid() : void {
        $model = new ModelInvalid();
        $model->column1 = 'asda';
        $model->column2 = 'asda';

        self::assertFalse($model->save());
    }

    public function testUpdateInvalid() : void {
        $model = new ModelInvalid();
        $model->column1 = 'asda';
        $model->column2 = 'asda';

        self::assertFalse($model->update());
        $model->id = 1;
        self::assertFalse($model->update());
    }

    public function testUpdate() : void {
        $model = ModelA::get(1);

        $model->name = 'testUpdate';

        self::assertTrue($model->save());

        // Check DB
        $row = DB::select(ModelA::TABLE, '*')->where('model_a_id = %i', $model->id)->fetch();
        self::assertNotNull($row);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->id, $row->model_a_id);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->name, $row->name);
        /** @phpstan-ignore-next-line */
        self::assertEquals($model->age, $row->age);
    }

    public function testArrayAccess() : void {
        $model = ModelA::get(1);

        // Test get
        self::assertEquals($model->name, $model['name']);
        self::assertEquals($model->age, $model['age']);
        self::assertNull($model['adsd']);

        // Test set
        $model['name'] = 'test set';
        self::assertEquals($model->name, $model['name']);

        // Test isset
        self::assertTrue(isset($model['name']));
        self::assertFalse(isset($model['asdas']));
    }

    public function testJsonSerialize() : void {
        $model = ModelA::get(1);
        $expected = [
          'id'       => $model->id,
          'name'     => $model->name,
          'age'      => $model->age,
          'children' => $model->children,
        ];

        // Test data
        self::assertEquals($expected, $model->jsonSerialize());

        // Prevent recursion
        $model->children = [];
        $expected['children'] = [];

        // Test encoded
        self::assertEquals(json_encode($expected, JSON_THROW_ON_ERROR), json_encode($model, JSON_THROW_ON_ERROR));
    }

    public function testPrimaryKeyGetting() : void {
        self::assertEquals('model_a_id', ModelA::getPrimaryKey());
        self::assertEquals('model_b_id', ModelB::getPrimaryKey());
        self::assertEquals('id', ModelInvalid::getPrimaryKey());
        self::assertEquals('id_model_pk1', ModelPk1::getPrimaryKey());
        self::assertEquals('model_pk2_id', ModelPk2::getPrimaryKey());
    }

    public function testExists() : void {
        $this->refreshData();
        self::assertTrue(ModelA::exists(1));
        self::assertTrue(ModelA::exists(2));
        self::assertFalse(ModelA::exists(3));
        self::assertFalse(ModelA::exists(4));
    }

    public function testDelete() : void {
        $this->refreshData();
        $model = ModelA::get(1);

        self::assertTrue($model->delete());

        self::assertNull(
          DB::select(ModelA::TABLE, '*')->where('%n = %i', ModelA::getPrimaryKey(), 1)->fetch(cache: false)
        );
        unset($model);

        $this->expectException(ModelNotFoundException::class);
        ModelA::get(1);
    }

    public function testDelete2() : void {
        $model = new ModelInvalid();

        self::assertFalse($model->delete());

        $model->id = 10;

        self::assertFalse($model->delete());
    }

    public function testManyToMany() : void {
        $model = ModelD::get(1);

        self::assertEquals('a', $model->name);
        self::assertCount(3, $model->models);

        $model2 = ModelE::get(2);
        self::assertEquals('b', $model2->name);
        self::assertCount(1, $model2->models);

        self::assertSame($model2, $model->models[2]);
        self::assertSame($model, $model2->models[1]);
    }

    #[DoesNotPerformAssertions]
    public function testValidationRequired() : void {
        $model = new ModelValidation();

        $model->requiredString = 'test';
        $model->validate();
    }

    public function testValidationRequiredInvalid() : void {
        $model = new ModelValidation();

        $this->expectException(ValidationException::class);
        $model->validate();
    }

    public function testValidationRequiredInvalid3() : void {
        $model = new Required();

        $this->expectException(ValidationException::class);
        $model->validateValue(null, ModelValidation::class, 'test');
    }

    public function testValidationRequiredInvalid2() : void {
        $model = new ModelValidation();
        $model->requiredString = null;
        $this->expectException(ValidationException::class);
        $model->validate();
    }

    #[Depends('testValidationRequiredInvalid')]
    #[Depends('testValidationRequired')]
    #[DoesNotPerformAssertions]
    public function testValidationStringLength() : void {
        $model = new ModelValidation();

        $model->requiredString = '';

        $model->stringLength10 = '1234567890';
        $model->validate();
        $model->stringLengthMax10 = '1234567890';
        $model->validate();
        $model->stringLengthMax10 = '123456789';
        $model->validate();
        $model->stringLengthMax10 = '12345';
        $model->validate();
        $model->stringLengthMax10 = '1';
        $model->validate();
        $model->stringLengthMax10 = '';
        $model->validate();
        $model->stringLengthBetween5and10 = '12345';
        $model->validate();
        $model->stringLengthBetween5and10 = '123456';
        $model->validate();
        $model->stringLengthBetween5and10 = '12345678';
        $model->validate();
        $model->stringLengthBetween5and10 = '1234567890';
        $model->validate();
        $model->notTypedString = '';
        $model->validate();
    }

    #[Depends('testValidationRequired')]
    #[Depends('testValidationRequiredInvalid')]
    #[TestWith([
      "stringLength10",
      "123",
      "stringLength10",
      "12345678901",
      "stringLengthMax10",
      "12345678901",
      "stringLengthMax10",
      "1234567890123",
      "stringLengthBetween5and10",
      "123",
      "stringLengthBetween5and10",
      "1",
      "stringLengthBetween5and10",
      "123456789012",
      "stringLengthBetween5and10",
      "1234567890123",
      "notTypedString",
      123,
      "notTypedString",
      123.14
    ])]
    public function testValidationStringInvalid(string $property, mixed $value) : void {
        $model = new ModelValidation();

        $model->requiredString = '';

        $this->expectException(ValidationException::class);
        $model->$property = $value;
        $model->validate();
    }

    #[DoesNotPerformAssertions]
    public function testValidationNumeric() : void {
        $model = new ModelValidation();
        $model->requiredString = 'asd';

        $model->numeric = 123;
        $model->validate();

        $model->numeric = '200';
        $model->validate();

        $model->numeric = 12.12;
        $model->validate();

        $model->numeric = '12.12';
        $model->validate();
    }

    #[TestWith(["hi", "asda", "123n"])]
    public function testValidationNumericInvalid(string | int | float $value) : void {
        $model = new ModelValidation();
        $model->requiredString = 'asd';

        $this->expectException(ValidationException::class);
        $model->numeric = $value;
        $model->validate();
    }

    #[TestWith(["asd@dasd.asd", "dsadij.dsa@sdaasd.od", "m.m123_13@dasd.asd.asd"])]
    #[DoesNotPerformAssertions]
    public function testValidationEmail(string $value) : void {
        $model = new ModelValidation();
        $model->requiredString = 'asd';

        $model->email = $value;
        $model->validate();
    }

    #[TestWith([
      "asdasd.asd",
      "dsadij",
      "sda@.asd.asd",
      "@asd.aa",
      "asda.@asd.as",
      "mysite()*@gmail.com",
      "mysite..1234@yahoo.com"
    ])]
    public function testValidationEmailInvalid(string $value) : void {
        $model = new ModelValidation();
        $model->requiredString = 'asd';

        $this->expectException(ValidationException::class);
        $model->email = $value;
        $model->validate();
    }

    #[TestWith([
      "http://google.com/",
      "http://localhost",
      "http://example.w3.org/path%20with%20spaces.html",
      "http://example.w3.org/%20",
      "ftp://ftp.is.co.za/rfc/rfc1808.txt",
      "ftp://ftp.is.co.za/../../../rfc/rfc1808.txt",
      "http://www.ietf.org/rfc/rfc2396.txt",
      "ldap://, 2001:db8::7, /c=GB?objectClass?one",
      "mailto:John.Doe@example.com",
      "news:comp.infosystems.www.servers.unix",
      "tel:+1-816-555-1212",
      "telnet://192.0.2.16:80",
      "urn:oasis:names:specification:docbook:dtd:xml:4.1.2"
    ])]
    #[DoesNotPerformAssertions]
    public function testValidationUri(string $value) : void {
        $model = new ModelValidation();
        $model->requiredString = 'asd';

        $model->uri = $value;
        $model->validate();
    }

    #[TestWith(["", "foo", "foo@bar", "://foo/"])]
    public function testValidationUriInvalid(string $value) : void {
        $model = new ModelValidation();
        $model->requiredString = 'asd';

        $model->uri = $value;
        $this->expectException(ValidationException::class);
        $model->validate();
    }

    #[TestWith([
      "http://google.com/",
      "http://localhost",
      "http://example.w3.org/path%20with%20spaces.html",
      "http://example.w3.org/%20",
      "http://www.ietf.org/rfc/rfc2396.txt"
    ])]
    #[DoesNotPerformAssertions]
    public function testValidationUrl(string $value) : void {
        $model = new ModelValidation();
        $model->requiredString = 'asd';

        $model->url = $value;
        $model->validate();
    }

    #[TestWith([
      "",
      "foo",
      "foo@bar",
      "=>//foo/",
      "ldap=>//, 2001=>db8=>=>7, /c=GB?objectClass?one",
      "mailto=>John.Doe@example.com",
      "news=>comp.infosystems.www.servers.unix",
      "tel=>+1-816-555-1212",
      "telnet=>//192.0.2.16=>80",
      "urn=>oasis=>names=>specification=>docbook=>dtd=>xml=>4.1.2"
    ])]
    public function testValidationUrlInvalid(string $value) : void {
        $model = new ModelValidation();
        $model->requiredString = 'asd';

        $model->url = $value;
        $this->expectException(ValidationException::class);
        $model->validate();
    }

    public function testInvalidInstantiate() : void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
          'Cannot initialize property '.ModelInvalidInstantiate::class.'::val with no type.'
        );
        ModelInvalidInstantiate::get(1);
    }

    public function testInvalidInstantiate2() : void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
          'Cannot initialize property '.ModelInvalidInstantiate2::class.'::val with type int.'
        );
        ModelInvalidInstantiate2::get(1);
    }
}

class ModelValidation extends Model
{

    #[Required]
    public ?string $requiredString;
    #[StringLength]
    public mixed $notTypedString;
    #[StringLength(10)]
    public string $stringLength10;
    #[StringLength(max: 10)]
    public string $stringLengthMax10;
    #[StringLength(5, 10)]
    public string $stringLengthBetween5and10;
    #[Numeric]
    public string | int | float $numeric;
    #[Email]
    public string $email;
    #[Uri]
    public string $uri;
    #[Url]
    public string $url;

}

#[PrimaryKey('model_a_id')]
class ModelA extends Model
{

    public const TABLE = 'modelsA';

    public string $name;
    public ?int $age = null;

    /**
     * @var ModelB[]
     */
    #[OneToMany(class: ModelB::class)]
    public array $children = [];

}

#[PrimaryKey('model_b_id')]
class ModelB extends Model
{

    public const TABLE = 'modelsB';

    public string $description;
    public TestEnum $modelType;

    #[ManyToOne]
    public ?ModelA $parent = null;

}

#[PrimaryKey('model_b_id')]
class ModelBLazy extends Model
{

    public const TABLE = 'modelsB';

    public string $description;
    public TestEnum $modelType;

    #[ManyToOne(loadingType: LoadingType::LAZY)]
    public ?ModelA $parent;

    public function getParent() : ?ModelA {
        $this->parent ??= ModelA::get($this->relationIds['parent'] ?? $this->row->model_a_id ?? 0);
        return $this->parent;
    }

}

#[PrimaryKey('model_c_id')]
class ModelC extends Model
{

    public const TABLE = 'modelsC';

    public string $value0;
    public SimpleData $data;

}

#[PrimaryKey('model_d_id')]
class ModelD extends Model
{

    public const TABLE = 'modelsD';

    public string $name;

    /** @var ModelE[] */
    #[ManyToMany('modelsD_modelsE', class: ModelE::class)]
    public array $models = [];

}

#[PrimaryKey('model_e_id')]
class ModelE extends Model
{

    public const TABLE = 'modelsE';

    public string $name;

    /** @var ModelD[] */
    #[ManyToMany('modelsD_modelsE', class: ModelD::class)]
    public array $models = [];

}

class ModelInvalid extends Model
{

    public const TABLE = 'invalid';

    public string $column1;
    public string $column2;

}

class ModelPk1 extends Model
{

    public const TABLE = 'invalid';

    public int $idModelPk1;

}

class ModelPk2 extends Model
{

    public const TABLE = 'invalid';

    public int $modelPk2Id;

}

final class SimpleData implements InsertExtendInterface
{

    public function __construct(
      public string $value1,
      public string $value2,
    ) {}


    public static function parseRow(Row $row) : static {
        return new SimpleData(
          $row->value1,
          $row->value2,
        );
    }

    /**
     * Add data from the object into the data array for DB INSERT/UPDATE
     *
     * @param  array<string, mixed>  $data
     */
    public function addQueryData(array &$data) : void {
        $data['value1'] = $this->value1;
        $data['value2'] = $this->value2;
    }
}

#[PrimaryKey('id_model')]
class ModelInvalidInstantiate extends Model
{

    public const TABLE = 'model_invalid_instantiate';

    #[Instantiate]
    /** @phpstan-ignore missingType.property */
    public $val;

}

#[PrimaryKey('id_model')]
class ModelInvalidInstantiate2 extends Model
{

    public const TABLE = 'model_invalid_instantiate';

    #[Instantiate]
    public int $val;

}