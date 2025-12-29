<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTester;

final class OpenApiCest
{
    public function _before(FunctionalTester $I): void
    {
        // Code here will be executed before each test function.
    }

    // All `public` methods will be executed as tests.
    public function tryGetOpenApiJson(FunctionalTester $I): void
    {
        $I->sendGet('/api/openapi');
        $I->seeResponseCodeIs(200);
        $response = $I->grabJsonResponse();
        $I->assertEquals('3.0.4', $response->openapi);
    }
}
