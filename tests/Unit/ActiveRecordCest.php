<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Core\ActiveRecord;
use Core\Config;
use Core\DB;
use Core\Exceptions\External;
use PDO;
use ReflectionProperty;
use stdClass;
use Tests\Support\UnitTester;

/**
 * Concrete test model extending ActiveRecord for unit testing.
 */
class TestRecord extends ActiveRecord
{
    public static ?PDO $mockPdo = null;

    protected static $_table = 'test_records';
    protected static $_typeMap = [
        'created_at' => 'datetime',
        'updated_at' => 'dt',
        'is_active' => 'bit',
        'meta' => 'json',
        'tags' => 'json-assoc',
    ];

    public mixed $name = null;
    public mixed $created_at = null;
    public mixed $updated_at = null;
    public mixed $is_active = null;
    public mixed $meta = null;
    public mixed $tags = null;

    protected static function getPdo(): PDO
    {
        return self::$mockPdo ?? parent::getPdo();
    }

    public function getId(): mixed
    {
        return $this->id;
    }

    public function setId(mixed $id): void
    {
        $this->id = $id;
    }

    public function testSet(string $field, mixed $value): void
    {
        $this->set($field, $value);
    }

    public function testSave(): bool
    {
        return $this->save();
    }

    public function getModified(): array
    {
        return $this->_modified;
    }

    public static function findById(mixed $id): static|false
    {
        return static::getById($id);
    }

    public static function findByIdMulti(array $ids): array
    {
        return static::getByIdMulti($ids);
    }

    public static function findBySql(string $sql, array $params = []): array
    {
        return static::getBySql($sql, $params);
    }

    public static function findBySqlSingle(string $sql, array $params = []): static|false
    {
        return static::getBySqlSingle($sql, $params);
    }
}

/**
 * Same table as TestRecord but stores meta as 'json-object'.
 */
class JsonObjectTestRecord extends TestRecord
{
    protected static $_typeMap = ['meta' => 'json-object'];
}

/**
 * Hydrates datetime fields as plain DateTime even though Carbon is installed.
 */
class NoCarbonTestRecord extends TestRecord
{
    protected static function useCarbon()
    {
        return false;
    }
}

/**
 * Same table as TestRecord but with a type mapping ActiveRecord does not know.
 */
class UnknownTypeTestRecord extends TestRecord
{
    protected static $_typeMap = ['name' => 'unknown'];
}

/**
 * Uses the default ActiveRecord::getPdo(), i.e. Core\DB::get().
 */
class DefaultPdoTestRecord extends ActiveRecord
{
    protected static $_table = 'test_records';

    public mixed $name = null;

    public function getId(): mixed
    {
        return $this->id;
    }

    public function testSet(string $field, mixed $value): void
    {
        $this->set($field, $value);
    }

    public function testSave(): bool
    {
        return $this->save();
    }

    public static function findById(mixed $id): static|false
    {
        return static::getById($id);
    }
}

final class ActiveRecordCest
{
    private function createSqlitePdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec("
            CREATE TABLE test_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                created_at TEXT,
                updated_at TEXT,
                is_active INTEGER,
                meta TEXT,
                tags TEXT
            )
        ");

