<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Config;
use Core\DB;
use PDO;
use PDOException;
use ReflectionProperty;
use stdClass;
use Tests\Support\UnitTester;

final class DBCest
{
    public function _before(UnitTester $I): void
    {
        $this->reset();
    }

    public function _after(UnitTester $I): void
    {
        $this->reset();
    }

    private function reset(): void
    {
        (new ReflectionProperty(DB::class, 'db'))->setValue(null, null);
        $this->setConfig([]);
    }

    private function setConfig(mixed $config): void
    {
        (new ReflectionProperty(Config::class, 'config'))->setValue(null, $config);
    }

    public function testGetFromConfigWithDsnArray(UnitTester $I): void
    {
        $this->setConfig(['db' => ['dsn' => 'sqlite::memory:']]);

        $pdo = DB::getFromConfig();

        $I->assertInstanceOf(PDO::class, $pdo);
        $I->assertEquals('sqlite', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        $I->assertEquals(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function testGetFromConfigWithDsnObject(UnitTester $I): void
    {
        $db = new stdClass();
        $db->dsn = 'sqlite::memory:';
        $config = new stdClass();
        $config->db = $db;
        $this->setConfig($config);

        $pdo = DB::getFromConfig();

        $I->assertEquals('sqlite', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function testGetFromConfigReturnsNewConnectionEachCall(UnitTester $I): void
    {
        $this->setConfig(['db' => ['dsn' => 'sqlite::memory:']]);

        $I->assertNotSame(DB::getFromConfig(), DB::getFromConfig());
    }

    public function testGetReturnsSingleton(UnitTester $I): void
    {
        $this->setConfig(['db' => ['dsn' => 'sqlite::memory:']]);

        $I->assertSame(DB::get(), DB::get());
    }

    public function testExtraOptionsAreMergedWithDefaults(UnitTester $I): void
    {
        $this->setConfig(['db' => [
            'dsn' => 'sqlite::memory:',
            'options' => [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ],
        ]]);

        $pdo = DB::getFromConfig();

        $I->assertEquals(PDO::FETCH_OBJ, $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
        $I->assertEquals(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function testExtraOptionsOverrideDefaults(UnitTester $I): void
    {
        $this->setConfig(['db' => [
            'dsn' => 'sqlite::memory:',
            'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT],
        ]]);

        $pdo = DB::getFromConfig();

        $I->assertEquals(PDO::ERRMODE_SILENT, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function testMissingDbConfigThrows(UnitTester $I): void
    {
        $this->setConfig(['other' => 'value']);

        $I->expectThrowable(new \Exception('No DB in config'), function () {
            DB::getFromConfig();
        });
    }

    public function testMissingDbConfigInObjectThrows(UnitTester $I): void
    {
        $config = new stdClass();
        $config->other = 'value';
        $this->setConfig($config);

        $I->expectThrowable(new \Exception('No DB in config'), function () {
            DB::getFromConfig();
        });
    }

    public function testInvalidConfigFormatThrows(UnitTester $I): void
    {
        $this->setConfig('not a config');

        $I->expectThrowable(new \Exception('Invalid config format: expected array or object'), function () {
            DB::getFromConfig();
        });
    }

    public function testMissingHostOrDbNameThrows(UnitTester $I): void
    {
        $expected = new \Exception("DB config missing required keys: 'dsn' or ('host' and 'db')");

        $this->setConfig(['db' => ['host' => 'localhost']]);
        $I->expectThrowable($expected, function () {
            DB::getFromConfig();
        });

        $this->setConfig(['db' => ['db' => 'app']]);
        $I->expectThrowable($expected, function () {
            DB::getFromConfig();
        });
    }

    public function testInvalidDsnThrowsPdoException(UnitTester $I): void
    {
        $this->setConfig(['db' => ['dsn' => 'nosuchdriver:foo']]);

        $I->expectThrowable(PDOException::class, function () {
            DB::getFromConfig();
        });
    }
}
