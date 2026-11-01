<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Vault;
use stdClass;
use Tests\Support\UnitTester;

final class VaultCest
{
    public function _before(UnitTester $I): void
    {
        Vault::getInstance()->clear();
    }

    public function testGetInstanceReturnsSingleton(UnitTester $I): void
    {
        $I->assertInstanceOf(Vault::class, Vault::getInstance());
        $I->assertSame(Vault::getInstance(), Vault::getInstance());
    }

    public function testSetAndGet(UnitTester $I): void
    {
        $vault = Vault::getInstance();
        $object = new stdClass();

        $vault->set('string', 'value');
        $vault->set('int', 42);
        $vault->set('array', ['a' => 1]);
        $vault->set('object', $object);

        $I->assertEquals('value', $vault->get('string'));
        $I->assertEquals(42, $vault->get('int'));
        $I->assertEquals(['a' => 1], $vault->get('array'));
        $I->assertSame($object, $vault->get('object'));
    }

    public function testGetMissingKeyReturnsNull(UnitTester $I): void
    {
        $I->assertNull(Vault::getInstance()->get('missing'));
    }

    public function testSetOverwritesExistingValue(UnitTester $I): void
    {
        $vault = Vault::getInstance();
        $vault->set('key', 'first');
        $vault->set('key', 'second');

        $I->assertEquals('second', $vault->get('key'));
    }

    public function testValuesSharedAcrossInstanceCalls(UnitTester $I): void
    {
        Vault::getInstance()->set('shared', 'yes');

        $I->assertEquals('yes', Vault::getInstance()->get('shared'));
    }

    public function testUnsetRemovesOnlyGivenKey(UnitTester $I): void
    {
        $vault = Vault::getInstance();
        $vault->set('keep', 1);
        $vault->set('remove', 2);

        $vault->unset('remove');

        $I->assertNull($vault->get('remove'));
        $I->assertEquals(1, $vault->get('keep'));
    }

    public function testUnsetMissingKeyIsNoop(UnitTester $I): void
    {
        $vault = Vault::getInstance();
        $vault->set('keep', 1);

        $vault->unset('missing');

        $I->assertEquals(1, $vault->get('keep'));
    }

    public function testClearRemovesAllValues(UnitTester $I): void
    {
        $vault = Vault::getInstance();
        $vault->set('a', 1);
        $vault->set('b', 2);

        $vault->clear();

        $I->assertNull($vault->get('a'));
        $I->assertNull($vault->get('b'));
    }
}