        return $pdo;
    }

    public function testHydration(UnitTester $I): void
    {
        $data = [
            'id' => 1,
            'name' => 'Test Item',
            'created_at' => '2026-01-01 12:00:00',
            'updated_at' => '2026-01-02 15:30:00',
            'is_active' => 1,
            'meta' => json_encode(['role' => 'admin']),
            'tags' => json_encode(['php', 'core']),
            'ignored_field' => 'should not exist',
        ];

        $record = TestRecord::createFromArray($data);

        $I->assertEquals(1, $record->getId());
        $I->assertEquals('Test Item', $record->name);
        $I->assertInstanceOf(\DateTimeInterface::class, $record->created_at);
        $I->assertEquals('2026-01-01 12:00:00', $record->created_at->format('Y-m-d H:i:s'));
        $I->assertInstanceOf(\DateTimeInterface::class, $record->updated_at);
        $I->assertEquals('2026-01-02 15:30:00', $record->updated_at->format('Y-m-d H:i:s'));
        $I->assertInstanceOf(stdClass::class, $record->meta);
        $I->assertEquals('admin', $record->meta->role);
        $I->assertIsArray($record->tags);
        $I->assertEquals(['php', 'core'], $record->tags);
    }

    public function testSetAndDirtyTracking(UnitTester $I): void
    {
        $record = new TestRecord();
        $I->assertEmpty($record->getModified());

        $record->testSet('name', 'New Name');
        $record->testSet('is_active', 1);

        $I->assertEquals(['name' => true, 'is_active' => true], $record->getModified());
        $I->assertEquals('New Name', $record->name);
        $I->assertEquals(1, $record->is_active);
    }

    public function testInsertAndResetDirtyState(UnitTester $I): void
    {
        $pdo = $this->createSqlitePdo();
        TestRecord::$mockPdo = $pdo;

        $record = new TestRecord();
        $record->testSet('name', 'Inserted Record');
        $record->testSet('is_active', true);
        $record->testSet('created_at', new \DateTime('2026-03-01 10:00:00', new \DateTimeZone('UTC')));
        $record->testSet('meta', ['key' => 'val']);

        $saved = $record->testSave();
        $I->assertTrue($saved);
        $I->assertNotEmpty($record->getId());
        $I->assertEmpty($record->getModified(), 'Modified array should be cleared after save');

        // Fetch back and assert
        $fetched = TestRecord::findById($record->getId());
        $I->assertNotFalse($fetched);
        $I->assertEquals('Inserted Record', $fetched->name);
        $I->assertEquals(1, $fetched->is_active);
        $I->assertEquals('val', $fetched->meta->key);
    }

    public function testUpdateExcludingId(UnitTester $I): void
    {
        $pdo = $this->createSqlitePdo();
        TestRecord::$mockPdo = $pdo;

        $record = new TestRecord();
        $record->testSet('name', 'Original');
        $record->testSave();
        $id = $record->getId();

        // Now modify with id marked modified
        $record->testSet('name', 'Updated Name');
        $record->testSet('id', $id);

        $saved = $record->testSave();
        $I->assertTrue($saved);
        $I->assertEmpty($record->getModified());

        $fetched = TestRecord::findById($id);
        $I->assertEquals('Updated Name', $fetched->name);
    }

    public function testGetByIdMultiWithAssociativeKeys(UnitTester $I): void
    {
        $pdo = $this->createSqlitePdo();
        TestRecord::$mockPdo = $pdo;

        $r1 = new TestRecord();
        $r1->testSet('name', 'First');
        $r1->testSave();

        $r2 = new TestRecord();
        $r2->testSet('name', 'Second');
        $r2->testSave();

        // Associative keys array
        $assocIds = ['first_key' => $r1->getId(), 'second_key' => $r2->getId()];
        $results = TestRecord::findByIdMulti($assocIds);

        $I->assertCount(2, $results);
        $I->assertEquals('First', $results[0]->name);
        $I->assertEquals('Second', $results[1]->name);

        $emptyResults = TestRecord::findByIdMulti([]);
        $I->assertEmpty($emptyResults);
    }

    public function testGetBySqlAndSingle(UnitTester $I): void
    {
        $pdo = $this->createSqlitePdo();
        TestRecord::$mockPdo = $pdo;

        $r1 = new TestRecord();
        $r1->testSet('name', 'Alpha');
        $r1->testSave();

        $r2 = new TestRecord();
        $r2->testSet('name', 'Beta');
        $r2->testSave();

        $all = TestRecord::findBySql('SELECT * FROM test_records ORDER BY id ASC');
        $I->assertCount(2, $all);

        $single = TestRecord::findBySqlSingle('SELECT * FROM test_records WHERE name = :name', ['name' => 'Alpha']);
        $I->assertNotFalse($single);
        $I->assertEquals('Alpha', $single->name);

        $notFound = TestRecord::findBySqlSingle('SELECT * FROM test_records WHERE name = :name', ['name' => 'NonExistent']);
        $I->assertFalse($notFound);
    }

    public function testNullJsonHandling(UnitTester $I): void
    {
        $pdo = $this->createSqlitePdo();
        TestRecord::$mockPdo = $pdo;

        $record = new TestRecord();
        $record->testSet('name', 'Null JSON Test');
        $record->testSet('meta', null);
        $record->testSave();

        $stmt = $pdo->prepare('SELECT meta FROM test_records WHERE id = :id');
        $stmt->execute(['id' => $record->getId()]);
        $raw = $stmt->fetchColumn();

        $I->assertNull($raw, 'Null JSON value should be stored as SQL NULL, not "null" string');
    }

    private function fetchRaw(PDO $pdo, string $column, mixed $id): mixed
    {
        $stmt = $pdo->prepare("SELECT $column FROM test_records WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->fetchColumn();
    }

    public function testGetByIdWithEmptyIdReturnsFalse(UnitTester $I): void
    {
        TestRecord::$mockPdo = $this->createSqlitePdo();

        $I->assertFalse(TestRecord::findById(null));
        $I->assertFalse(TestRecord::findById(''));
    }

    public function testGetByIdNotFoundReturnsFalse(UnitTester $I): void
    {
        TestRecord::$mockPdo = $this->createSqlitePdo();

        $I->assertFalse(TestRecord::findById(999));
    }

    public function testHydrationKeepsDateTimeInstances(UnitTester $I): void
    {
        $date = new \DateTimeImmutable('2026-01-01 12:00:00');

        $record = TestRecord::createFromArray(['created_at' => $date]);

        $I->assertSame($date, $record->created_at);
    }

    public function testHydrationUsesCarbonInUtc(UnitTester $I): void
    {
        $record = TestRecord::createFromArray(['created_at' => '2026-01-01 12:00:00']);

        $I->assertInstanceOf(Carbon::class, $record->created_at);
        $I->assertEquals('UTC', $record->created_at->getTimezone()->getName());
        $I->assertEquals('2026-01-01 12:00:00', $record->created_at->format('Y-m-d H:i:s'));
    }

    public function testHydrationFallsBackToDateTimeWithoutCarbon(UnitTester $I): void
    {
        $record = NoCarbonTestRecord::createFromArray(['created_at' => '2026-01-01 12:00:00']);

        $I->assertInstanceOf(\DateTime::class, $record->created_at);
        $I->assertNotInstanceOf(Carbon::class, $record->created_at);
        $I->assertEquals('UTC', $record->created_at->getTimezone()->getName());
        $I->assertEquals('2026-01-01 12:00:00', $record->created_at->format('Y-m-d H:i:s'));
    }

    public function testSaveWithoutModificationsDoesNothing(UnitTester $I): void
    {
        $pdo = $this->createSqlitePdo();
        TestRecord::$mockPdo = $pdo;

        $record = new TestRecord();

        $I->assertTrue($record->testSave());
        $I->assertNull($record->getId());
        $I->assertEquals(0, $pdo->query('SELECT COUNT(*) FROM test_records')->fetchColumn());
    }

    public function testUpdateWithOnlyIdModifiedSkipsQuery(UnitTester $I): void
    {
        $pdo = $this->createSqlitePdo();
        TestRecord::$mockPdo = $pdo;

        $record = new TestRecord();
        $record->testSet('name', 'Original');
        $record->testSave();
        $id = $record->getId();

        $record->name = 'Changed without set()';
        $record->testSet('id', $id);

        $I->assertTrue($record->testSave());
        $I->assertEmpty($record->getModified());
        $I->assertEquals('Original', TestRecord::findById($id)->name);
    }

    public function testSaveDateTimeConvertsToUtc(UnitTester $I): void
    {
        $pdo = $this->createSqlitePdo();
        TestRecord::$mockPdo = $pdo;

        $record = new TestRecord();
        $record->testSet('created_at', new \DateTimeImmutable('2026-03-01 12:00:00', new \DateTimeZone('Europe/Copenhagen')));
        $record->testSave();

        $I->assertEquals('2026-03-01 11:00:00', $this->fetchRaw($pdo, 'created_at', $record->getId()));
    }

    public function testSaveDateTimeStringAndNull(UnitTester $I): void
    {
        $pdo = $this->createSqlitePdo();
        TestRecord::$mockPdo = $pdo;

        $record = new TestRecord();
        $record->testSet('created_at', '2026-05-05 05:05:05');
        $record->testSet('updated_at', null);
        $record->testSave();

        $I->assertEquals('2026-05-05 05:05:05', $this->fetchRaw($pdo, 'created_at', $record->getId()));
        $I->assertNull($this->fetchRaw($pdo, 'updated_at', $record->getId()));
    }

    public function testSaveBitValues(UnitTester $I): void
    {
        $pdo = $this->createSqlitePdo();
        TestRecord::$mockPdo = $pdo;

        $active = new TestRecord();
        $active->testSet('is_active', '1');
        $active->testSave();

        $inactive = new TestRecord();
        $inactive->testSet('is_active', false);
        $inactive->testSave();

        $unknown = new TestRecord();
        $unknown->testSet('is_active', null);
        $unknown->testSave();

        $I->assertSame(1, $this->fetchRaw($pdo, 'is_active', $active->getId()));
        $I->assertSame(0, $this->fetchRaw($pdo, 'is_active', $inactive->getId()));
        $I->assertNull($this->fetchRaw($pdo, 'is_active', $unknown->getId()));
    }

    public function testSaveJsonAssocRoundTrip(UnitTester $I): void
    {
        $pdo = $this->createSqlitePdo();
        TestRecord::$mockPdo = $pdo;

        $record = new TestRecord();
        $record->testSet('tags', ['lang' => 'php', 'list' => [1, 2]]);
        $record->testSave();

        $I->assertEquals('{"lang":"php","list":[1,2]}', $this->fetchRaw($pdo, 'tags', $record->getId()));
        $I->assertEquals(['lang' => 'php', 'list' => [1, 2]], TestRecord::findById($record->getId())->tags);
    }

    public function testSaveJsonObjectRoundTrip(UnitTester $I): void
    {
        $pdo = $this->createSqlitePdo();
        TestRecord::$mockPdo = $pdo;

        $meta = new stdClass();
        $meta->role = 'editor';

        $record = new JsonObjectTestRecord();
        $record->testSet('meta', $meta);
        $record->testSave();

        $fetched = JsonObjectTestRecord::findById($record->getId());
        $I->assertInstanceOf(stdClass::class, $fetched->meta);
        $I->assertEquals('editor', $fetched->meta->role);
    }

    public function testSaveUnencodableJsonThrowsExternal(UnitTester $I): void
    {
        TestRecord::$mockPdo = $this->createSqlitePdo();

        $record = new TestRecord();
        $record->testSet('meta', ['value' => INF]);

        $I->expectThrowable(new External('Error converting meta to json', 500), function () use ($record) {
            $record->testSave();
        });
    }

    public function testSaveUnknownTypeThrows(UnitTester $I): void
    {
        TestRecord::$mockPdo = $this->createSqlitePdo();

        $record = new UnknownTypeTestRecord();
        $record->testSet('name', 'Anything');

        $I->expectThrowable(new \Exception('Unknown field type unknown for name'), function () use ($record) {
            $record->testSave();
        });
    }

    public function testDefaultPdoComesFromDb(UnitTester $I): void
    {
        $dbProperty = new ReflectionProperty(DB::class, 'db');
        $configProperty = new ReflectionProperty(Config::class, 'config');
        $configProperty->setValue(null, ['db' => ['dsn' => 'sqlite::memory:']]);
        $dbProperty->setValue(null, null);

        try {
            DB::get()->exec('CREATE TABLE test_records (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

            $record = new DefaultPdoTestRecord();
            $record->testSet('name', 'Via DB');
            $record->testSave();

            $I->assertEquals('Via DB', DefaultPdoTestRecord::findById($record->getId())->name);
        } finally {
            $dbProperty->setValue(null, null);
            $configProperty->setValue(null, []);
        }
    }
}
