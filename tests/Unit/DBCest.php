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

/**
 * SQLite-based tests always run. MariaDB tests need DB_HOST, DB_NAME, DB_USER and DB_PASS
 * in the environment (provided by docker-compose) and are skipped otherwise.
 */
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

    /**
     * @return array{host: string, port: string|false, db: string, user: string, pass: string}
     */
    private function mariaDbEnv(UnitTester $I): array
    {
        $host = getenv('DB_HOST');
        if (!$host) {
            $I->markTestSkipped('DB_HOST not set; MariaDB tests need the docker-compose environment');
        }

        return [
            'host' => $host,
            'port' => getenv('DB_PORT'),
            'db' => (string)getenv('DB_NAME'),
            'user' => (string)getenv('DB_USER'),
            'pass' => (string)getenv('DB_PASS'),
        ];
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

    public function testScalarDbConfigThrowsMissingKeys(UnitTester $I): void
    {
        $this->setConfig(['db' => 'mysql://localhost/app']);

        $I->expectThrowable(new \Exception("DB config missing required keys: 'dsn' or ('host' and 'db')"), function () {
            DB::getFromConfig();
        });
    }

    public function testMariaDbConnectionFromParts(UnitTester $I): void
    {
        $env = $this->mariaDbEnv($I);
        $this->setConfig(['db' => $env]);

        $pdo = DB::getFromConfig();

        $I->assertEquals('mysql', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        $I->assertEquals($env['db'], $pdo->query('SELECT DATABASE()')->fetchColumn());
        $I->assertEquals('utf8mb4', $pdo->query('SELECT @@character_set_client')->fetchColumn());
        $I->assertStringContainsStringIgnoringCase('mariadb', $pdo->query('SELECT VERSION()')->fetchColumn());
    }

    public function testMariaDbConnectionFromPartsObjectWithoutPort(UnitTester $I): void
    {
        $env = $this->mariaDbEnv($I);
        $db = new stdClass();
        $db->host = $env['host'];
        $db->db = $env['db'];
        $db->user = $env['user'];
        $db->pass = $env['pass'];
        $config = new stdClass();
        $config->db = $db;
        $this->setConfig($config);

        $pdo = DB::getFromConfig();

        $I->assertEquals($env['db'], $pdo->query('SELECT DATABASE()')->fetchColumn());
    }

    public function testMariaDbPortIsUsed(UnitTester $I): void
    {
        $env = $this->mariaDbEnv($I);
        $this->setConfig(['db' => ['port' => 1] + $env]);

        $I->expectThrowable(PDOException::class, function () {
            DB::getFromConfig();
        });
    }

    public function testMariaDbCustomCharset(UnitTester $I): void
    {
        $env = $this->mariaDbEnv($I);
        $this->setConfig(['db' => $env + ['charset' => 'latin1']]);

        $pdo = DB::getFromConfig();

        $I->assertEquals('latin1', $pdo->query('SELECT @@character_set_client')->fetchColumn());
    }

    public function testMariaDbConnectionFromDsn(UnitTester $I): void
    {
        $env = $this->mariaDbEnv($I);
        $this->setConfig(['db' => [
            'dsn' => "mysql:host={$env['host']};dbname={$env['db']}",
            'user' => $env['user'],
            'pass' => $env['pass'],
        ]]);

        $pdo = DB::getFromConfig();

        $I->assertEquals($env['db'], $pdo->query('SELECT DATABASE()')->fetchColumn());
    }

    public function testMariaDbWrongPasswordThrows(UnitTester $I): void
    {
        $env = $this->mariaDbEnv($I);
        $this->setConfig(['db' => ['pass' => 'wrong-password'] + $env]);

        $I->expectThrowable(PDOException::class, function () {
            DB::getFromConfig();
        });
    }

    public function testMariaDbRoundTrip(UnitTester $I): void
    {
        $env = $this->mariaDbEnv($I);
        $this->setConfig(['db' => $env]);

        $pdo = DB::get();
        $pdo->exec('CREATE TEMPORARY TABLE db_cest (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))');
        $pdo->prepare('INSERT INTO db_cest (name) VALUES (?)')->execute(['Æblegrød']);

        $row = DB::get()->query('SELECT id, name FROM db_cest')->fetch(PDO::FETCH_ASSOC);

        $I->assertEquals(1, $row['id']);
        $I->assertEquals('Æblegrød', $row['name']);
    }
}
