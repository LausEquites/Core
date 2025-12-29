<?php

declare(strict_types=1);


namespace Tests\Unit;

use Phdantic\Phdantic;
use Phdantic\Tester;
use stdClass;
use Tests\Support\UnitTester;

final class PhdanticCest
{
    public function validateInt(UnitTester $I): void
    {
        $I->assertTrue(Tester::isInt(1));
        $I->assertTrue(Tester::isInt(0));

        $I->assertFalse(Tester::isInt(0.00));
        $I->assertFalse(Tester::isInt(null));
        $I->assertFalse(Tester::isInt(false));
        $I->assertFalse(Tester::isBool([]));
    }

    public function validateFloat(UnitTester $I): void
    {
        $I->assertTrue(Tester::isFloat(0.00));
        $I->assertTrue(Tester::isFloat(1));
        $I->assertTrue(Tester::isFloat(0));

        $I->assertFalse(Tester::isFloat(null));
        $I->assertFalse(Tester::isFloat(false));
        $I->assertFalse(Tester::isFloat([]));
    }

    public function validateFloatStrict(UnitTester $I): void
    {
        $I->assertTrue(Tester::isFloatStrict(0.00));

        $I->assertFalse(Tester::isFloatStrict(1));
        $I->assertFalse(Tester::isFloatStrict(0));
        $I->assertFalse(Tester::isFloatStrict(null));
        $I->assertFalse(Tester::isFloatStrict(false));
        $I->assertFalse(Tester::isFloatStrict([]));
    }

    public function validateString(UnitTester $I): void
    {
        $I->assertTrue(Tester::isString(''));
        $I->assertTrue(Tester::isString('FooBar'));
        $I->assertTrue(Tester::isString('true'));

        $I->assertFalse(Tester::isString(0));
        $I->assertFalse(Tester::isString(true));
        $I->assertFalse(Tester::isBool([]));
    }

    public function validateBool(UnitTester $I): void
    {
        $I->assertTrue(Tester::isBool(true));
        $I->assertTrue(Tester::isBool(false));

        $I->assertFalse(Tester::isBool(0));
        $I->assertFalse(Tester::isBool(1));
        $I->assertFalse(Tester::isBool('1'));
        $I->assertFalse(Tester::isBool('0'));
        $I->assertFalse(Tester::isBool('true'));
        $I->assertFalse(Tester::isBool('false'));
        $I->assertFalse(Tester::isBool(''));
        $I->assertFalse(Tester::isBool(3));
        $I->assertFalse(Tester::isBool(null));
        $I->assertFalse(Tester::isBool([]));
    }

    public function validateBoolString(UnitTester $I): void
    {
        $I->assertTrue(Tester::isBoolLoose(true));
        $I->assertTrue(Tester::isBoolLoose(false));
        $I->assertTrue(Tester::isBoolLoose(0));
        $I->assertTrue(Tester::isBoolLoose(1));
        $I->assertTrue(Tester::isBoolLoose('1'));
        $I->assertTrue(Tester::isBoolLoose('0'));
        $I->assertTrue(Tester::isBoolLoose('true'));
        $I->assertTrue(Tester::isBoolLoose('false'));

        $I->assertFalse(Tester::isBoolLoose(''));
        $I->assertFalse(Tester::isBoolLoose(3));
        $I->assertFalse(Tester::isBoolLoose(null));
        $I->assertFalse(Tester::isBoolLoose([]));
    }

    public function validateArray(UnitTester $I): void
    {
        $I->assertTrue(Tester::isArray([]));

        $I->assertFalse(Tester::isArray(''));
        $I->assertFalse(Tester::isArray('FooBar'));
        $I->assertFalse(Tester::isArray('true'));
        $I->assertFalse(Tester::isArray(0));
        $I->assertFalse(Tester::isArray(true));
    }

    public function validateObject(UnitTester $I): void
    {
        $I->assertTrue(Tester::isObject(new stdClass()));

        $I->assertFalse(Tester::isObject(''));
        $I->assertFalse(Tester::isObject('FooBar'));
        $I->assertFalse(Tester::isObject('true'));
        $I->assertFalse(Tester::isObject(0));
        $I->assertFalse(Tester::isObject(true));
        $I->assertFalse(Tester::isObject([]));
    }

    public function testFilter(UnitTester $I): void
    {
        $rules = [
            'required' => [
                'name' => 'string',
            ],
            'optional' => [
                'age' => 'int',
            ],
        ];

        $result = new stdClass();
        $result->name = 'Foo';
        $result->age = 10;

        $object = clone $result;
        $filtered = Phdantic::filterObject($object, $rules);
        $I->assertEquals($result, $filtered);
        $I->assertEquals($object, $filtered);

        $object->height = 1.85;
        $filtered = Phdantic::filterObject($object, $rules);
        $I->assertEquals($result, $filtered);
        $I->assertNotEquals($object, $filtered);
    }

    public function testValidateObject(UnitTester $I): void
    {
        $rules = [
            'required' => [
                'name' => 'string',
            ],
            'optional' => [
                'age' => 'int',
                'isHuman' => 'bool',
                'na' => 'string',
            ],
        ];

        $object = new stdClass();
        $object->name = 'Foo';
        $object->age = 10;
        $object->isHuman = true;
        $I->assertTrue(Phdantic::validateObject($object, $rules));

        $object = new stdClass();
        $object->name = 'Foo';
        $I->assertTrue(Phdantic::validateObject($object, $rules));

        $object = new stdClass();
        $object->age = 10;
        $I->assertFalse(Phdantic::validateObject($object, $rules));
        $lastErrors = Phdantic::getLastErrors();
        $I->assertEquals(['name'], $lastErrors['missing']);

        $object = new stdClass();
        $object->name = 1;
        $I->assertFalse(Phdantic::validateObject($object, $rules));
        $I->assertEquals(['name'], Phdantic::getLastErrors()['invalid']);
        $object = new stdClass();
        $object->name = true;
        $I->assertFalse(Phdantic::validateObject($object, $rules));
        $I->assertEquals(['name'], Phdantic::getLastErrors()['invalid']);
        $object = new stdClass();
        $object->name = [];
        $I->assertFalse(Phdantic::validateObject($object, $rules));
        $I->assertEquals(['name'], Phdantic::getLastErrors()['invalid']);

        $object = new stdClass();
        $object->name = 'Foo';
        $object->age = 'Bar';
        $I->assertFalse(Phdantic::validateObject($object, $rules));

        $rules = [
            'required' => [
                'name' => 'na',
            ],
        ];
        $object = new stdClass();
        $object->name = 'Foo';
        $I->expectThrowable(\Exception::class, function () use ($object, $rules) {
            Phdantic::validateObject($object, $rules);
        });

    }
}
