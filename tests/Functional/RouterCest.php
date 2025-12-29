<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTester;

final class RouterCest
{
    // All `public` methods will be executed as tests.
    public function trySimpleRoute(FunctionalTester $I): void
    {
        $I->sendGet('/api/houses');
        $I->seeResponseCodeIs(200);
    }

    public function tryNotFoundRoute(FunctionalTester $I): void
    {
        $I->sendGet('/api/404');
        $I->seeResponseCodeIs(404);
    }

    public function tryNotImplementedRoute(FunctionalTester $I): void
    {
        $I->sendPatch('/api/houses');
        $I->seeResponseCodeIs(501);
    }

    public function tryRouteWithParams(FunctionalTester $I): void
    {
        $I->sendGet('/api/houses/1');
        $I->seeResponseCodeIs(200);
        $response = $I->grabJsonResponse();
        $I->assertEquals(1, $response->id);
        $I->assertEquals(null, $response->city);

        $I->sendGet('/api/houses/1/floors');
        $I->seeResponseCodeIs(200);
        $response = $I->grabJsonResponse();
        $I->assertEquals(1, $response->houseId);
    }

    public function tryRouteWithJson(FunctionalTester $I): void
    {
        $data = [
            'name' => 'FooBar',
            'street' => '123 Main St.',
            'streetNumber' => 10,
            'builtYear' => 1983,
        ];
        $I->sendPost('/api/houses', $data);
        $I->seeResponseCodeIs(200);

        $response = $I->grabJsonResponse();
        $I->assertEquals($data['name'], $response->name);
        $I->assertEquals($data['street'], $response->street);
        $I->assertEquals($data['streetNumber'], $response->streetNumber);
        $I->assertEquals($data['builtYear'], $response->builtYear);
    }

    public function tryRouteWithMissingJsonParams(FunctionalTester $I): void
    {
        $tests = [
            [
                'name' => 'Success',
                'params' => [
                    'name' => 'FooBar',
                    'street' => '123 Main St.',
                    'streetNumber' => 10,
                    'builtYear' => 1983,
                ],
                'httpStatus' => 200,
            ],
            [
                'name' => 'Missing all',
                'params' => [],
                'missing' => ['name', 'street', 'streetNumber'],
                'httpStatus' => 400,
            ],
            [
                'name' => 'Missing street and streetNumber',
                'params' => [
                    'name' => 'FooBar',
                ],
                'missing' => ['street','streetNumber'],
                'httpStatus' => 400,
            ],
            [
                'name' => 'Missing optional builtYear',
                'params' => [
                    'name' => 'FooBar',
                    'street' => '123 Main St.',
                    'streetNumber' => 10,
                ],
                'httpStatus' => 200,
            ],
        ];

        foreach ($tests as $test) {
            $I->amGoingTo("Testing {$test['name']}");
            $I->sendPost('/api/houses', $test['params']);
            $I->seeResponseCodeIs($test['httpStatus']);
            if (isset($test['missing'])) {
                $response = $I->grabJsonResponse();
                $I->assertEquals($test['missing'], $response->errors->missing);
            }
        }
    }

    public function tryRouteWithInvalidJsonParams(FunctionalTester $I): void
    {
        $tests = [
            [
                'name' => 'Success',
                'params' => [
                    'name' => 'FooBar',
                    'street' => '123 Main St.',
                    'streetNumber' => 10,
                    'builtYear' => 1983,
                    'locked' => false,
                ],
                'httpStatus' => 200,
            ],
            [
                'name' => 'Invalid string',
                'params' => [
                    'name' => null,
                    'street' => '123 Main St.',
                    'streetNumber' => 10,
                ],
                'invalid' => ['name'],
                'httpStatus' => 400,
            ],
            [
                'name' => 'Invalid int',
                'params' => [
                    'name' => 'FooBar',
                    'street' => '123 Main St.',
                    'streetNumber' => '10',
                    'builtYear' => 1983,
                ],
                'invalid' => ['streetNumber'],
                'httpStatus' => 400,
            ],
            [
                'name' => 'Invalid bool',
                'params' => [
                    'name' => 'FooBar',
                    'street' => '123 Main St.',
                    'streetNumber' => 10,
                    'locked' => 'locked',
                ],
                'invalid' => ['locked'],
                'httpStatus' => 400,
            ],
            [
                'name' => 'Invalid optional',
                'params' => [
                    'name' => 'FooBar',
                    'street' => '123 Main St.',
                    'streetNumber' => 10,
                    'builtYear' => '1983',
                ],
                'invalid' => ['builtYear'],
                'httpStatus' => 400,
            ],
        ];

        foreach ($tests as $test) {
            $I->amGoingTo("Testing {$test['name']}");
            $I->sendPost(
                '/api/houses',
                $test['params']
            );
            $I->seeResponseCodeIs($test['httpStatus']);
            if (isset($test['invalid'])) {
                $response = $I->grabJsonResponse();
                $I->assertEquals($test['invalid'], $response->errors->invalid);
            }
        }
    }
}
