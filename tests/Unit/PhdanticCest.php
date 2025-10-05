<?php

declare(strict_types=1);


namespace Tests\Unit;

use Phdantic\Phdantic;
use stdClass;
use Tests\Support\UnitTester;

final class PhdanticCest
{
    public function validateInt(UnitTester $I): void
    {
        $I->assertTrue(Phdantic::validateInt(1));
        $I->assertTrue(Phdantic::validateInt(0));

        $I->assertFalse(Phdantic::validateInt(null));
        $I->assertFalse(Phdantic::validateInt(false));
        $I->assertFalse(Phdantic::validateBool([]));
    }

    public function validateString(UnitTester $I): void
    {
        $I->assertTrue(Phdantic::validateString(''));
        $I->assertTrue(Phdantic::validateString('FooBar'));
        $I->assertTrue(Phdantic::validateString('true'));

        $I->assertFalse(Phdantic::validateString(0));
        $I->assertFalse(Phdantic::validateString(true));
        $I->assertFalse(Phdantic::validateBool([]));
    }

    public function validateBool(UnitTester $I): void
    {
        $I->assertTrue(Phdantic::validateBool(true));
        $I->assertTrue(Phdantic::validateBool(false));
        $I->assertTrue(Phdantic::validateBool(0));
        $I->assertTrue(Phdantic::validateBool(1));
        $I->assertTrue(Phdantic::validateBool('1'));
        $I->assertTrue(Phdantic::validateBool('0'));
        $I->assertTrue(Phdantic::validateBool('true'));
        $I->assertTrue(Phdantic::validateBool('false'));

        $I->assertFalse(Phdantic::validateBool(''));
        $I->assertFalse(Phdantic::validateBool(3));
        $I->assertFalse(Phdantic::validateBool(null));
        $I->assertFalse(Phdantic::validateBool([]));
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

        $object = new stdClass();
        $object->name = 1;
        $I->assertFalse(Phdantic::validateObject($object, $rules));
        $object = new stdClass();
        $object->name = true;
        $I->assertFalse(Phdantic::validateObject($object, $rules));
        $object = new stdClass();
        $object->name = [];
        $I->assertFalse(Phdantic::validateObject($object, $rules));

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
