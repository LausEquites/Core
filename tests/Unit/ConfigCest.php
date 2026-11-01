<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Config;
use ReflectionProperty;
use stdClass;
use Tests\Support\UnitTester;

final class ConfigCest
{
    private const DATA_ROOT = __DIR__ . '/../Support/Data';

    public function _before(UnitTester $I): void
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', self::DATA_ROOT);
        }

        $this->setConfig([]);
    }

    public function _after(UnitTester $I): void
    {
        $this->setConfig([]);
    }

    private function setConfig(mixed $config): void
    {
        (new ReflectionProperty(Config::class, 'config'))->setValue(null, $config);
    }

    private function skipIfForeignAppRoot(UnitTester $I): void
    {
        if (realpath(APP_ROOT) !== realpath(self::DATA_ROOT)) {
            $I->markTestSkipped('APP_ROOT is defined elsewhere; config fixture cannot be loaded');
        }
    }

    public function testGetAllLoadsConfigFile(UnitTester $I): void
    {
        $this->skipIfForeignAppRoot($I);

        $config = Config::getAll();

        $I->assertIsArray($config);
        $I->assertEquals('Core Test', $config['app_name']);
        $I->assertEquals(['host' => 'localhost', 'port' => 3306], $config['db']);
    }

    public function testGetKeyLazyLoadsConfigFile(UnitTester $I): void
    {
        $this->skipIfForeignAppRoot($I);

        $I->assertEquals('Core Test', Config::getKey('app_name'));
        $I->assertTrue(Config::getKey('debug'));
    }

    public function testGetAllCachesLoadedConfig(UnitTester $I): void
    {
        $this->setConfig(['cached' => 'value']);

        $I->assertEquals(['cached' => 'value'], Config::getAll());
    }

    public function testGetKeyFromArrayConfig(UnitTester $I): void
    {
        $this->setConfig(['name' => 'array', 'nested' => ['a' => 1]]);

        $I->assertEquals('array', Config::getKey('name'));
        $I->assertEquals(['a' => 1], Config::getKey('nested'));
    }

    public function testGetKeyReturnsNullValueForExistingArrayKey(UnitTester $I): void
    {
        $this->setConfig(['nullable' => null]);

        $I->assertNull(Config::getKey('nullable'));
    }

    public function testGetKeyMissingArrayKeyThrows(UnitTester $I): void
    {
        $this->setConfig(['name' => 'array']);

        $I->expectThrowable(new \Exception("No config for 'missing'"), function () {
            Config::getKey('missing');
        });
    }

    public function testGetKeyFromObjectConfig(UnitTester $I): void
    {
        $config = new stdClass();
        $config->name = 'object';
        $config->nullable = null;
        $this->setConfig($config);

        $I->assertSame($config, Config::getAll());
        $I->assertEquals('object', Config::getKey('name'));
        $I->assertNull(Config::getKey('nullable'));
    }

    public function testGetKeyMissingObjectPropertyThrows(UnitTester $I): void
    {
        $config = new stdClass();
        $config->name = 'object';
        $this->setConfig($config);

        $I->expectThrowable(new \Exception("No config for 'missing'"), function () {
            Config::getKey('missing');
        });
    }

    public function testGetKeyWithInvalidConfigFormatThrows(UnitTester $I): void
    {
        $this->setConfig('not a config');

        $I->expectThrowable(new \Exception('Invalid config format: expected array or object'), function () {
            Config::getKey('any');
        });
    }
}
